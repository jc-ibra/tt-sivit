<?php

namespace App\Controllers;

use Config\Database;
use Config\Services;

/**
 * Instalador web: corre las migraciones y crea el Operador Virtual, para hosting sin terminal.
 *
 * Seguridad:
 *  - Desactivado mientras no exista `sivit.setupToken` en el .env.
 *  - Exige ese token (comparación en tiempo constante) y limita los intentos por IP.
 *  - No usa sesión (la tabla ci_sessions todavía puede no existir).
 *  - Al terminar con éxito escribe writable/setup.lock y deja de responder. Para volver a usarlo
 *    (por ejemplo tras subir migraciones nuevas) borra ese archivo.
 */
class Setup extends BaseController
{
    private function lock(): string
    {
        return WRITEPATH . 'setup.lock';
    }

    private function token(): string
    {
        return (string) config('Sivit')->setupToken;
    }

    /** Revisiones del servidor antes de instalar. @return list<array{0:string,1:bool,2:string}> */
    private function requisitos(): array
    {
        $r   = [['PHP 8.2 o superior', version_compare(PHP_VERSION, '8.2', '>='), PHP_VERSION]];
        foreach (['intl', 'mbstring', 'mysqli', 'gd', 'zip', 'dom', 'xml', 'simplexml', 'xmlreader', 'xmlwriter', 'fileinfo'] as $e) {
            $r[] = ["Extensión $e", extension_loaded($e), extension_loaded($e) ? 'ok' : 'falta'];
        }
        $r[] = ['Carpeta writable/ con permiso de escritura', is_writable(WRITEPATH), WRITEPATH];
        try {
            $db = Database::connect();
            $db->initialize();
            $r[] = ['Conexión a MySQL', true, $db->getDatabase() . '@' . $db->hostname];
        } catch (\Throwable $e) {
            $r[] = ['Conexión a MySQL', false, 'Revisa database.default.* en el .env (' . mb_substr($e->getMessage(), 0, 120) . ')'];
        }
        return $r;
    }

    private function pagina(array $datos, int $status = 200)
    {
        return $this->response->setStatusCode($status)->setBody(view('setup', $datos + ['resultado' => null, 'error' => null]));
    }

    public function index()
    {
        if (is_file($this->lock())) {
            return $this->response->setStatusCode(403)->setBody('La instalación ya se realizó. Borra writable/setup.lock si necesitas volver a ejecutarla.');
        }
        if ($this->token() === '') {
            return $this->pagina(['estado' => 'sin_token', 'reqs' => []], 503);
        }
        return $this->pagina(['estado' => 'listo', 'reqs' => $this->requisitos()]);
    }

    public function ejecutar()
    {
        if (is_file($this->lock()) || $this->token() === '') {
            return $this->response->setStatusCode(403)->setBody('Instalador desactivado.');
        }
        $ip = $this->request->getIPAddress();
        if (Services::throttler()->check('setup_' . md5($ip), 5, MINUTE) === false) {
            return $this->response->setStatusCode(429)->setBody('Demasiados intentos. Espera un minuto.');
        }
        if (! hash_equals($this->token(), (string) $this->request->getPost('token'))) {
            log_message('warning', 'Setup: token incorrecto desde ' . $ip);
            return $this->pagina(['estado' => 'listo', 'reqs' => $this->requisitos(), 'error' => 'Token incorrecto.'], 403);
        }

        try {
            $migraciones = Services::migrations();
            $migraciones->setNamespace(null);
            $historia = static fn () => array_map(static fn ($m) => (array) $m, $migraciones->getHistory());   // getHistory() devuelve objetos
            $antes    = array_column($historia(), 'version');
            $migraciones->latest();
            $despues = $historia();
            $nuevas  = array_values(array_filter($despues, static fn ($m) => ! in_array($m['version'], $antes, true)));
            Database::seeder()->call('OperadorSeeder');
            $tablas = Database::connect()->listTables();
        } catch (\Throwable $e) {
            log_message('error', 'Setup: ' . $e->getMessage());
            return $this->pagina(['estado' => 'listo', 'reqs' => $this->requisitos(), 'error' => 'Falló la instalación: ' . $e->getMessage()], 500);
        }

        @file_put_contents($this->lock(), date('c') . ' ' . $ip . "\n");
        return $this->pagina([
            'estado' => 'terminado', 'reqs' => [],
            'resultado' => ['nuevas' => array_map(static fn ($m) => $m['class'] ?? $m['version'], $nuevas), 'total' => count($despues), 'tablas' => $tablas, 'bloqueado' => is_file($this->lock())],
        ]);
    }
}
