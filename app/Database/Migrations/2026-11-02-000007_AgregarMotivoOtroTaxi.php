<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Justificación de taxi "OTROS": el motivo que escribe el usuario. */
class AgregarMotivoOtroTaxi extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('solicitudes', [
            'just_taxi_otro' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => '', 'after' => 'just_taxi'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('solicitudes', 'just_taxi_otro');
    }
}
