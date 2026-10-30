<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CrearCatalogos extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'nomina'      => ['type' => 'VARCHAR', 'constraint' => 20],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'estatus'     => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => ''],
            'coordinador' => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'regional'    => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('nomina');
        $this->forge->addKey('nombre');
        $this->forge->createTable('empleados', true);

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'cliente'    => ['type' => 'VARCHAR', 'constraint' => 150],
            'cc'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('cliente');
        $this->forge->createTable('centros_costo', true);

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'usuario_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'por'         => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'modo'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'completo'], // completo | parcial
            'n_empleados' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'n_cc'        => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('catalogo_actualizaciones', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('catalogo_actualizaciones', true);
        $this->forge->dropTable('centros_costo', true);
        $this->forge->dropTable('empleados', true);
    }
}
