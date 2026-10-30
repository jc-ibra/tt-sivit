<?php

namespace App\Controllers;

use App\Libraries\Auth as AuthLib;
use App\Models\BitacoraModel;
use App\Models\UsuarioModel;

class Auth extends BaseController
{
    /** Pantalla de bienvenida (sin sesión) o redirección al formulario. */
    public function inicio()
    {
        if (service('auth')->usuario()) {
            return redirect()->to('solicitudes');
        }
        $m = model(UsuarioModel::class);
        return view('inicio', [
            'usuarios'     => $m->paraPanel(),
            'operadorTieneClave' => (bool) (($op = $m->operador()) && ($op['password_hash'] || $op['legacy_hash'])),
            'yo'           => null,
        ]);
    }

    public function login()
    {
        $id    = (int) $this->request->getPost('usuario_id');
        $clave = (string) $this->request->getPost('clave');
        $limite = service('throttler');
        $clave_th = 'login_' . md5($this->request->getIPAddress() . '_' . $id);
        if ($limite->check($clave_th, 5, MINUTE) === false) {
            return redirect()->to('/')->with('error', 'Demasiados intentos. Espera un minuto.');
        }
        $u = model(UsuarioModel::class)->find($id);
        if (! $u || ! service('auth')->verificar($u, $clave)) {
            model(BitacoraModel::class)->registrar($u['id'] ?? null, 'login_fallido', 'usuario', $id);
            return redirect()->to('/')->with('error', 'Contraseña incorrecta')->with('abrir', $id);
        }
        service('auth')->entrar(model(UsuarioModel::class)->find($id));
        return redirect()->to('solicitudes');
    }

    /** Primera vez: el Operador Virtual crea su contraseña. Solo mientras no tenga una. */
    public function crearOperador()
    {
        $m  = model(UsuarioModel::class);
        $op = $m->operador();
        if (! $op || $op['password_hash'] || $op['legacy_hash']) {
            return redirect()->to('/')->with('error', 'El operador ya tiene contraseña.');
        }
        $min = config('Sivit')->passwordMinimo;
        $a   = (string) $this->request->getPost('clave');
        $b   = (string) $this->request->getPost('clave2');
        if (mb_strlen($a) < $min) {
            return redirect()->to('/')->with('error', "Usa al menos $min caracteres")->with('abrir', $op['id']);
        }
        if ($a !== $b) {
            return redirect()->to('/')->with('error', 'Las contraseñas no coinciden')->with('abrir', $op['id']);
        }
        $m->update($op['id'], ['password_hash' => AuthLib::hash($a)]);
        service('auth')->entrar($m->find($op['id']));
        return redirect()->to('solicitudes');
    }

    public function logout()
    {
        service('auth')->salir();
        return redirect()->to('/');
    }
}
