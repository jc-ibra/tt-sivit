<?php

namespace App\Libraries;

use App\Models\CatalogoActualizacionModel;
use App\Models\CentroCostoModel;
use App\Models\EmpleadoModel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lee un Excel/CSV de IDs y centros de costos, detecta cada hoja por sus encabezados,
 * compara contra la BD y aplica los cambios (port de abrirActualizacion del HTML original).
 */
class CatalogoImport
{
    /** @return list<array{nombre:string, filas:list<list<string>>}> */
    public function leerTablas(string $ruta, string $nombreOriginal): array
    {
        if (preg_match('/\.(xlsx|xlsm|xls)$/i', $nombreOriginal)) {
            $reader = IOFactory::createReaderForFile($ruta);
            $reader->setReadDataOnly(true);
            $wb  = $reader->load($ruta);
            $out = [];
            foreach ($wb->getWorksheetIterator() as $hoja) {
                $filas = [];
                foreach ($hoja->toArray('', true, true, false) as $f) {
                    $filas[] = array_map(static fn ($c) => is_float($c) && floor($c) == $c ? (string) (int) $c : SolicitudReglas::limpiar($c), $f);
                }
                $out[] = ['nombre' => $nombreOriginal . ' · ' . $hoja->getTitle(), 'filas' => $filas];
            }
            return $out;
        }
        $txt = (string) file_get_contents($ruta);
        $txt = preg_replace('/^\xEF\xBB\xBF/', '', $txt);
        $l1  = strtok($txt, "\n") ?: '';
        $sep = substr_count($l1, ';') > substr_count($l1, ',') ? ';' : ',';
        $fh  = fopen('php://temp', 'r+');
        fwrite($fh, $txt);
        rewind($fh);
        $filas = [];
        while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
            if (array_filter($r, static fn ($x) => trim((string) $x) !== '')) {
                $filas[] = $r;
            }
        }
        fclose($fh);
        return [['nombre' => $nombreOriginal, 'filas' => $filas]];
    }

    private static function h(?string $s): string
    {
        return preg_replace('/\s+/', ' ', SolicitudReglas::sinAcento($s));
    }

    private static function numNom($v): string
    {
        return SolicitudReglas::limpiarNomina($v);
    }

    /** Reconoce qué es una hoja (IDs o centros de costos) buscando encabezados en las primeras 10 filas. */
    public function detectar(array $filas): ?array
    {
        $buscar = static function (array $h, callable $f) {
            foreach ($h as $i => $x) {
                if ($f($x)) {
                    return $i;
                }
            }
            return -1;
        };
        for ($i = 0, $n = min(10, count($filas)); $i < $n; $i++) {
            $h   = array_map([self::class, 'h'], $filas[$i]);
            $nom = $buscar($h, static fn ($x) => str_contains($x, 'NOMINA') || in_array($x, ['ID', 'IDS', 'NUM EMPLEADO', 'NO EMPLEADO', 'NUMERO DE EMPLEADO'], true));
            $nam = $buscar($h, static fn ($x) => (str_contains($x, 'NOMBRE') || str_contains($x, 'INGENIERO') || $x === 'EMPLEADO') && ! str_contains($x, 'NOMINA'));
            if ($nom >= 0 && $nam >= 0) {
                return ['tipo' => 'emp', 'ini' => $i + 1, 'c' => [
                    'nom' => $nom, 'nam' => $nam,
                    'est' => $buscar($h, static fn ($x) => str_contains($x, 'ESTATUS') || str_contains($x, 'STATUS')),
                    'coord' => $buscar($h, static fn ($x) => str_contains($x, 'COORDINADOR') || str_contains($x, 'CORDINADOR')),
                    'reg' => $buscar($h, static fn ($x) => str_contains($x, 'REGIONAL') || $x === 'ZONA'),
                ]];
            }
            $cli = $buscar($h, static fn ($x) => str_contains($x, 'CLIENTE') || $x === 'DESCRIPCION');
            $cc  = $buscar($h, static fn ($x) => in_array($x, ['CC', 'CECO', 'NUMERO', 'NUMERO DE CC'], true) || str_contains($x, 'CENTRO DE COSTO') || str_contains($x, 'CENTROS DE COSTO'));
            if ($cli >= 0 && $cc >= 0) {
                return ['tipo' => 'cc', 'ini' => $i + 1, 'c' => ['cli' => $cli, 'cc' => $cc]];
            }
        }
        return null;
    }

    /**
     * Junta todas las hojas de los archivos en dos mapas: empleados (por nómina) y centros de costos (por cliente).
     * @param list<array{nombre:string, filas:array}> $tablas
     * @return array{emp:?array, cc:?array, repetidos:int, ignoradas:list<string>}
     */
    public function plan(array $tablas): array
    {
        $emp = null;
        $cc  = null;
        $rep = 0;
        $ign = [];
        $up  = static fn ($v) => mb_strtoupper(SolicitudReglas::limpiar($v));
        $actual = [];
        foreach (model(EmpleadoModel::class)->findAll() as $e) {
            $actual[$e['nomina']] = $e;
        }
        foreach ($tablas as $t) {
            $d = $this->detectar($t['filas']);
            if (! $d) {
                $ign[] = $t['nombre'];
                continue;
            }
            foreach (array_slice($t['filas'], $d['ini']) as $r) {
                if ($d['tipo'] === 'emp') {
                    $emp ??= [];
                    $num = self::numNom($r[$d['c']['nom']] ?? '');
                    if ($num === '') {
                        continue;
                    }
                    $rep += isset($emp[$num]) ? 1 : 0;
                    $prev = $actual[$num] ?? null;
                    $c    = $d['c'];
                    $emp[$num] = [
                        'nomina'      => $num,
                        'nombre'      => $up($r[$c['nam']] ?? '') ?: ($prev['nombre'] ?? ''),
                        'estatus'     => $c['est'] >= 0 ? ($up($r[$c['est']] ?? '') ?: 'ACTIVO') : ($prev['estatus'] ?? 'ACTIVO'),
                        'coordinador' => $c['coord'] >= 0 ? $up($r[$c['coord']] ?? '') : ($prev['coordinador'] ?? ''),
                        'regional'    => $c['reg'] >= 0 ? $up($r[$c['reg']] ?? '') : ($prev['regional'] ?? ''),
                    ];
                } else {
                    $cc ??= [];
                    $cli = $up($r[$d['c']['cli']] ?? '');
                    if ($cli === '') {
                        continue;
                    }
                    $rep += isset($cc[$cli]) ? 1 : 0;
                    $cc[$cli] = preg_replace('/\.0+$/', '', SolicitudReglas::limpiar($r[$d['c']['cc']] ?? ''));
                }
            }
        }
        return ['emp' => $emp, 'cc' => $cc, 'repetidos' => $rep, 'ignoradas' => $ign];
    }

    /** Compara el plan contra la BD (para la vista previa). */
    public function comparar(array $plan): array
    {
        $ch = ['nuevos' => [], 'cambios' => [], 'faltan' => [], 'bajas' => 0, 'ccNuevos' => [], 'ccCambios' => [], 'ccFaltan' => []];
        if ($plan['emp'] !== null) {
            $actual = [];
            foreach (model(EmpleadoModel::class)->findAll() as $e) {
                $actual[$e['nomina']] = $e;
            }
            foreach ($plan['emp'] as $k => $e) {
                $o = $actual[$k] ?? null;
                if (! $o) {
                    $ch['nuevos'][] = $e;
                    continue;
                }
                $d = [];
                foreach (['nombre' => 'nombre', 'estatus' => 'estatus', 'coordinador' => 'coordinador', 'regional' => 'regional'] as $campo => $et) {
                    if ($o[$campo] !== $e[$campo]) {
                        $d[] = "$et: " . ($o[$campo] ?: '—') . ' → ' . ($e[$campo] ?: '—');
                    }
                }
                if ($d) {
                    $ch['cambios'][] = ['e' => $e, 'd' => $d];
                    if ($e['estatus'] === 'BAJA' && $o['estatus'] !== 'BAJA') {
                        $ch['bajas']++;
                    }
                }
            }
            foreach ($actual as $k => $o) {
                if (! isset($plan['emp'][$k]) && $o['estatus'] !== 'BAJA') {
                    $ch['faltan'][] = $o;
                }
            }
        }
        if ($plan['cc'] !== null) {
            $actual = model(CentroCostoModel::class)->mapa();
            foreach ($plan['cc'] as $k => $v) {
                if (! isset($actual[$k])) {
                    $ch['ccNuevos'][] = [$k, $v];
                } elseif ((string) $actual[$k] !== (string) $v) {
                    $ch['ccCambios'][] = [$k, $actual[$k], $v];
                }
            }
            foreach ($actual as $k => $v) {
                if (! isset($plan['cc'][$k])) {
                    $ch['ccFaltan'][] = $k;
                }
            }
        }
        return $ch;
    }

    /**
     * Aplica el plan en una transacción. modo=completo: lo que no viene se marca BAJA / se quita; parcial: solo agrega y actualiza.
     */
    public function aplicar(array $plan, string $modo, ?array $usuario): array
    {
        $db = db_connect();
        $db->transStart();
        $eM = model(EmpleadoModel::class);
        $cM = model(CentroCostoModel::class);
        $nE = 0;
        $nC = 0;
        if ($plan['emp'] !== null) {
            $existentes = array_column($eM->findAll(), null, 'nomina');
            foreach ($plan['emp'] as $k => $e) {
                if (isset($existentes[$k])) {
                    $eM->update($existentes[$k]['id'], $e);
                } else {
                    $eM->insert($e);
                }
            }
            if ($modo === 'completo') {
                foreach ($existentes as $k => $o) {
                    if (! isset($plan['emp'][$k]) && $o['estatus'] !== 'BAJA') {
                        $eM->update($o['id'], ['estatus' => 'BAJA']);
                    }
                }
            }
            $nE = $eM->countAllResults();
        }
        if ($plan['cc'] !== null) {
            $existentes = array_column($cM->findAll(), null, 'cliente');
            foreach ($plan['cc'] as $k => $v) {
                if (isset($existentes[$k])) {
                    $cM->update($existentes[$k]['id'], ['cc' => $v]);
                } else {
                    $cM->insert(['cliente' => $k, 'cc' => $v]);
                }
            }
            if ($modo === 'completo') {
                foreach ($existentes as $k => $o) {
                    if (! isset($plan['cc'][$k])) {
                        $cM->delete($o['id']);
                    }
                }
            }
            $nC = $cM->countAllResults();
        }
        $nE = $eM->countAllResults();
        $nC = $cM->countAllResults();
        model(CatalogoActualizacionModel::class)->insert([
            'usuario_id' => $usuario['id'] ?? null, 'por' => $usuario['nombre'] ?? 'importador', 'modo' => $modo,
            'n_empleados' => $nE, 'n_cc' => $nC, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $db->transComplete();
        return ['empleados' => $nE, 'cc' => $nC, 'ok' => $db->transStatus()];
    }
}
