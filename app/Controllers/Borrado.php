<?php

namespace App\Controllers;

use App\Models\BitacoraModel;
use App\Models\EmpleadoModel;
use App\Models\SolicitudModel;

/**
 * Borrado de varios registros a la vez (solo el operador; la ruta tiene el filtro rol:operador).
 * Pide la contraseña del operador dos veces: las dos deben coincidir y ser la correcta.
 */
class Borrado extends BaseController
{
    public function borrar()
    {
        $u    = service('auth')->usuario();
        $tipo = (string) $this->request->getPost('tipo');
        $ids  = array_values(array_unique(array_filter(array_map('intval', (array) $this->request->getPost('ids')))));
        $c1   = (string) $this->request->getPost('clave');
        $c2   = (string) $this->request->getPost('clave2');

        if (! in_array($tipo, ['ingenieros', 'solicitudes'], true)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'No sé qué borrar']);
        }
        if (! $ids) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Marca al menos uno para borrar']);
        }
        if ($c1 === '' || $c2 === '') {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Escribe tu contraseña en los dos campos']);
        }
        if ($c1 !== $c2) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Las dos contraseñas no coinciden']);
        }
        if (! service('auth')->verificar($u, $c1)) {
            model(BitacoraModel::class)->registrar($u['id'], 'borrado_rechazado', $tipo, implode(',', $ids));
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Contraseña incorrecta: no se borró nada']);
        }

        $m     = $tipo === 'ingenieros' ? model(EmpleadoModel::class) : model(SolicitudModel::class);
        $filas = $m->whereIn('id', $ids)->findAll();
        if (! $filas) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Ya no existen: recarga la página']);
        }
        $borrar = array_column($filas, 'id');
        $db     = db_connect();
        $db->transStart();
        $m->delete($borrar);   // solicitudes: borrado lógico (deleted_at); ingenieros: se quitan del catálogo
        model(BitacoraModel::class)->registrar($u['id'], 'borrar_varios', $tipo === 'ingenieros' ? 'empleado' : 'solicitud', implode(',', $borrar), $filas);
        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'No se pudo borrar; no se cambió nada']);
        }

        $n   = count($borrar);
        $que = $tipo === 'ingenieros' ? ($n === 1 ? 'ingeniero borrado' : 'ingenieros borrados') : ($n === 1 ? 'solicitud borrada' : 'solicitudes borradas');
        session()->setFlashdata('ok', "✓ $n $que");
        return $this->response->setJSON(['borrados' => $n]);
    }
}
