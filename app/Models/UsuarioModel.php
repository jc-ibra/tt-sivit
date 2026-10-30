<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table            = 'usuarios';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'legacy_id', 'nombre', 'rol', 'password_hash', 'legacy_hash', 'legacy_salt',
        'foto', 'color', 'tema', 'mail_destino',
    ];

    /** Perfiles para el panel lateral (sin la foto: se pide aparte por /usuarios/{id}/foto). */
    public function paraPanel(): array
    {
        return $this->select('id, nombre, rol, color, tema, (password_hash IS NOT NULL OR legacy_hash IS NOT NULL) AS tiene_clave, (foto IS NOT NULL) AS tiene_foto', false)
            ->orderBy('nombre')->findAll();
    }

    public function operador(): ?array
    {
        return $this->where('rol', 'operador')->first();
    }

    public function nombreExiste(string $nombre, ?int $exceptoId = null): bool
    {
        $q = $this->where('UPPER(nombre) = ' . $this->db->escape(mb_strtoupper($nombre)), null, false);
        if ($exceptoId) {
            $q->where('id !=', $exceptoId);
        }
        return $q->countAllResults() > 0;
    }
}
