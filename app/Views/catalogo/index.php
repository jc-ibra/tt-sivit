<?= $this->extend('layout') ?>
<?= $this->section('contenido') ?>
<?php
$ch   = $previa['ch'] ?? null;
$plan = $previa['plan'] ?? null;
$paso = $previa ? 2 : 1;
$s    = static fn (int $n, string $uno, string $varios) => $n === 1 ? $uno : $varios;
$lista = static function (array $arr, callable $f, int $max = 8) {
    if (! $arr) {
        return '';
    }
    $o = '<ul>' . implode('', array_map($f, array_slice($arr, 0, $max)));
    if (count($arr) > $max) {
        $o .= '<li class="mas">… y ' . (count($arr) - $max) . ' más</li>';
    }
    return $o . '</ul>';
};
?>
  <section>
    <div class="sec up">
      <h2>Actualizar IDs y centros de costos</h2>
      <div class="up-now">Catálogo actual: <b><?= (int) $nEmp ?></b> IDs · <b><?= (int) $nCc ?></b> centros de costos<?= $ultima ? ' · actualizado ' . esc(fecha_hora($ultima['created_at'])) . ' por ' . esc($ultima['por']) : ' · versión original' ?></div>

      <ol class="up-steps" aria-label="Pasos">
        <li class="<?= $paso === 1 ? 'on' : 'done' ?>"><b>1</b><span>Prepara y sube tu archivo</span></li>
        <li class="<?= $paso === 2 ? 'on' : '' ?>"><b>2</b><span>Revisa la vista previa</span></li>
        <li><b>3</b><span>Confirma y guarda</span></li>
      </ol>

      <?php if (! empty($error)): ?><div class="notice" role="alert"><?= esc($error) ?></div><?php endif ?>

      <?php if (! $previa): ?>
      <div class="up-box up-info">
        <h3>¿Reemplaza o suma?</h3>
        <p>Ninguna de las dos cosas tal cual: el sistema <b>combina</b> tu archivo con lo que ya hay. Cada registro se busca por su clave:</p>
        <ul>
          <li><b>IDs</b> se identifican por el <b>número de nómina</b>. <b>Centros de costos</b>, por el <b>nombre del cliente</b>.</li>
          <li>Si ya existe → <b>se actualiza</b> con los datos del archivo (no se duplica).</li>
          <li>Si no existe → <b>se agrega</b>.</li>
          <li>Si está en el sistema pero <b>no viene en tu archivo</b> → <b>tú decides</b> en el paso 3: tratarlo como base completa (ingenieros a BAJA, clientes se quitan) o como solo cambios (se deja igual).</li>
        </ul>
        <p class="up-hint">Subir el archivo <b>no guarda nada</b>: primero verás exactamente qué va a cambiar. Las solicitudes ya capturadas nunca se modifican.</p>
      </div>

      <div class="up-box">
        <h3>Paso 1 · Descarga el formato, llénalo y súbelo</h3>
        <div class="up-btns">
          <a class="btn sm" href="<?= site_url('catalogo/formato') ?>">⬇ Formato con el catálogo actual</a>
          <a class="btn ghost sm" href="<?= site_url('catalogo/formato?vacio=1') ?>">⬇ Formato vacío</a>
        </div>
        <ul>
          <li><b>Con el catálogo actual</b> (recomendado): trae a todos los ingenieros y clientes de hoy. Edita, agrega o cambia a BAJA lo que necesites y súbelo completo.</li>
          <li><b>Vacío</b>: solo encabezados. Úsalo para subir únicamente altas o cambios puntuales.</li>
        </ul>
        <div class="up-cols">
          <div><b>Hoja «IDs»</b><span>Obligatorias: <code>NOMBRE</code>, <code>NUMERO DE NOMINA</code></span><span>Opcionales: <code>ESTATUS</code>, <code>COORDINADOR</code>, <code>REGIONAL</code></span></div>
          <div><b>Hoja «Centros de costos»</b><span>Obligatorias: <code>CLIENTE</code>, <code>CC</code></span></div>
        </div>
        <p class="up-hint">No cambies los encabezados. Si no necesitas una hoja, déjala solo con encabezados: esa parte del catálogo no se toca. El formato trae una hoja de instrucciones que se ignora al subirlo.</p>
        <form method="post" action="<?= site_url('catalogo/previa') ?>" enctype="multipart/form-data" class="up-row">
          <?= csrf_field() ?>
          <input type="file" name="archivos[]" accept=".xlsx,.xls,.xlsm,.csv,text/csv" multiple required>
          <button type="submit" class="btn">Subir y ver vista previa →</button>
        </form>
      </div>
      <?php else: ?>
      <div class="up-box up-info">
        <h3>Paso 2 · Vista previa</h3>
        <p><b>Todavía no se ha guardado nada.</b> Esto es lo que pasaría al guardar. Si algo no se ve bien, cancela, corrige tu archivo y vuelve a subirlo.</p>
      </div>

      <form method="post" action="<?= site_url('catalogo/aplicar') ?>">
        <?= csrf_field() ?>
        <?php if ($plan['emp'] !== null): ?>
        <div class="up-box"><h3>IDs (ingenieros) · <?= count($plan['emp']) ?> en el archivo</h3>
          <div class="up-k"><span class="g"><?= count($ch['nuevos']) ?><small>se agregan</small></span><span class="b"><?= count($ch['cambios']) ?><small>se actualizan</small></span><span class="r"><?= $ch['bajas'] ?><small>el archivo pone en BAJA</small></span><span class="n"><?= count($plan['emp']) - count($ch['nuevos']) - count($ch['cambios']) ?><small>sin cambio</small></span></div>
          <?= $lista($ch['nuevos'], fn ($e) => '<li><b>Nuevo:</b> ' . esc($e['nombre']) . ' - ' . esc($e['nomina']) . ' (' . esc($e['estatus']) . ')</li>') ?>
          <?= $lista($ch['cambios'], fn ($x) => '<li><b>' . esc($x['e']['nombre']) . '</b>: ' . esc(implode(' · ', $x['d'])) . '</li>') ?>
          <?php if ($ch['faltan']): ?>
          <p class="warn-t"><?= count($ch['faltan']) ?> <?= $s(count($ch['faltan']), 'ingeniero activo del sistema no viene', 'ingenieros activos del sistema no vienen') ?> en el archivo (ver paso 3):</p>
          <?= $lista($ch['faltan'], fn ($e) => '<li>' . esc($e['nombre']) . ' - ' . esc($e['nomina']) . '</li>') ?>
          <?php endif ?></div>
        <?php else: ?>
        <div class="up-box"><h3>IDs (ingenieros)</h3><p class="up-hint">Tu archivo no trae hoja de IDs: los ingenieros se quedan como están.</p></div>
        <?php endif ?>

        <?php if ($plan['cc'] !== null): ?>
        <div class="up-box"><h3>Centros de costos · <?= count($plan['cc']) ?> en el archivo</h3>
          <div class="up-k"><span class="g"><?= count($ch['ccNuevos']) ?><small>se agregan</small></span><span class="b"><?= count($ch['ccCambios']) ?><small>cambian de CC</small></span><span class="n"><?= count($plan['cc']) - count($ch['ccNuevos']) - count($ch['ccCambios']) ?><small>sin cambio</small></span></div>
          <?= $lista($ch['ccNuevos'], fn ($x) => '<li><b>Nuevo:</b> ' . esc($x[0]) . ' · CC ' . esc($x[1]) . '</li>') ?>
          <?= $lista($ch['ccCambios'], fn ($x) => '<li><b>' . esc($x[0]) . '</b>: CC ' . esc($x[1]) . ' → ' . esc($x[2]) . '</li>') ?>
          <?php if ($ch['ccFaltan']): ?>
          <p class="warn-t"><?= count($ch['ccFaltan']) ?> <?= $s(count($ch['ccFaltan']), 'cliente del sistema no viene', 'clientes del sistema no vienen') ?> en el archivo (ver paso 3):</p>
          <?= $lista($ch['ccFaltan'], fn ($c) => '<li>' . esc($c) . '</li>') ?>
          <?php endif ?></div>
        <?php else: ?>
        <div class="up-box"><h3>Centros de costos</h3><p class="up-hint">Tu archivo no trae hoja de centros de costos: se quedan como están.</p></div>
        <?php endif ?>

        <?php if ($plan['repetidos']): ?><p class="up-hint">Había <?= $plan['repetidos'] ?> <?= $s($plan['repetidos'], 'registro repetido', 'registros repetidos') ?> en el archivo: se tomó el último de cada uno.<?php if (! empty($plan['repetidosLista'])): ?> Se quedan fuera: <?= esc(implode(', ', array_slice($plan['repetidosLista'], 0, 10))) ?><?= count($plan['repetidosLista']) > 10 ? '…' : '' ?>. Revisa que cada uno tenga su propio número de empleado.<?php endif ?></p><?php endif ?>
        <?php if ($plan['ignoradas']): ?><p class="up-hint">Se ignoró (no se reconocieron los encabezados): <?= esc(implode(', ', $plan['ignoradas'])) ?>.</p><?php endif ?>

        <div class="up-box">
          <h3>Paso 3 · Confirma</h3>
          <?php if ($ch['faltan'] || $ch['ccFaltan']): ?>
          <p>¿Qué hago con lo que está en el sistema pero <b>no viene</b> en tu archivo?</p>
          <label class="rad"><input type="radio" name="modo" value="parcial" checked> <span><b>Solo son cambios</b> (recomendado si subiste una lista parcial): agrego y actualizo lo que viene; lo demás se queda igual.</span></label>
          <label class="rad"><input type="radio" name="modo" value="completo"> <span><b>Es mi base completa</b>: <?= $ch['faltan'] ? count($ch['faltan']) . ' ' . $s(count($ch['faltan']), 'ingeniero pasa', 'ingenieros pasan') . ' a <b>BAJA</b> (no se borran)' : '' ?><?= $ch['faltan'] && $ch['ccFaltan'] ? ' y ' : '' ?><?= $ch['ccFaltan'] ? count($ch['ccFaltan']) . ' ' . $s(count($ch['ccFaltan']), 'cliente se quita', 'clientes se quitan') . ' de la lista' : '' ?>.</span></label>
          <?php else: ?>
          <p>Tu archivo trae todo lo que ya está en el sistema, así que solo se agregará y actualizará lo indicado arriba.</p>
          <?php endif ?>
          <p class="up-hint"><b>Las solicitudes ya capturadas no cambian:</b> guardan los datos con que se registraron. Si algo falla al guardar, no se aplica ningún cambio.</p>
          <div class="up-btns end"><a class="btn ghost" href="<?= site_url('catalogo') ?>">Cancelar (no guardar)</a><button type="submit" class="btn ok-b">✓ Guardar cambios</button></div>
        </div>
      </form>
      <?php endif ?>
    </div>
  </section>

  <?php if (! $previa):
    // opciones de los filtros con su cantidad, como el autofiltro de Excel
    $cuenta = static function (string $campo) use ($empleados): array {
        $c = array_count_values(array_map(static fn ($e) => (string) $e[$campo], $empleados));
        ksort($c, SORT_NATURAL);
        return $c;
    };
    $porEstatus = $cuenta('estatus');
    $opciones   = static function (array $c): string {
        $o = '<option value="">Todos</option>';
        foreach ($c as $v => $n) {
            $o .= '<option value="' . esc((string) $v, 'attr') . '">' . esc($v === '' ? '(vacío)' : (string) $v) . ' (' . $n . ')</option>';
        }
        return $o;
    };
  ?>
  <section>
    <div class="sec ing">
      <div class="ing-top">
        <h2>Ingenieros del catálogo</h2>
        <a class="btn ghost sm" href="<?= site_url('catalogo/formato') ?>">⬇ Descargar en Excel</a>
      </div>
      <div class="ing-bar">
        <label class="ing-q"><span class="sr">Buscar</span><input type="search" id="ing-q" placeholder="Buscar en todas las columnas…" autocomplete="off"></label>
        <div class="ing-chips" role="group" aria-label="Filtrar por estatus">
          <button type="button" class="chip" data-est="" aria-pressed="true">Todos <b><?= count($empleados) ?></b></button>
          <?php foreach ($porEstatus as $est => $n): ?><button type="button" class="chip" data-est="<?= esc((string) $est, 'attr') ?>" aria-pressed="false"><?= esc($est === '' ? '(vacío)' : (string) $est) ?> <b><?= $n ?></b></button><?php endforeach ?>
        </div>
        <button type="button" class="btn ghost sm" id="ing-limpiar">Limpiar filtros</button>
        <button type="button" class="btn ghost sm danger-t" data-borrar="ingenieros" data-de="#ing-tabla" disabled>Borrar marcados (<b>0</b>)</button>
      </div>
      <div class="ing-info" id="ing-info" aria-live="polite">Mostrando <?= count($empleados) ?> de <?= count($empleados) ?> ingenieros</div>
      <div class="tablebox ing-box">
        <table id="ing-tabla">
          <thead>
            <tr>
              <th class="num">#</th>
              <th><button type="button" class="ing-ord" data-col="1">Nombre<span class="arr">▲</span></button></th>
              <th class="num"><button type="button" class="ing-ord" data-col="2" data-num="1">Nómina<span class="arr"></span></button></th>
              <th><button type="button" class="ing-ord" data-col="3">Estatus<span class="arr"></span></button></th>
              <th><button type="button" class="ing-ord" data-col="4">Coordinador<span class="arr"></span></button></th>
              <th><button type="button" class="ing-ord" data-col="5">Regional<span class="arr"></span></button></th>
              <th class="ck"><input type="checkbox" data-del-todos title="Marcar todos los que se están mostrando" aria-label="Marcar todos los mostrados"></th>
            </tr>
            <tr class="ing-filtros">
              <th></th>
              <th><input type="search" data-f="1" placeholder="Filtrar nombre" aria-label="Filtrar por nombre" autocomplete="off"></th>
              <th><input type="search" data-f="2" placeholder="Filtrar nómina" aria-label="Filtrar por nómina" autocomplete="off" inputmode="numeric"></th>
              <th><select data-f="3" data-exacto="1" aria-label="Filtrar por estatus"><?= $opciones($porEstatus) ?></select></th>
              <th><select data-f="4" data-exacto="1" aria-label="Filtrar por coordinador"><?= $opciones($cuenta('coordinador')) ?></select></th>
              <th><select data-f="5" data-exacto="1" aria-label="Filtrar por regional"><?= $opciones($cuenta('regional')) ?></select></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($empleados as $i => $e): $rev = in_array($e['estatus'], config('Sivit')->estatusRevisar, true); ?>
            <tr>
              <td class="num mono"><?= $i + 1 ?></td>
              <td><?= esc($e['nombre']) ?></td>
              <td class="num mono"><?= esc($e['nomina']) ?></td>
              <td data-v="<?= esc($e['estatus'], 'attr') ?>"><?php if ($e['estatus'] !== ''): ?><span class="pill <?= $rev ? 'rev' : 'ok' ?>"><?= esc($e['estatus']) ?></span><?php endif ?></td>
              <td><?= esc($e['coordinador']) ?></td>
              <td><?= esc($e['regional']) ?></td>
              <td class="ck"><input type="checkbox" data-del="<?= (int) $e['id'] ?>" aria-label="Marcar <?= esc($e['nombre'], 'attr') ?>"></td>
            </tr>
            <?php endforeach ?>
            <tr class="ing-vacio" hidden><td colspan="7">Ningún ingeniero coincide con los filtros.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
  <?php endif ?>
<?= $this->endSection() ?>
