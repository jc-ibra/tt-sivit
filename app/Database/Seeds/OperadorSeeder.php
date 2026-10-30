<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Crea el "Operador Virtual" sin contraseña: la primera persona que entre se la asigna (igual que en el sistema anterior). */
class OperadorSeeder extends Seeder
{
    public function run(): void
    {
        $db = $this->db->table('usuarios');
        if ($db->where('rol', 'operador')->countAllResults() === 0) {
            $db->insert([
                'legacy_id'  => 'u-operador',
                'nombre'     => 'OPERADOR VIRTUAL',
                'rol'        => 'operador',
                'color'      => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
