<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * En una solicitud a proveedor sin número, la "nómina" es el nombre escrito (sin espacios):
 * con 20 caracteres MySQL lo cortaba sin avisar. Se amplía al mismo tamaño que el nombre.
 */
class AmpliarNominaSolicitudes extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('solicitudes', [
            'nomina' => ['name' => 'nomina', 'type' => 'VARCHAR', 'constraint' => 150],
        ]);
    }

    /** No se vuelve a 20: las nóminas largas ya guardadas no cabrían (fallaría o se cortarían). */
    public function down(): void
    {
    }
}
