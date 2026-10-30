<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Uso: 'filter' => 'rol:operador' o 'rol:operador,viatico' */
class RolFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $u = service('auth')->usuario();
        if (! $u) {
            return redirect()->to('/');
        }
        if (! in_array($u['rol'], $arguments ?? [], true)) {
            return service('response')->setStatusCode(403)->setBody('No tienes permiso para esta acción.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
