<?php

namespace App\Controllers;

use App\Models\EmpleadoModel;
use App\Models\SolicitudModel;
use App\Models\UsuarioModel;

class Indicadores extends BaseController
{
    /** Suma por clave descartando vacíos y ceros; ordenado de mayor a menor (groupSum del original). */
    private function agrupar(array $lista, callable $clave, callable $valor): array
    {
        $m = [];
        foreach ($lista as $r) {
            $k = $clave($r);
            if ($k === null || $k === '') {
                continue;
            }
            $v = $valor($r);
            if (! $v) {
                continue;
            }
            $m[$k] ??= ['k' => (string) $k, 'v' => 0.0, 'n' => 0];
            $m[$k]['v'] += $v;
            $m[$k]['n']++;
        }
        $m = array_values($m);
        usort($m, fn ($a, $b) => $b['v'] <=> $a['v']);
        return $m;
    }

    /**
     * Todos los ingenieros del catálogo con su gasto en el periodo (aunque sea $0),
     * más los externos que tengan solicitudes. De mayor a menor gasto.
     */
    private function ingenieros(array $L): array
    {
        $gasto = [];
        foreach ($L as $r) {
            $k = (string) $r['nomina'];
            $gasto[$k] ??= ['nombre' => $r['nombre'], 'n' => 0, 'taxis' => 0.0, 'total' => 0.0, 'perfiles' => []];
            $gasto[$k]['n']++;
            $gasto[$k]['perfiles'][$r['creado_por'] ?: '(sin perfil)'] = true;
            $gasto[$k]['taxis'] += (float) $r['taxis'];
            $gasto[$k]['total'] += (float) $r['total'];
        }
        $out = [];
        foreach (model(EmpleadoModel::class)->findAll() as $e) {
            $g     = $gasto[$e['nomina']] ?? ['n' => 0, 'taxis' => 0.0, 'total' => 0.0, 'perfiles' => []];
            $out[] = ['id' => (int) $e['id'], 'nombre' => $e['nombre'], 'nomina' => $e['nomina'], 'estatus' => $e['estatus'], 'coordinador' => $e['coordinador'],
                'regional' => $e['regional'], 'perfiles' => $this->perfiles($g), 'n' => $g['n'], 'taxis' => $g['taxis'], 'total' => $g['total']];
            unset($gasto[$e['nomina']]);
        }
        foreach ($gasto as $nomina => $g) {   // proveedores / no está en base
            $out[] = ['id' => null, 'nombre' => $g['nombre'], 'nomina' => (string) $nomina, 'estatus' => 'EXTERNO', 'coordinador' => '', 'regional' => '',
                'perfiles' => $this->perfiles($g), 'n' => $g['n'], 'taxis' => $g['taxis'], 'total' => $g['total']];
        }
        usort($out, fn ($a, $b) => $b['total'] <=> $a['total'] ?: strcmp($a['nombre'], $b['nombre']));
        return $out;
    }

    /** Perfiles que capturaron solicitudes del ingeniero, en orden alfabético. */
    private function perfiles(array $g): string
    {
        $p = array_keys($g['perfiles']);
        sort($p);
        return implode(', ', $p);
    }

    public function index()
    {
        $u = service('auth')->usuario();
        $g = $this->request->getGet();
        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($g['desde'] ?? '')) ? $g['desde'] : '';
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($g['hasta'] ?? '')) ? $g['hasta'] : '';

        $m = model(SolicitudModel::class);
        $L = $m->visiblesPara($u)->filtrar(['desde' => $desde, 'hasta' => $hasta])
            ->select('nomina, nombre, creado_por, coordinador, cliente, cc, lf, fecha, ok, concepto_otros, tran_publico, taxis, autobus, comida, hotel, otros, total')->findAll();

        $S      = fn (string $k) => array_sum(array_map(fn ($r) => (float) $r[$k], $L));
        $total  = $S('total');
        $taxi   = $S('taxis');
        $n      = count($L);
        $conTaxi = count(array_filter($L, fn ($r) => (float) $r['taxis'] > 0));
        $personas = count(array_unique(array_column($L, 'nomina')));
        $rev    = count(array_filter($L, fn ($r) => $r['ok'] === 'REVISAR'));
        $foraneo = array_sum(array_map(fn ($r) => $r['lf'] === 'F' ? (float) $r['total'] : 0, $L));
        $pct    = fn ($v) => $total ? (int) round($v / $total * 100) : 0;
        $plural = fn (int $x, string $s, string $p) => $x === 1 ? $s : $p;

        $tiles = [
            ['Total viáticos', dinero($total), "$n " . $plural($n, 'solicitud', 'solicitudes') . " · $personas " . $plural($personas, 'persona', 'personas')],
            ['Promedio por solicitud', dinero($n ? $total / $n : 0), 'Total entre número de solicitudes'],
            ['Gasto en taxis', dinero($taxi), $pct($taxi) . "% del total · $conTaxi " . $plural($conTaxi, 'solicitud', 'solicitudes') . ' con taxi'],
            ['Gasto foráneo', dinero($foraneo), $pct($foraneo) . '% del total'],
            ['Por revisar', (string) $rev, 'Solicitudes de personal externo / no está en base', $rev > 0],
        ];

        $conc = [];
        foreach ([['Transporte público', 'tran_publico'], ['Taxis', 'taxis'], ['Autobús', 'autobus'], ['Comida', 'comida'], ['Hotel', 'hotel'], ['Otros', 'otros']] as [$et, $c]) {
            $v = $S($c);
            if ($v > 0) {
                $conc[] = ['k' => $et, 'v' => $v, 'n' => count(array_filter($L, fn ($r) => (float) $r[$c] > 0))];
            }
        }
        usort($conc, fn ($a, $b) => $b['v'] <=> $a['v']);

        $dias = $this->agrupar($L, fn ($r) => $r['fecha'], fn ($r) => (float) $r['total']);
        usort($dias, fn ($a, $b) => strcmp($b['k'], $a['k']));
        $dias = array_map(fn ($x) => ['k' => dmy($x['k'])] + $x, array_slice($dias, 0, 15));

        $barras = [
            'concepto' => $conc,
            'ing'      => $this->agrupar($L, fn ($r) => $r['nombre'], fn ($r) => (float) $r['total']),
            'taxi'     => $this->agrupar($L, fn ($r) => $r['nombre'], fn ($r) => (float) $r['taxis']),
            'coord'    => $this->agrupar($L, fn ($r) => $r['coordinador'] ?: 'SIN COORDINADOR', fn ($r) => (float) $r['total']),
            'cli'      => $this->agrupar($L, fn ($r) => $r['cliente'] ? $r['cliente'] . ' (' . $r['cc'] . ')' : '', fn ($r) => (float) $r['total']),
            'otros'    => $this->agrupar($L, fn ($r) => $r['concepto_otros'] ?: 'SIN CONCEPTO', fn ($r) => (float) $r['otros']),
            'lf'       => $this->agrupar($L, fn ($r) => $r['lf'] === 'L' ? 'Local' : ($r['lf'] === 'F' ? 'Foráneo' : ''), fn ($r) => (float) $r['total']),
            'dia'      => $dias,
        ];

        return view('indicadores/index', [
            'yo' => $u, 'tab' => 'kpi', 'tiles' => $tiles, 'barras' => $barras, 'desde' => $desde, 'hasta' => $hasta,
            'ingenieros' => $this->ingenieros($L),
            'conteo' => $m->visiblesPara($u)->countAllResults(),
            'usuarios' => model(UsuarioModel::class)->paraPanel(),
        ]);
    }
}
