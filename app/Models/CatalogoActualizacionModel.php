<?php

namespace App\Models;

use CodeIgniter\Model;

class CatalogoActualizacionModel extends Model
{
    protected $table         = 'catalogo_actualizaciones';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['usuario_id', 'por', 'modo', 'n_empleados', 'n_cc', 'created_at'];

    public function ultima(): ?array
    {
        return $this->orderBy('id', 'DESC')->first();
    }
}
