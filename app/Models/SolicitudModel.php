<?php

namespace App\Models;

use CodeIgniter\Model;

class SolicitudModel extends Model
{
    protected $table          = 'solicitudes';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'legacy_id', 'usuario_id', 'fecha', 'nomina', 'nombre', 'coordinador', 'regional', 'estatus', 'tipo',
        'folio_gp', 'cliente', 'cc', 'lf', 'traslado', 'tran_publico', 'taxis', 'just_taxi', 'autobus', 'comida',
        'hotel', 'otros', 'concepto_otros', 'comentarios', 'total', 'ok', 'enviado_en', 'enviado_a',
        'dep_completo', 'real_dep', 'real_dep_por', 'real_dep_en', 'creado_por', 'editado_por',
    ];

    /** Columnas por las que se puede ordenar el concentrado (nunca se arma SQL con texto del usuario). */
    public const ORDENABLES = [
        'n' => 'id', 'creadoPor' => 'creado_por', 'fecha' => 'fecha', 'coordinador' => 'coordinador', 'regional' => 'regional',
        'nombre' => 'nombre', 'nomina' => 'nomina', 'estatus' => 'estatus', 'tipo' => 'tipo', 'folioGP' => 'folio_gp',
        'cliente' => 'cliente', 'cc' => 'cc', 'lf' => 'lf', 'traslado' => 'traslado', 'tranPublico' => 'tran_publico',
        'taxis' => 'taxis', 'justTaxi' => 'just_taxi', 'autobus' => 'autobus', 'comida' => 'comida', 'hotel' => 'hotel',
        'otros' => 'otros', 'conceptoOtros' => 'concepto_otros', 'comentarios' => 'comentarios', 'total' => 'total',
        'ok' => 'ok', 'enviadoEn' => 'enviado_en', 'depCompleto' => 'dep_completo', 'realDep' => 'real_dep',
    ];

    public static function veTodo(array $u): bool
    {
        return in_array($u['rol'], ['operador', 'viatico'], true);
    }

    public static function puedeBorrar(array $u): bool
    {
        return $u['rol'] !== 'viatico';
    }

    /** Limita la consulta a lo que el usuario puede ver: coordinador = solo lo suyo. */
    public function visiblesPara(array $u): static
    {
        if (! self::veTodo($u)) {
            $this->where('usuario_id', (int) $u['id']);
        }
        return $this;
    }

    /** Una solicitud por id, solo si el usuario puede verla (si no, null → 404). */
    public function visible(array $u, int $id): ?array
    {
        return $this->visiblesPara($u)->where('id', $id)->first();
    }

    /**
     * Filtros del concentrado: desde, hasta, cliente, coord, lf, ok, q.
     */
    public function filtrar(array $f): static
    {
        if (! empty($f['desde'])) {
            $this->where('fecha >=', $f['desde']);
        }
        if (! empty($f['hasta'])) {
            $this->where('fecha <=', $f['hasta']);
        }
        if (! empty($f['cliente'])) {
            $this->where('cliente', $f['cliente']);
        }
        if (! empty($f['coord'])) {
            $this->where('coordinador', $f['coord']);
        }
        if (in_array($f['lf'] ?? '', ['L', 'F'], true)) {
            $this->where('lf', $f['lf']);
        }
        if (in_array($f['ok'] ?? '', ['OK', 'REVISAR'], true)) {
            $this->where('ok', $f['ok']);
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $this->groupStart();
            foreach (['nombre', 'nomina', 'folio_gp', 'comentarios', 'traslado', 'cliente', 'coordinador'] as $i => $c) {
                $i === 0 ? $this->like($c, $q) : $this->orLike($c, $q);
            }
            $this->groupEnd();
        }
        return $this;
    }

    /** Número consecutivo "#" de cada solicitud según el orden de captura (visibles para el usuario). */
    public function consecutivos(array $u): array
    {
        $ids = $this->visiblesPara($u)->select('id')->orderBy('created_at')->orderBy('id')->findColumn('id') ?? [];
        $out = [];
        foreach ($ids as $i => $id) {
            $out[(int) $id] = $i + 1;
        }
        return $out;
    }

    /** Solicitudes aún no enviadas ("por enviar"). */
    public function porEnviar(array $u): array
    {
        return $this->visiblesPara($u)->where('enviado_en', null)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll();
    }

    /** Estado del depósito: 'si' | 'no' | ''. */
    public static function depEstado(array $r): string
    {
        if (in_array($r['dep_completo'] ?? null, ['si', 'no'], true)) {
            return $r['dep_completo'];
        }
        if (($r['real_dep'] ?? null) !== null && $r['real_dep'] !== '') {
            return abs((float) $r['real_dep'] - (float) $r['total']) < 0.005 ? 'si' : 'no';
        }
        return '';
    }

    public static function pctDep(array $r): ?float
    {
        if (($r['real_dep'] ?? null) === null || $r['real_dep'] === '' || ! (float) $r['total']) {
            return null;
        }
        return (float) $r['real_dep'] / (float) $r['total'] * 100;
    }
}
