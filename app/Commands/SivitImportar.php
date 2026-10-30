<?php

namespace App\Commands;

use App\Libraries\CatalogoImport;
use App\Libraries\SolicitudReglas;
use App\Models\SolicitudModel;
use App\Models\UsuarioModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark sivit:importar --carpeta ../SIVIT_datos --respaldo SIVIT_respaldo.json --excel ../SIVIT_DATA.xlsx
 * Idempotente: se puede correr varias veces y combinar fuentes (upsert por legacy_id / nómina / cliente).
 */
class SivitImportar extends BaseCommand
{
    protected $group       = 'SIVIT';
    protected $name        = 'sivit:importar';
    protected $description = 'Importa usuarios, solicitudes y catálogo desde la carpeta SIVIT_datos, un respaldo .json y/o el Excel.';
    protected $usage       = 'sivit:importar [--carpeta=ruta] [--respaldo=archivo.json] [--excel=archivo.xlsx]';
    protected $options     = [
        '--carpeta'  => 'Carpeta SIVIT_datos (usuarios/, solicitudes/, catalogo.json)',
        '--respaldo' => 'Respaldo SIVIT_respaldo_<fecha>.json (puede repetirse separado por comas)',
        '--excel'    => 'Excel/CSV de IDs y centros de costos (SIVIT_DATA.xlsx)',
    ];

    private array $cuenta = ['usuarios' => 0, 'solicitudes' => 0, 'omitidas' => 0, 'empleados' => 0, 'cc' => 0];
    private array $avisos = [];
    private array $usuarioPorLegacy = [];

    public function run(array $params)
    {
        // spark corre desde public/: las rutas relativas se toman desde la raíz del proyecto
        $carpeta  = $this->ruta(CLI::getOption('carpeta'));
        $respaldo = implode(',', array_map([$this, 'ruta'], array_filter(explode(',', (string) CLI::getOption('respaldo')))));
        $excel    = $this->ruta(CLI::getOption('excel'));
        if (! $carpeta && ! $respaldo && ! $excel) {
            CLI::error('Indica al menos --carpeta, --respaldo o --excel.');
            return EXIT_USER_INPUT;
        }

        $db = db_connect();
        $db->transStart();
        $users = [];
        $rows  = [];
        $cat   = null;

        if ($carpeta) {
            $carpeta = rtrim($carpeta, '/');
            if (! is_dir($carpeta)) {
                CLI::error("No existe la carpeta $carpeta");
                return EXIT_USER_INPUT;
            }
            foreach (glob("$carpeta/usuarios/*.json") ?: [] as $f) {
                $o = $this->json($f);
                if ($o && empty($o['_borrado'])) {
                    $users[] = $o;
                }
            }
            foreach (glob("$carpeta/solicitudes/*.json") ?: [] as $f) {
                $o = $this->json($f);
                if ($o && empty($o['_borrado'])) {
                    $rows[] = $o;
                } elseif ($o) {
                    $this->cuenta['omitidas']++;
                }
            }
            $cat = $this->json("$carpeta/catalogo.json") ?: null;
        }
        foreach (array_filter(explode(',', (string) $respaldo)) as $f) {
            $d = $this->json(trim($f));
            if (! $d || ($d['app'] ?? '') !== 'SIVIT') {
                CLI::error("$f no es un respaldo de SIVIT");
                $db->transRollback();
                return EXIT_USER_INPUT;
            }
            $users = array_merge($users, $d['users'] ?? []);
            $rows  = array_merge($rows, $d['rows'] ?? []);
            $cat   = $d['catalogo'] ?? $cat;
        }

        $this->importarUsuarios($users);
        if ($cat) {
            $this->importarCatalogoJson($cat);
        }
        if ($excel) {
            $this->importarExcel($excel);
        }
        $this->importarSolicitudes($rows);
        $db->transComplete();

        if (! $db->transStatus()) {
            CLI::error('Falló la importación; no se guardó nada.');
            return EXIT_ERROR;
        }
        CLI::write('Importación terminada:', 'green');
        foreach ($this->cuenta as $k => $v) {
            CLI::write(sprintf('  %-12s %d', $k, $v));
        }
        foreach (array_unique($this->avisos) as $a) {
            CLI::write("  ! $a", 'yellow');
        }
        return EXIT_SUCCESS;
    }

    private function ruta($p): ?string
    {
        if (! is_string($p) || $p === '') {
            return null;
        }
        return str_starts_with($p, '/') ? $p : rtrim(ROOTPATH, '/') . '/' . $p;
    }

    private function json(string $f): ?array
    {
        $t = @file_get_contents($f);
        $d = $t === false ? null : json_decode($t, true);
        return is_array($d) ? $d : null;
    }

    private function fecha($ms): ?string
    {
        return is_numeric($ms) && $ms > 0 ? date('Y-m-d H:i:s', (int) ($ms / 1000)) : null;
    }

    private function importarUsuarios(array $users): void
    {
        $m = model(UsuarioModel::class);
        // si un mismo perfil viene de varias fuentes, se queda el último
        $porId = [];
        foreach ($users as $u) {
            if (! empty($u['id'])) {
                $porId[$u['id']] = $u;
            }
        }
        foreach ($porId as $legacy => $u) {
            $rol = in_array($u['rol'] ?? '', config('Sivit')->roles, true) ? $u['rol'] : 'coordinador';
            $foto = null;
            if (! empty($u['foto']) && preg_match('#^data:image/\w+;base64,(.+)$#', $u['foto'], $mm)) {
                $foto = base64_decode($mm[1], true) !== false ? $mm[1] : null;   // se guarda en base64
            }
            $datos = [
                'legacy_id' => $legacy, 'nombre' => mb_strtoupper(trim($u['nombre'] ?? '')), 'rol' => $rol,
                'legacy_hash' => $u['hash'] ?? null, 'legacy_salt' => $u['salt'] ?? null,
                'foto' => $foto, 'color' => (int) ($u['color'] ?? 0), 'tema' => $u['tema'] ?? null,
            ];
            $ex = $m->withDeleted()->where('legacy_id', $legacy)->first();
            if (! $ex && $rol === 'operador') {
                $ex = $m->withDeleted()->where('rol', 'operador')->first();   // el que creó el seeder
            }
            if ($ex) {
                // no pisar una contraseña ya migrada con el hash viejo
                if (! empty($ex['password_hash'])) {
                    unset($datos['legacy_hash'], $datos['legacy_salt']);
                }
                $m->update($ex['id'], $datos);
                $id = $ex['id'];
            } else {
                $id = $m->insert($datos);
            }
            $this->usuarioPorLegacy[$legacy] = (int) $id;
            $this->cuenta['usuarios']++;
        }
    }

    private function importarCatalogoJson(array $c): void
    {
        $emp = [];
        foreach ($c['emp'] ?? [] as $r) {
            $num = SolicitudReglas::limpiarNomina($r[1] ?? '');
            if ($num !== '') {
                $emp[$num] = ['nomina' => $num, 'nombre' => SolicitudReglas::limpiar($r[0]), 'estatus' => SolicitudReglas::limpiar($r[2] ?? ''), 'coordinador' => SolicitudReglas::limpiar($r[3] ?? ''), 'regional' => SolicitudReglas::limpiar($r[4] ?? '')];
            }
        }
        $cc = [];
        foreach ($c['cc'] ?? [] as $r) {
            $cc[SolicitudReglas::limpiar($r[0])] = SolicitudReglas::limpiar($r[1]);
        }
        $this->aplicarPlan(['emp' => $emp ?: null, 'cc' => $cc ?: null]);
    }

    private function importarExcel(string $ruta): void
    {
        if (! is_file($ruta)) {
            CLI::error("No existe $ruta");
            return;
        }
        $imp  = new CatalogoImport();
        $plan = $imp->plan($imp->leerTablas($ruta, basename($ruta)));
        foreach ($plan['ignoradas'] as $i) {
            $this->avisos[] = "Hoja ignorada (encabezados no reconocidos): $i";
        }
        $this->aplicarPlan($plan);
    }

    private function aplicarPlan(array $plan): void
    {
        $plan += ['repetidos' => 0, 'ignoradas' => []];
        // al importar solo se agrega y actualiza: nunca marca BAJA ni quita clientes
        $r = (new CatalogoImport())->aplicar($plan, 'parcial', null);
        $this->cuenta['empleados'] = $r['empleados'];
        $this->cuenta['cc']        = $r['cc'];
    }

    private function importarSolicitudes(array $rows): void
    {
        $m = model(SolicitudModel::class);
        $porId = [];
        foreach ($rows as $r) {
            if (empty($r['id'])) {
                continue;
            }
            $prev = $porId[$r['id']] ?? null;
            // si viene de varias fuentes gana la edición/creación más reciente
            if (! $prev || ($r['editadoEn'] ?? $r['creadoEn'] ?? 0) >= ($prev['editadoEn'] ?? $prev['creadoEn'] ?? 0)) {
                $porId[$r['id']] = $r;
            }
        }
        $empleados = array_column(model(\App\Models\EmpleadoModel::class)->findAll(), 'nomina');
        foreach ($porId as $legacy => $r) {
            $uid = isset($r['usuarioId'], $this->usuarioPorLegacy[$r['usuarioId']]) ? $this->usuarioPorLegacy[$r['usuarioId']]
                 : (($u = model(UsuarioModel::class)->withDeleted()->where('legacy_id', $r['usuarioId'] ?? '')->first()) ? (int) $u['id'] : null);
            if (! $uid) {
                $this->avisos[] = 'Solicitudes cuyo usuario ya no existe: se importaron sin dueño (solo las ve el operador/viático).';
            }
            $total = round((float) ($r['total'] ?? 0), 2);
            $dato = [
                'legacy_id' => $legacy, 'usuario_id' => $uid, 'fecha' => $r['fecha'] ?? date('Y-m-d'),
                'nomina' => SolicitudReglas::limpiarNomina($r['nomina'] ?? ''), 'nombre' => $r['nombre'] ?? '', 'coordinador' => $r['coordinador'] ?? '',
                'regional' => $r['regional'] ?? '', 'estatus' => $r['estatus'] ?? '', 'tipo' => $r['tipo'] ?? 'EXTERNO (PROVEEDOR)',
                'folio_gp' => $r['folioGP'] ?? '', 'cliente' => $r['cliente'] ?? '', 'cc' => (string) ($r['cc'] ?? 'SIN CC'),
                'lf' => $r['lf'] ?? 'L', 'traslado' => $r['traslado'] ?? '',
                'tran_publico' => (float) ($r['tranPublico'] ?? 0), 'taxis' => (float) ($r['taxis'] ?? 0), 'just_taxi' => $r['justTaxi'] ?? '',
                'autobus' => (float) ($r['autobus'] ?? 0), 'comida' => (float) ($r['comida'] ?? 0), 'hotel' => (float) ($r['hotel'] ?? 0),
                'otros' => (float) ($r['otros'] ?? 0), 'concepto_otros' => $r['conceptoOtros'] ?? '', 'comentarios' => $r['comentarios'] ?? '',
                'total' => $total, 'ok' => $r['ok'] ?? 'REVISAR',
                'enviado_en' => $this->fecha($r['enviadoEn'] ?? null), 'enviado_a' => $r['enviadoA'] ?? null,
                'dep_completo' => in_array($r['depCompleto'] ?? null, ['si', 'no'], true) ? $r['depCompleto'] : null,
                'real_dep' => isset($r['realDep']) && $r['realDep'] !== '' ? (float) $r['realDep'] : null,
                'real_dep_por' => $r['realDepPor'] ?? null, 'real_dep_en' => $this->fecha($r['realDepEn'] ?? null),
                'creado_por' => $r['creadoPor'] ?? '', 'editado_por' => $r['editadoPor'] ?? null,
            ];
            if (! in_array($dato['nomina'], $empleados, true)) {
                $this->avisos[] = 'Hay solicitudes de nóminas que no están en el catálogo (externos/proveedores).';
            }
            $ex = $m->withDeleted()->where('legacy_id', $legacy)->first();
            if ($ex) {
                $m->update($ex['id'], $dato);
            } else {
                $m->insert($dato);
            }
            // conserva las fechas originales de captura/edición
            $m->builder()->where('legacy_id', $legacy)->update(array_filter([
                'created_at' => $this->fecha($r['creadoEn'] ?? null), 'updated_at' => $this->fecha($r['editadoEn'] ?? $r['creadoEn'] ?? null),
            ]));
            $this->cuenta['solicitudes']++;
        }
    }
}
