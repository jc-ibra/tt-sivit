<?= $this->extend('layout') ?>
<?= $this->section('contenido') ?>
<?php
$titulos = [
    'concepto' => 'Gasto por concepto', 'otros' => 'Gasto en otros · por concepto',
    'lf' => 'Local vs foráneo', 'dia' => 'Gasto por día · últimos 15 días con gasto',
    'coord' => 'Gasto por coordinador', 'cli' => 'Gasto por cliente (CC)',
    // los top 10 de ingenieros van hasta el final, abajo de todos
    'ing' => 'Quién gasta más · top 10 ingenieros', 'taxi' => 'Gasto en taxi · top 10 ingenieros',
];
$qs = fn (array $e) => '?' . http_build_query($e);
$hoy = date('Y-m-d');
$lunes = date('Y-m-d', strtotime('monday this week'));
$rapidos = [
    'Hoy' => [$hoy, $hoy], 'Esta semana' => [$lunes, $hoy], 'Este mes' => [date('Y-m-01'), $hoy], 'Este año' => [date('Y-01-01'), $hoy], 'Todo' => ['', ''],
];
?>
  <section id="view-kpi">
    <div class="sec">
      <h2>Periodo</h2>
      <form method="get" action="<?= site_url('indicadores') ?>">
      <div class="filters">
        <label class="f"><span>Desde</span><input type="date" id="k-desde" name="desde" value="<?= esc($desde, 'attr') ?>"></label>
        <label class="f"><span>Hasta</span><input type="date" id="k-hasta" name="hasta" value="<?= esc($hasta, 'attr') ?>"></label>
      </div></form>
      <div class="quick" id="k-quick">
        <?php foreach ($rapidos as $t => [$a, $z]): ?>
          <a class="chip" style="text-decoration:none" href="<?= site_url('indicadores') . ($a ? $qs(['desde' => $a, 'hasta' => $z]) : '') ?>" aria-pressed="<?= ($desde === $a && $hasta === $z) ? 'true' : 'false' ?>"><?= $t ?></a>
        <?php endforeach ?>
      </div>
    </div>
    <div class="tiles" id="k-tiles">
      <?php foreach ($tiles as $tl): [$l, $v, $s] = $tl; $w = $tl[3] ?? false; ?><div class="tile<?= $w ? ' warn' : '' ?>"><div class="l"><?= esc($l) ?></div><div class="v"><?= esc($v) ?></div><div class="s"><?= esc($s) ?></div></div><?php endforeach ?>
    </div>
    <div class="kgrid">
      <?php foreach ($titulos as $clave => $t):
          $items = $barras[$clave]; $top = $clave === 'dia' ? 15 : 10; $lista = array_slice($items, 0, $top);
          $max = $lista ? max(array_column($lista, 'v')) : 0; $totalG = array_sum(array_column($items, 'v')); ?>
      <div class="sec"><h2><?= esc($t) ?></h2><div class="bars">
        <?php if (! $lista): ?><div class="knone">Sin gasto en este periodo.</div><?php endif ?>
        <?php foreach ($lista as $x): $pct = $totalG ? (int) round($x['v'] / $totalG * 100) : 0; $ancho = $max ? number_format($x['v'] / $max * 100, 1, '.', '') : 0; ?>
          <div class="bar" title="<?= esc($x['k'], 'attr') ?>: <?= dinero($x['v']) ?> · <?= $pct ?>% del total · <?= (int) $x['n'] ?> solicitud<?= $x['n'] > 1 ? 'es' : '' ?>">
            <span class="n"><?= esc($x['k']) ?></span><span class="tr"><span class="fl" style="display:block;width:<?= $ancho ?>%"></span></span>
            <span class="val"><?= dinero($x['v']) ?><small><?= $pct ?>%</small></span></div>
        <?php endforeach ?>
      </div></div>
      <?php endforeach ?>
    </div>

    <?php
    // 9 · todos los ingenieros (mismos filtros tipo Excel que el listado del catálogo)
    $cuenta = static function (string $campo) use ($ingenieros): array {
        $c = array_count_values(array_map(static fn ($e) => (string) $e[$campo], $ingenieros));
        ksort($c, SORT_NATURAL);
        return $c;
    };
    $opciones = static function (array $c): string {
        $o = '<option value="">Todos</option>';
        foreach ($c as $v => $n) {
            $o .= '<option value="' . esc((string) $v, 'attr') . '">' . esc($v === '' ? '(vacío)' : (string) $v) . ' (' . $n . ')</option>';
        }
        return $o;
    };
    $porEstatus = $cuenta('estatus');
    $conGasto   = count(array_filter($ingenieros, static fn ($e) => $e['n'] > 0));
    $totalIng   = array_sum(array_column($ingenieros, 'total'));
    $esOp       = $yo['rol'] === 'operador';   // solo el operador puede borrar ingenieros
    ?>
    <div class="sec ing">
      <div class="ing-top"><h2>Todos los ingenieros · gasto en el periodo</h2></div>
      <div class="ing-bar">
        <label class="ing-q"><span class="sr">Buscar</span><input type="search" id="ing-q" placeholder="Buscar en todas las columnas…" autocomplete="off"></label>
        <div class="ing-chips" role="group" aria-label="Filtrar por estatus">
          <button type="button" class="chip" data-est="" aria-pressed="true">Todos <b><?= count($ingenieros) ?></b></button>
          <?php foreach ($porEstatus as $est => $n): ?><button type="button" class="chip" data-est="<?= esc((string) $est, 'attr') ?>" aria-pressed="false"><?= esc($est === '' ? '(vacío)' : (string) $est) ?> <b><?= $n ?></b></button><?php endforeach ?>
        </div>
        <label class="ing-ck"><input type="checkbox" id="ing-congasto"> Solo con gasto <b><?= $conGasto ?></b></label>
        <button type="button" class="btn ghost sm" id="ing-limpiar">Limpiar filtros</button>
        <?php if ($esOp): ?><button type="button" class="btn ghost sm danger-t" data-borrar="ingenieros" data-de="#ing-tabla" disabled>Borrar marcados (<b>0</b>)</button><?php endif ?>
      </div>
      <div class="ing-info" id="ing-info" aria-live="polite">Mostrando <?= count($ingenieros) ?> de <?= count($ingenieros) ?> ingenieros</div>
      <div class="tablebox ing-box">
        <table id="ing-tabla">
          <thead>
            <tr>
              <th class="num">#</th>
              <th><button type="button" class="ing-ord" data-col="1">Nombre<span class="arr"></span></button></th>
              <th class="num"><button type="button" class="ing-ord" data-col="2" data-num="1">Nómina<span class="arr"></span></button></th>
              <th><button type="button" class="ing-ord" data-col="3">Estatus<span class="arr"></span></button></th>
              <th><button type="button" class="ing-ord" data-col="4">Coordinador<span class="arr"></span></button></th>
              <th><button type="button" class="ing-ord" data-col="5">Regional<span class="arr"></span></button></th>
              <th><button type="button" class="ing-ord" data-col="6">Solicitó<span class="arr"></span></button></th>
              <th class="num"><button type="button" class="ing-ord" data-col="7" data-num="1">Solicitudes<span class="arr"></span></button></th>
              <th class="num"><button type="button" class="ing-ord" data-col="8" data-num="1">Taxi<span class="arr"></span></button></th>
              <th class="num"><button type="button" class="ing-ord" data-col="9" data-num="1" data-inicial="-1">Total<span class="arr">▼</span></button></th>
              <?php if ($esOp): ?><th class="ck"><input type="checkbox" data-del-todos title="Marcar todos los que se están mostrando" aria-label="Marcar todos los mostrados"></th><?php endif ?>
            </tr>
            <tr class="ing-filtros">
              <th></th>
              <th><input type="search" data-f="1" placeholder="Filtrar nombre" aria-label="Filtrar por nombre" autocomplete="off"></th>
              <th><input type="search" data-f="2" placeholder="Filtrar nómina" aria-label="Filtrar por nómina" autocomplete="off" inputmode="numeric"></th>
              <th><select data-f="3" data-exacto="1" aria-label="Filtrar por estatus"><?= $opciones($porEstatus) ?></select></th>
              <th><select data-f="4" data-exacto="1" aria-label="Filtrar por coordinador"><?= $opciones($cuenta('coordinador')) ?></select></th>
              <th><select data-f="5" data-exacto="1" aria-label="Filtrar por regional"><?= $opciones($cuenta('regional')) ?></select></th>
              <th><input type="search" data-f="6" placeholder="Filtrar perfil" aria-label="Filtrar por perfil que solicitó" autocomplete="off"></th>
              <th></th><th></th><th></th>
              <?php if ($esOp): ?><th></th><?php endif ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ingenieros as $i => $e): $rev = in_array($e['estatus'], config('Sivit')->estatusRevisar, true) || $e['estatus'] === 'EXTERNO'; ?>
            <tr data-gasto="<?= $e['n'] > 0 ? 1 : 0 ?>">
              <td class="num mono"><?= $i + 1 ?></td>
              <td><?= esc($e['nombre']) ?></td>
              <td class="num mono"><?= esc($e['nomina']) ?></td>
              <td data-v="<?= esc($e['estatus'], 'attr') ?>"><?php if ($e['estatus'] !== ''): ?><span class="pill <?= $rev ? 'rev' : 'ok' ?>"><?= esc($e['estatus']) ?></span><?php endif ?></td>
              <td><?= esc($e['coordinador']) ?></td>
              <td><?= esc($e['regional']) ?></td>
              <td><?= esc($e['perfiles']) ?></td>
              <td class="num mono" data-v="<?= (int) $e['n'] ?>"><?= $e['n'] ?: '' ?></td>
              <td class="num mono" data-v="<?= number_format($e['taxis'], 2, '.', '') ?>"><?= $e['taxis'] > 0 ? dinero($e['taxis']) : '' ?></td>
              <td class="num mono" data-v="<?= number_format($e['total'], 2, '.', '') ?>"><?= $e['total'] > 0 ? '<b>' . dinero($e['total']) . '</b>' : '<span class="ing-cero">$0.00</span>' ?></td>
              <?php if ($esOp): ?><td class="ck"><?php if ($e['id']): ?><input type="checkbox" data-del="<?= $e['id'] ?>" aria-label="Marcar <?= esc($e['nombre'], 'attr') ?>"><?php endif ?></td><?php endif ?>
            </tr>
            <?php endforeach ?>
            <tr class="ing-vacio" hidden><td colspan="<?= $esOp ? 11 : 10 ?>">Ningún ingeniero coincide con los filtros.</td></tr>
          </tbody>
          <tfoot><tr><td colspan="9">Total de los mostrados</td><td class="num mono"><b id="ing-suma" data-col="9"><?= dinero($totalIng) ?></b></td><?php if ($esOp): ?><td></td><?php endif ?></tr></tfoot>
        </table>
      </div>
    </div>
  </section>
<?= $this->endSection() ?>
