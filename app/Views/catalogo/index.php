<?= $this->extend('layout') ?>
<?= $this->section('contenido') ?>
<?php $ch = $previa['ch'] ?? null; $plan = $previa['plan'] ?? null; ?>
  <section>
    <div class="sec up">
      <h2>Actualizar IDs y centros de costos</h2>
      <p class="rl">Sube tu base de datos en <b>Excel (.xlsx)</b> o <b>CSV</b>. Puede ser un archivo con dos hojas (IDs y centros de costos) o dos archivos. El sistema reconoce cada hoja por sus encabezados.</p>
      <div class="up-now">Catálogo actual: <b><?= (int) $nEmp ?></b> IDs · <b><?= (int) $nCc ?></b> centros de costos<?= $ultima ? ' · actualizado ' . esc(fecha_hora($ultima['created_at'])) . ' por ' . esc($ultima['por']) : ' · versión original' ?></div>
      <?php if (! empty($error)): ?><div class="notice" role="alert"><?= esc($error) ?></div><?php endif ?>
      <form method="post" action="<?= site_url('catalogo/previa') ?>" enctype="multipart/form-data" class="up-row">
        <?= csrf_field() ?>
        <input type="file" name="archivos[]" accept=".xlsx,.xls,.xlsm,.csv,text/csv" multiple required>
        <button type="submit" class="btn">⬆ Subir actualización de IDs y centros de costos</button>
      </form>
      <div class="mbtns"><a class="btn ghost sm" href="<?= site_url('catalogo/csv/ids') ?>" style="text-decoration:none">Descargar IDs actuales</a><a class="btn ghost sm" href="<?= site_url('catalogo/csv/cc') ?>" style="text-decoration:none">Descargar CC actuales</a></div>

      <?php if ($previa): ?>
      <form method="post" action="<?= site_url('catalogo/aplicar') ?>">
        <?= csrf_field() ?>
        <?php $lista = function (array $arr, callable $f, int $max = 8) { if (! $arr) return ''; $o = '<ul>' . implode('', array_map($f, array_slice($arr, 0, $max))); if (count($arr) > $max) $o .= '<li class="mas">… y ' . (count($arr) - $max) . ' más</li>'; return $o . '</ul>'; }; ?>
        <?php if ($plan['emp'] !== null): ?>
        <div class="up-box"><h3>IDs (ingenieros) · <?= count($plan['emp']) ?> en el archivo</h3>
          <div class="up-k"><span class="g"><?= count($ch['nuevos']) ?><small>nuevos</small></span><span class="b"><?= count($ch['cambios']) ?><small>con cambios</small></span><span class="r"><?= $ch['bajas'] ?><small>pasan a BAJA</small></span><span class="n"><?= count($plan['emp']) - count($ch['nuevos']) - count($ch['cambios']) ?><small>sin cambio</small></span></div>
          <?= $lista($ch['nuevos'], fn ($e) => '<li><b>Nuevo:</b> ' . esc($e['nombre']) . ' - ' . esc($e['nomina']) . ' (' . esc($e['estatus']) . ')</li>') ?>
          <?= $lista($ch['cambios'], fn ($x) => '<li><b>' . esc($x['e']['nombre']) . '</b>: ' . esc(implode(' · ', $x['d'])) . '</li>') ?>
          <?php if ($ch['faltan']): ?><p class="warn-t"><?= count($ch['faltan']) ?> ingeniero<?= count($ch['faltan']) > 1 ? 's' : '' ?> del catálogo actual no viene<?= count($ch['faltan']) > 1 ? 'n' : '' ?> en el archivo.</p><?php endif ?></div>
        <?php endif ?>
        <?php if ($plan['cc'] !== null): ?>
        <div class="up-box"><h3>Centros de costos · <?= count($plan['cc']) ?> en el archivo</h3>
          <div class="up-k"><span class="g"><?= count($ch['ccNuevos']) ?><small>nuevos</small></span><span class="b"><?= count($ch['ccCambios']) ?><small>cambian de CC</small></span><span class="n"><?= count($plan['cc']) - count($ch['ccNuevos']) - count($ch['ccCambios']) ?><small>sin cambio</small></span></div>
          <?= $lista($ch['ccNuevos'], fn ($x) => '<li><b>Nuevo:</b> ' . esc($x[0]) . ' · CC ' . esc($x[1]) . '</li>') ?>
          <?= $lista($ch['ccCambios'], fn ($x) => '<li><b>' . esc($x[0]) . '</b>: CC ' . esc($x[1]) . ' → ' . esc($x[2]) . '</li>') ?>
          <?php if ($ch['ccFaltan']): ?><p class="warn-t"><?= count($ch['ccFaltan']) ?> cliente<?= count($ch['ccFaltan']) > 1 ? 's' : '' ?> actual<?= count($ch['ccFaltan']) > 1 ? 'es' : '' ?> no viene<?= count($ch['ccFaltan']) > 1 ? 'n' : '' ?> en el archivo.</p><?php endif ?></div>
        <?php endif ?>
        <?php if ($ch['faltan'] || $ch['ccFaltan']): ?>
        <div class="up-box"><h3>Lo que no viene en el archivo</h3>
          <label class="rad"><input type="radio" name="modo" value="completo" checked> <span><b>Es mi base completa:</b> los ingenieros que no vienen se marcan <b>BAJA</b> y los clientes que no vienen se quitan de la lista.</span></label>
          <label class="rad"><input type="radio" name="modo" value="parcial"> <span><b>Solo son cambios:</b> agregar y actualizar lo que viene, sin tocar lo demás.</span></label></div>
        <?php endif ?>
        <?php if ($plan['repetidos']): ?><p class="rl">Había <?= $plan['repetidos'] ?> registro<?= $plan['repetidos'] > 1 ? 's' : '' ?> repetido<?= $plan['repetidos'] > 1 ? 's' : '' ?> en el archivo: se tomó el último.</p><?php endif ?>
        <?php if ($plan['ignoradas']): ?><p class="rl">Se ignoró (no se reconocieron los encabezados): <?= esc(implode(', ', $plan['ignoradas'])) ?>.</p><?php endif ?>
        <p class="rl"><b>Las solicitudes ya capturadas no cambian:</b> guardan los datos con que se registraron. Nada se duplica: cada ID y cada cliente se reemplaza por su versión nueva.</p>
        <div class="mbtns"><a class="btn ghost" href="<?= site_url('catalogo') ?>" style="text-decoration:none">Cancelar</a><button type="submit" class="btn ok-b">✓ Guardar</button></div>
      </form>
      <?php endif ?>
    </div>
  </section>
<?= $this->endSection() ?>
