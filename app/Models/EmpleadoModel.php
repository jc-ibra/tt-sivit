<?php

namespace App\Models;

use CodeIgniter\Model;

class EmpleadoModel extends Model
{
    protected $table         = 'empleados';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['nomina', 'nombre', 'estatus', 'coordinador', 'regional'];

    public function porNomina(string $nomina): ?array
    {
        return $this->where('nomina', $nomina)->first();
    }
}
