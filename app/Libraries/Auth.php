<?php

namespace App\Libraries;

use App\Models\BitacoraModel;
use App\Models\UsuarioModel;

/**
 * Sesión y contraseñas. El usuario se recarga de la BD en cada petición
 * (si se elimina el perfil o cambia el rol, surte efecto de inmediato).
 */
class Auth
{
    private ?array $cache = null;

    /** Usuario de la sesión; se recarga de la BD y solo se reutiliza dentro de la misma petición/sesión. */
    public function usuario(): ?array
    {
        $id = session('usuario_id');
        if (! $id) {
            return $this->cache = null;
        }
        if ($this->cache && (int) $this->cache['id'] === (int) $id) {
            return $this->cache;
        }
        $u = model(UsuarioModel::class)->find((int) $id);
        if ($u && ($u['password_hash'] || $u['legacy_hash'])) {
            return $this->cache = $u;
        }
        session()->remove('usuario_id');
        return $this->cache = null;
    }

    public function entrar(array $u): void
    {
        session()->regenerate(true);
        session()->set('usuario_id', (int) $u['id']);
        $this->cache = $u;
        model(BitacoraModel::class)->registrar((int) $u['id'], 'login', 'usuario', $u['id']);
    }

    public function salir(): void
    {
        $u = $this->usuario();
        if ($u) {
            model(BitacoraModel::class)->registrar((int) $u['id'], 'logout', 'usuario', $u['id']);
        }
        session()->destroy();
        $this->cache = null;
    }

    public static function hash(string $clave): string
    {
        return password_hash($clave, PASSWORD_DEFAULT);
    }

    /**
     * Verifica la contraseña. Si el perfil viene del sistema anterior (SHA-256 + sal)
     * y coincide, se convierte a password_hash y se borra el hash viejo.
     */
    public function verificar(array $u, string $clave): bool
    {
        if (! empty($u['password_hash'])) {
            if (! password_verify($clave, $u['password_hash'])) {
                return false;
            }
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                model(UsuarioModel::class)->update($u['id'], ['password_hash' => self::hash($clave)]);
            }
            return true;
        }
        if (! empty($u['legacy_hash']) && hash_equals($u['legacy_hash'], hash('sha256', ($u['legacy_salt'] ?? '') . $clave))) {
            model(UsuarioModel::class)->update($u['id'], ['password_hash' => self::hash($clave), 'legacy_hash' => null, 'legacy_salt' => null]);
            return true;
        }
        return false;
    }
}
