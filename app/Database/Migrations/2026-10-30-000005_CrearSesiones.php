<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CrearSesiones extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'VARCHAR', 'constraint' => 128],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45],
            'timestamp'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'data'       => ['type' => 'BLOB'],
        ]);
        $this->forge->addKey(['id', 'ip_address'], true);
        $this->forge->addKey('timestamp');
        $this->forge->createTable('ci_sessions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ci_sessions', true);
    }
}
