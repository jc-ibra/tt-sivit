<?php

namespace App\Models;

use CodeIgniter\Model;

class CentroCostoModel extends Model
{
    protected $table         = 'centros_costo';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['cliente', 'cc'];

    /** @return array<string,string> cliente => cc */
    public function mapa(): array
    {
        $out = [];
        foreach ($this->orderBy('cliente')->findAll() as $r) {
            $out[$r['cliente']] = $r['cc'];
        }
        return $out;
    }
}
