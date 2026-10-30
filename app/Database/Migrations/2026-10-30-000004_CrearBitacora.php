<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CrearBitacora extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'accion'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'entidad'    => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'entidad_id' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'antes'      => ['type' => 'TEXT', 'null' => true],   // JSON
            'despues'    => ['type' => 'TEXT', 'null' => true],   // JSON
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['entidad', 'entidad_id']);
        $this->forge->addKey('created_at');
        $this->forge->createTable('bitacora', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('bitacora', true);
    }
}
