<?php

namespace App\Controllers;

use App\Libraries\CorreoSolicitudes;
use App\Libraries\SolicitudReglas;
use App\Models\BitacoraModel;
use App\Models\CentroCostoModel;
use App\Models\EmpleadoModel;
use App\Models\SolicitudModel;
use App\Models\UsuarioModel;

class Solicitudes extends BaseController
{
    private function modelo(): SolicitudModel
    {
        return model(SolicitudModel::class);
    }

    private function usuario(): array
    {
        return service('auth')->usuario();
    }

    private function vistaFormulario(?array $edit, array $errs = [], array $old = [])
    {
        $u = $this->usuario();
        $m = $this->modelo();
        $porEnviar = $m->porEnviar($u);
        $cons = $m->consecutivos($u);
        return view('solicitudes/index', [
            'yo' => $u, 'tab' => 'form',
            'edit' => $edit, 'errs' => $errs, 'old' => $old,
            'porEnviar' => $porEnviar, 'cons' => $cons,
            'conteo' => $m->visiblesPara($u)->countAllResults(),
            'catalogo' => $this->catalogo(),
            'usuarios' => model(UsuarioModel::class)->paraPanel(),
            'correo' => $u['mail_destino'] ?: config('Sivit')->correoPredeterminado,
        ]);
    }

    /** Catálogo para el autollenado de la vista previa (el servidor vuelve a validar todo al guardar). */
    private function catalogo(): array
    {
        return [
            'emp' => array_map(fn ($e) => [$e['nombre'], $e['nomina'], $e['estatus'], $e['coordinador'], $e['regional']], model(EmpleadoModel::class)->orderBy('nombre')->findAll()),
            'cc'  => model(CentroCostoModel::class)->mapa(),
        ];
    }

    public function index()
    {
        return $this->vistaFormulario(null);
    }

    public function editar(int $id)
    {
        $s = $this->modelo()->visible($this->usuario(), $id);
        if (! $s) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $this->vistaFormulario($s);
    }

    public function guardar()
    {
        $u   = $this->usuario();
        $res = (new SolicitudReglas())->validar($this->request->getPost());
        if ($res['errs']) {
            return $this->vistaFormulario(null, $res['errs'], $this->request->getPost());
        }
        $rec = $res['rec'] + ['usuario_id' => $u['id'], 'creado_por' => $u['nombre']];
        $id  = $this->modelo()->insert($rec);
        model(BitacoraModel::class)->registrar($u['id'], 'crear', 'solicitud', $id, null, $rec);
        return redirect()->to('solicitudes')->with('ok', 'Solicitud guardada');
    }

    public function actualizar(int $id)
    {
        $u = $this->usuario();
        $m = $this->modelo();
        $s = $m->visible($u, $id);
        if (! $s) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $res = (new SolicitudReglas())->validar($this->request->getPost(), $s['just_taxi']);
        if ($res['errs']) {
            return $this->vistaFormulario($s, $res['errs'], $this->request->getPost());
        }
        $rec = $res['rec'] + ['editado_por' => $u['nombre']];
        // si cambió el total, el depósito ya capturado puede dejar de coincidir: se conserva lo capturado y se recalcula el estado al mostrar
        $m->update($id, $rec);
        model(BitacoraModel::class)->registrar($u['id'], 'editar', 'solicitud', $id, $s, $rec);
        return redirect()->to('solicitudes')->with('ok', 'Solicitud actualizada');
    }

    public function eliminar(int $id)
    {
        $u = $this->usuario();
        if (! SolicitudModel::puedeBorrar($u)) {
            return redirect()->back()->with('error', 'Tu perfil puede editar las solicitudes, pero no eliminarlas');
        }
        $m = $this->modelo();
        $s = $m->visible($u, $id);
        if (! $s) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $m->delete($id);
        model(BitacoraModel::class)->registrar($u['id'], 'eliminar', 'solicitud', $id, $s);
        return redirect()->back()->with('ok', 'Solicitud eliminada');
    }

    /** Depósito real (AJAX): accion = si | no | monto. */
    public function deposito(int $id)
    {
        $u = $this->usuario();
        $m = $this->modelo();
        $s = $m->visible($u, $id);
        if (! $s) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'No encontrada']);
        }
        $accion = (string) $this->request->getPost('accion');
        $upd    = [];
        if ($accion === 'si') {
            $upd = ['dep_completo' => 'si', 'real_dep' => $s['total']];
        } elseif ($accion === 'no') {
            $upd = ['dep_completo' => 'no'];
            if ($s['real_dep'] !== null && abs((float) $s['real_dep'] - (float) $s['total']) < 0.005) {
                $upd['real_dep'] = null;
            }
        } elseif ($accion === 'monto') {
            $t = preg_replace('/[$,\s]/', '', (string) $this->request->getPost('valor'));
            if ($t !== '' && (! is_numeric($t) || (float) $t < 0)) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'Escribe una cantidad válida']);
            }
            $upd = ['real_dep' => $t === '' ? null : round((float) $t, 2)];
            if (! $s['dep_completo']) {
                $upd['dep_completo'] = 'no';
            }
        } else {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Acción no válida']);
        }
        $upd += ['real_dep_por' => $u['nombre'], 'real_dep_en' => date('Y-m-d H:i:s')];
        $m->update($id, $upd);
        model(BitacoraModel::class)->registrar($u['id'], 'deposito', 'solicitud', $id, ['dep_completo' => $s['dep_completo'], 'real_dep' => $s['real_dep']], $upd);
        $n = $m->find($id);
        return $this->response->setJSON([
            'dep_completo' => $n['dep_completo'], 'real_dep' => $n['real_dep'], 'estado' => SolicitudModel::depEstado($n),
            'pct' => SolicitudModel::pctDep($n), 'total' => $n['total'],
        ]);
    }

    /**
     * Envía el correo desde el servidor (SMTP del .env) y, si salió bien, pasa al concentrado las que aún no estaban enviadas.
     * 503 + sinConfigurar si no hay servidor de correo: el navegador abre entonces el correo de la PC (como antes).
     */
    public function enviarCorreo()
    {
        $u   = $this->usuario();
        $ids = array_filter(array_map('intval', (array) $this->request->getPost('ids')));
        if (! $ids) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'No hay solicitudes para enviar']);
        }
        [$para, $malos] = CorreoSolicitudes::destinatarios((string) $this->request->getPost('para'));
        if ($malos) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Revisa el correo destino; no es válido: ' . implode(', ', $malos)]);
        }
        if (! $para) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Escribe el correo destino']);
        }
        if (count($para) > CorreoSolicitudes::MAX_DESTINATARIOS) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Máximo ' . CorreoSolicitudes::MAX_DESTINATARIOS . ' correos destino']);
        }
        $m    = $this->modelo();
        $rows = $m->visiblesPara($u)->whereIn('id', $ids)->orderBy('id')->findAll();
        if (! $rows) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'No encontré esas solicitudes']);
        }
        if (! CorreoSolicitudes::configurado()) {
            return $this->response->setStatusCode(503)->setJSON(['error' => 'El envío de correo no está configurado en el servidor', 'sinConfigurar' => true]);
        }

        $error = (new CorreoSolicitudes())->enviar($rows, $m->consecutivos($u), $para, $u);
        $a     = implode(', ', $para);
        if ($error !== null) {
            log_message('error', 'SIVIT: no se pudo enviar el correo de solicitudes a {para}: {error}', ['para' => $a, 'error' => $error]);
            return $this->response->setStatusCode(502)->setJSON(['error' => 'No se pudo enviar el correo: el servidor de correo no respondió o rechazó el envío. No se marcó nada como enviado; intenta de nuevo y, si sigue, avisa al administrador.']);
        }

        $pendientes = array_values(array_map(static fn ($r) => (int) $r['id'], array_filter($rows, static fn ($r) => $r['enviado_en'] === null)));
        $db         = db_connect();
        $db->transStart();
        if ($pendientes) {
            $m->builder()->whereIn('id', $pendientes)->update(['enviado_en' => date('Y-m-d H:i:s'), 'enviado_a' => mb_substr($a, 0, 255)]);
        }
        model(BitacoraModel::class)->registrar($u['id'], 'correo', 'solicitud', implode(',', array_column($rows, 'id')), null, ['enviado_a' => $a, 'marcadas' => $pendientes]);
        $db->transComplete();
        return $this->response->setJSON(['enviadas' => count($rows), 'marcadas' => count($pendientes), 'para' => $a]);
    }

    /** "Ya lo envié": pasa las solicitudes al concentrado. Solo las que el usuario puede ver. */
    public function marcarEnviadas()
    {
        $u   = $this->usuario();
        $ids = array_filter(array_map('intval', (array) $this->request->getPost('ids')));
        if (! $ids) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Sin solicitudes']);
        }
        $a  = mb_substr(trim((string) $this->request->getPost('enviado_a')), 0, 255);
        $m  = $this->modelo();
        $ok = $m->visiblesPara($u)->whereIn('id', $ids)->where('enviado_en', null)->findColumn('id') ?? [];
        if ($ok) {
            db_connect()->transStart();
            $m->builder()->whereIn('id', $ok)->update(['enviado_en' => date('Y-m-d H:i:s'), 'enviado_a' => $a]);
            model(BitacoraModel::class)->registrar($u['id'], 'enviar', 'solicitud', implode(',', $ok), null, ['enviado_a' => $a]);
            db_connect()->transComplete();
        }
        if ($ok) {   // aviso al recargar la página
            $n = count($ok);
            session()->setFlashdata('ok', '✓ ' . ($n === 1 ? 'La solicitud enviada pasó' : "Las $n solicitudes enviadas pasaron") . ' al Concentrado de solicitudes');
        }
        return $this->response->setJSON(['marcadas' => count($ok)]);
    }
}
