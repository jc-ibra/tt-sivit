<?= $this->extend('layout') ?>
<?= $this->section('contenido') ?>
<?php
$titulos = [
    'concepto' => 'Gasto por concepto', 'ing' => 'Quién gasta más · top 10 ingenieros', 'taxi' => 'Gasto en taxi · top 10 ingenieros',
    'coord' => 'Gasto por coordinador', 'cli' => 'Gasto por cliente (CC)', 'otros' => 'Gasto en otros · por concepto',
    'lf' => 'Local vs foráneo', 'dia' => 'Gasto por día · últimos 15 días con gasto',
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
  </section>
<?= $this->endSection() ?>
