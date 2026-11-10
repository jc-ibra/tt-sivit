<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Los externos sin número de empleado se identifican por su nombre (sin espacios ni acentos):
 * con 20 caracteres MySQL lo cortaba sin avisar y dos nombres parecidos chocaban. Mismo tamaño que en solicitudes.
 */
class AmpliarNominaEmpleados extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('empleados', [
            'nomina' => ['name' => 'nomina', 'type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
        ]);
    }

    /** No se vuelve a 20: las claves largas ya guardadas no cabrían. */
    public function down(): void
    {
    }
}
