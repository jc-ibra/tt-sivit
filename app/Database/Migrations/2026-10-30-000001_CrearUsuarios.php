<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CrearUsuarios extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'legacy_id'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'nombre'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'rol'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'coordinador'], // operador | coordinador | viatico
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'legacy_hash'   => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],   // SHA-256 del sistema anterior; se borra al primer inicio de sesión
            'legacy_salt'   => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'foto'          => ['type' => 'MEDIUMTEXT', 'null' => true],                  // JPEG 160x160 en base64 (texto: seguro en cualquier motor)
            'color'         => ['type' => 'TINYINT', 'constraint' => 3, 'default' => 0],
            'tema'          => ['type' => 'CHAR', 'constraint' => 7, 'null' => true],
            'mail_destino'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('legacy_id');
        $this->forge->addKey('nombre');
        $this->forge->addKey('rol');
        $this->forge->createTable('usuarios', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('usuarios', true);
    }
}
