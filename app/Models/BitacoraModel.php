<?php

namespace App\Models;

use CodeIgniter\Model;

class BitacoraModel extends Model
{
    protected $table         = 'bitacora';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['usuario_id', 'accion', 'entidad', 'entidad_id', 'antes', 'despues', 'ip', 'created_at'];

    public function registrar(?int $usuarioId, string $accion, ?string $entidad = null, $entidadId = null, $antes = null, $despues = null): void
    {
        // nunca guardar contraseñas ni fotos en la bitácora
        $limpia = static function ($d) {
            if (! is_array($d)) {
                return $d;
            }
            unset($d['password_hash'], $d['legacy_hash'], $d['legacy_salt'], $d['foto']);
            return $d;
        };
        $this->insert([
            'usuario_id' => $usuarioId,
            'accion'     => $accion,
            'entidad'    => $entidad,
            'entidad_id' => $entidadId === null ? null : (string) $entidadId,
            'antes'      => $antes === null ? null : json_encode($limpia($antes), JSON_UNESCAPED_UNICODE),
            'despues'    => $despues === null ? null : json_encode($limpia($despues), JSON_UNESCAPED_UNICODE),
            'ip'         => service('request')->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
