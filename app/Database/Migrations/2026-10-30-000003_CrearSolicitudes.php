<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CrearSolicitudes extends Migration
{
    public function up(): void
    {
        $dinero = fn (bool $null = false) => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => $null ? null : 0, 'null' => $null];

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'legacy_id'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'usuario_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'fecha'          => ['type' => 'DATE'],
            // foto del catálogo al momento de capturar: no cambia si el catálogo se actualiza después
            'nomina'         => ['type' => 'VARCHAR', 'constraint' => 20],
            'nombre'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'coordinador'    => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'regional'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'estatus'        => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => ''],
            'tipo'           => ['type' => 'VARCHAR', 'constraint' => 30],
            'folio_gp'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'cliente'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'cc'             => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'SIN CC'],
            'lf'             => ['type' => 'CHAR', 'constraint' => 1],
            'traslado'       => ['type' => 'TEXT'],
            'tran_publico'   => $dinero(),
            'taxis'          => $dinero(),
            'just_taxi'      => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'autobus'        => $dinero(),
            'comida'         => $dinero(),
            'hotel'          => $dinero(),
            'otros'          => $dinero(),
            'concepto_otros' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'comentarios'    => ['type' => 'TEXT', 'null' => true],
            'total'          => $dinero(),
            'ok'             => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'REVISAR'], // OK | REVISAR
            'enviado_en'     => ['type' => 'DATETIME', 'null' => true],
            'enviado_a'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'dep_completo'   => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],          // si | no
            'real_dep'       => $dinero(true),
            'real_dep_por'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'real_dep_en'    => ['type' => 'DATETIME', 'null' => true],
            'creado_por'     => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'editado_por'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('legacy_id');
        foreach (['fecha', 'usuario_id', 'coordinador', 'cliente', 'enviado_en', 'nomina'] as $k) {
            $this->forge->addKey($k);
        }
        $this->forge->createTable('solicitudes', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('solicitudes', true);
    }
}
