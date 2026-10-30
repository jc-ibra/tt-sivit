<?php

namespace App\Controllers;

use App\Libraries\Auth as AuthLib;
use App\Models\BitacoraModel;
use App\Models\UsuarioModel;

class Usuarios extends BaseController
{
    private function yo(): array
    {
        return service('auth')->usuario();
    }

    /** Foto del perfil (se guarda como JPEG 160×160 en la BD). Requiere sesión; se cachea por versión. */
    public function foto(int $id)
    {
        if (! service('auth')->usuario()) {
            return $this->response->setStatusCode(401);
        }
        $u = model(UsuarioModel::class)->select('id, foto, updated_at')->find($id);
        if (! $u || ! $u['foto']) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $this->response->setHeader('Content-Type', 'image/jpeg')
            ->setHeader('Cache-Control', 'private, max-age=3600')->setBody((string) base64_decode($u['foto'], true));
    }

    /** Recorta la imagen subida a un cuadrado de 160×160 JPEG. Devuelve el JPEG en base64 o null si no es imagen válida. */
    private function procesarFoto(): ?string
    {
        $f = $this->request->getFile('foto');
        if (! $f || ! $f->isValid() || $f->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($f->getSizeByUnit('mb') > 5 || ! in_array($f->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return null;
        }
        $ruta = $f->getRealPath();
        $info = @getimagesize($ruta);
        if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40_000_000) {
            return null;   // no es imagen o es demasiado grande para procesarla
        }
        $src = @imagecreatefromstring((string) file_get_contents($ruta));
        if (! $src) {
            return null;
        }
        [$w, $h] = $info;
        $lado = min($w, $h);
        $dst  = imagecreatetruecolor(160, 160);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // fondo blanco para PNG/GIF con transparencia
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $lado, 2), intdiv($h - $lado, 2), 160, 160, $lado, $lado);
        ob_start();
        imagejpeg($dst, null, 85);
        $bytes = (string) ob_get_clean();
        return $bytes !== '' ? base64_encode($bytes) : null;
    }

    private function temaValido(?string $t): ?string
    {
        $validos = array_column(config('Sivit')->temas, 1);
        return in_array($t, $validos, true) ? $t : null;
    }

    public function crear()
    {
        $yo = $this->yo();
        if ($yo['rol'] !== 'operador') {
            return $this->response->setStatusCode(403)->setBody('No tienes permiso para esta acción.');
        }
        return $this->guardar(null);
    }

    public function actualizar(int $id)
    {
        return $this->guardar($id);
    }

    private function guardar(?int $id)
    {
        $yo = $this->yo();
        $m  = model(UsuarioModel::class);
        $t  = $id ? $m->find($id) : null;
        if ($id && ! $t) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $esOp = $yo['rol'] === 'operador';
        if ($id && ! $esOp && $yo['id'] != $id) {
            return $this->response->setStatusCode(403)->setBody('No tienes permiso para esta acción.');
        }
        $min  = config('Sivit')->passwordMinimo;
        $datos = ['tema' => $this->temaValido($this->request->getPost('tema'))];
        $err  = null;

        if ($esOp) {
            $nom = mb_strtoupper(trim((string) $this->request->getPost('nombre')));
            $a   = (string) $this->request->getPost('clave');
            $b   = (string) $this->request->getPost('clave2');
            if ($nom === '') {
                $err = 'Escribe el nombre';
            } elseif ($m->nombreExiste($nom, $id)) {
                $err = 'Ya existe un perfil con ese nombre';
            } elseif (! $id && $a === '') {
                $err = 'Asigna una contraseña';
            } elseif ($a !== '' && mb_strlen($a) < $min) {
                $err = "La contraseña debe tener al menos $min caracteres";
            } elseif ($a !== $b) {
                $err = 'Las contraseñas no coinciden';
            }
            if ($err) {
                return redirect()->back()->with('error', $err);
            }
            $datos['nombre'] = $nom;
            if (! $t || $t['rol'] !== 'operador') {
                $rol = (string) $this->request->getPost('rol');
                $datos['rol'] = in_array($rol, ['coordinador', 'viatico'], true) ? $rol : 'coordinador';
            }
            if ($a !== '') {
                $datos['password_hash'] = AuthLib::hash($a);
                $datos['legacy_hash']   = null;
                $datos['legacy_salt']   = null;
            }
            if (! $id) {
                $datos['color'] = $m->where('rol', 'coordinador')->countAllResults();
            }
        }

        if ($this->request->getPost('quitar_foto')) {
            $datos['foto'] = null;
        } elseif (($foto = $this->procesarFoto()) !== null) {
            $datos['foto'] = $foto;
        }

        if ($id) {
            $m->update($id, $datos);
        } else {
            $id = $m->insert($datos);
        }
        model(BitacoraModel::class)->registrar($yo['id'], $t ? 'perfil_editar' : 'perfil_crear', 'usuario', $id, $t, $datos);
        return redirect()->back()->with('ok', $t ? 'Perfil actualizado' : 'Coordinador dado de alta');
    }

    public function eliminar(int $id)
    {
        $yo = $this->yo();
        if ($yo['rol'] !== 'operador') {
            return $this->response->setStatusCode(403)->setBody('No tienes permiso para esta acción.');
        }
        $m = model(UsuarioModel::class);
        $t = $m->find($id);
        if (! $t || $t['rol'] === 'operador') {
            return redirect()->back()->with('error', 'No se puede eliminar ese perfil');
        }
        $m->delete($id);   // borrado lógico: sus solicitudes siguen visibles para el operador
        model(BitacoraModel::class)->registrar($yo['id'], 'perfil_eliminar', 'usuario', $id, $t);
        return redirect()->back()->with('ok', 'Perfil eliminado');
    }

    /** Correo destino del usuario (antes se guardaba por navegador). */
    public function correo()
    {
        $yo = $this->yo();
        $v  = mb_substr(trim((string) $this->request->getPost('correo')), 0, 255);
        model(UsuarioModel::class)->update($yo['id'], ['mail_destino' => $v ?: null]);
        return $this->response->setJSON(['ok' => true]);
    }
}
