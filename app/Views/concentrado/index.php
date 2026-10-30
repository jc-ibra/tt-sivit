<?= $this->extend('layout') ?>
<?= $this->section('contenido') ?>
<?php
$verUsuario = in_array($yo['rol'], ['operador', 'viatico'], true);
$cols = columnas_solicitud($verUsuario);
$puedeBorrar = $yo['rol'] !== 'viatico';
$yel = ['lf', 'traslado', 'tranPublico', 'taxis', 'autobus', 'comida', 'hotel', 'otros', 'comentarios'];
$sum = fn (string $c) => array_sum(array_map(fn ($r) => (float) $r[$c], $list));
$si = count(array_filter($list, fn ($r) => \App\Models\SolicitudModel::depEstado($r) === 'si'));
$no = count(array_filter($list, fn ($r) => \App\Models\SolicitudModel::depEstado($r) === 'no'));
$con = array_filter($list, fn ($r) => $r['real_dep'] !== null && $r['real_dep'] !== '');
$st = array_sum(array_map(fn ($r) => (float) $r['total'], $con));
$sd = array_sum(array_map(fn ($r) => (float) $r['real_dep'], $con));
$pp = $st ? $sd / $st * 100 : null;
$pctClase = fn ($p) => $p === null ? '' : ($p < 99.95 ? 'bajo' : ($p > 100.05 ? 'alto' : 'exacto'));
$pctTxt = fn ($p) => $p === null ? '' : rtrim(rtrim(number_format(round($p, 1), 1, '.', ','), '0'), '.') . '%';
$qs = fn (array $extra) => '?' . http_build_query(array_filter($filtros + $extra, fn ($x) => $x !== '' && $x !== null));
$hayFiltros = (bool) array_filter($filtros);
$faltan = count(array_filter($list, fn ($r) => \App\Models\SolicitudModel::depEstado($r) === 'no' && ($r['real_dep'] === null || $r['real_dep'] === '')));
?>
  <section id="view-list">
    <div class="sec">
      <h2>Filtros</h2>
      <form method="get" action="<?= site_url('concentrado') ?>" id="filtros">
      <div class="filters">
        <label class="f"><span>Desde</span><input type="date" id="f-desde" name="desde" value="<?= esc($filtros['desde'], 'attr') ?>"></label>
        <label class="f"><span>Hasta</span><input type="date" id="f-hasta" name="hasta" value="<?= esc($filtros['hasta'], 'attr') ?>"></label>
        <label class="f"><span>Cliente</span><select id="f-cliente" name="cliente"><option value="">Todos</option><?php foreach ($clientes as $c): ?><option<?= $filtros['cliente'] === $c ? ' selected' : '' ?>><?= esc($c) ?></option><?php endforeach ?></select></label>
        <label class="f"><span>Coordinador</span><select id="f-coord" name="coord"><option value="">Todos</option><?php foreach ($coordinadores as $c): ?><option<?= $filtros['coord'] === $c ? ' selected' : '' ?>><?= esc($c) ?></option><?php endforeach ?></select></label>
        <label class="f"><span>L/F</span><select id="f-lf" name="lf"><option value="">Todos</option><option<?= $filtros['lf'] === 'L' ? ' selected' : '' ?>>L</option><option<?= $filtros['lf'] === 'F' ? ' selected' : '' ?>>F</option></select></label>
        <label class="f"><span>OK</span><select id="f-ok" name="ok"><option value="">Todos</option><option<?= $filtros['ok'] === 'OK' ? ' selected' : '' ?>>OK</option><option<?= $filtros['ok'] === 'REVISAR' ? ' selected' : '' ?>>REVISAR</option></select></label>
        <label class="f" style="grid-column:span 2"><span>Buscar</span><input id="f-q" name="q" value="<?= esc($filtros['q'], 'attr') ?>" placeholder="Nombre, nómina, folio, comentario…"></label>
      </div>
      <input type="hidden" name="orden" value="<?= esc($orden, 'attr') ?>"><input type="hidden" name="dir" value="<?= $dir > 0 ? 'asc' : 'desc' ?>">
      <div class="quick" id="quick">
        <button type="button" class="chip" data-q="hoy" aria-pressed="false">Hoy</button>
        <button type="button" class="chip" data-q="ayer" aria-pressed="false">Ayer</button>
        <button type="button" class="chip" data-q="semana" aria-pressed="false">Esta semana</button>
        <button type="button" class="chip" data-q="mes" aria-pressed="false">Este mes</button>
        <a class="chip" href="<?= site_url('concentrado') ?>" style="text-decoration:none">Quitar filtros</a>
      </div>
      </form>
    </div>
    <div class="sec mail" style="display:none" aria-hidden="true"><input id="mail-to" type="text" value="<?= esc($correo, 'attr') ?>"></div>
    <div class="summary">
      <div><span>Solicitudes</span> <b id="s-n"><?= count($list) ?></b></div>
      <div><span>Total viáticos</span> <b id="s-t"><?= dinero($sum('total')) ?></b></div>
      <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><span class="save-st<?= $faltan ? ' warn' : '' ?>" id="save-st"><?= $faltan ? 'Faltan ' . $faltan . ' cantidad' . ($faltan > 1 ? 'es' : '') : '' ?></span>
        <button class="btn sm save-b" id="btn-guardar" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h11l3 3v15H5z"/><path d="M8 3v5h7V3"/><rect x="8" y="13" width="8" height="6" rx="1"/></svg>Guardar</button>
        <button class="btn" id="btn-mail" type="button">Enviar por correo</button>
        <a class="btn ghost sm" id="btn-csv" href="<?= site_url('concentrado/csv') . $qs(['orden' => $orden, 'dir' => $dir > 0 ? 'asc' : 'desc']) ?>" style="text-decoration:none">Descargar para Excel (CSV)</a></div>
    </div>
    <div class="mailinfo" id="mail-info"></div>
    <div class="notice okmsg" id="mail-msg" hidden></div>
    <div class="tablebox"><table>
      <thead><tr><th class="ck"><input type="checkbox" id="ck-all" title="Marcar todas las visibles"></th>
        <?php foreach ($cols as [$t, $k, $n]): $ord = $k !== 'pctDep' && isset(\App\Models\SolicitudModel::ORDENABLES[$k]); ?>
          <th class="<?= $n ? 'num' : '' ?> <?= in_array($k, $yel, true) ? 'yel' : '' ?> <?= $k === 'creadoPor' ? 'usr' : '' ?>">
          <?php if ($ord): ?><a class="thsort" href="<?= $qs(['orden' => $k, 'dir' => ($orden === $k && $dir > 0) ? 'desc' : 'asc']) ?>"><?= esc($t) ?><span class="arr"><?= $orden === $k ? ($dir > 0 ? '▲' : '▼') : '' ?></span></a><?php else: echo esc($t); endif ?></th>
        <?php endforeach ?><th></th></tr></thead>
      <tbody id="tbody">
      <?php foreach ($list as $r):
          $n = $cons[(int) $r['id']] ?? null; $est = \App\Models\SolicitudModel::depEstado($r); $pct = \App\Models\SolicitudModel::pctDep($r);
          $v = $r['real_dep'] !== null && $r['real_dep'] !== '' ? number_format((float) $r['real_dep'], 2, '.', '') : '';
          $falta = $est === 'no' && $v === ''; ?>
        <tr data-id="<?= (int) $r['id'] ?>" data-total="<?= esc($r['total'], 'attr') ?>" data-est="<?= $est ?>" data-real="<?= $v ?>">
          <td class="ck"><input type="checkbox" data-ck="<?= (int) $r['id'] ?>"></td>
          <?php foreach ($cols as [$t, $k, $num]):
              if ($k === 'enviadoEn') { echo '<td class="sent">' . ($r['enviado_en'] ? '✓ ' . esc(fecha_hora($r['enviado_en'])) : '') . '</td>'; }
              elseif ($k === 'depCompleto') { echo '<td><div class="sino" role="group" aria-label="¿Depositado completo?"><button type="button" data-dc="si" aria-pressed="' . ($est === 'si' ? 'true' : 'false') . '">Sí</button><button type="button" data-dc="no" aria-pressed="' . ($est === 'no' ? 'true' : 'false') . '">No</button></div></td>'; }
              elseif ($k === 'realDep') { echo '<td class="num"><input class="realdep ' . ($falta ? 'falta' : '') . '" data-rd inputmode="decimal" value="' . $v . '" ' . ($est === 'no' ? '' : 'disabled') . ' placeholder="' . ($est === 'no' ? 'Obligatorio' : '—') . '" title="' . ($est ? '' : 'Primero elige Sí o No') . '" aria-label="Viático real depositado"' . ($falta ? ' aria-invalid="true"' : '') . '>' . ($falta ? '<div class="falta-t">Escribe la cantidad</div>' : '') . '</td>'; }
              elseif ($k === 'pctDep') { echo '<td class="num mono pct ' . $pctClase($pct) . '">' . $pctTxt($pct) . '</td>'; }
              else { echo celda_solicitud($r, $k, (bool) $num, $n); }
          endforeach ?>
          <td><div class="rowact"><a class="btn ghost sm" href="<?= site_url('solicitudes/' . (int) $r['id'] . '/editar') ?>" style="text-decoration:none">Editar</a>
            <?php if ($puedeBorrar): ?><form method="post" action="<?= site_url('solicitudes/' . (int) $r['id'] . '/eliminar') ?>" class="inline-form" data-confirmar><?= csrf_field() ?><button class="btn ghost sm" type="submit">Eliminar</button></form><?php endif ?></div></td></tr>
      <?php endforeach ?>
      </tbody>
      <?php if ($list): ?>
      <tfoot id="tfoot"><tr><td colspan="<?= $verUsuario ? 15 : 14 ?>">Total (<?= count($list) ?>)</td>
        <td class="num mono"><?= dinero($sum('tran_publico')) ?></td><td class="num mono"><?= dinero($sum('taxis')) ?></td><td></td>
        <td class="num mono"><?= dinero($sum('autobus')) ?></td><td class="num mono"><?= dinero($sum('comida')) ?></td><td class="num mono"><?= dinero($sum('hotel')) ?></td><td class="num mono"><?= dinero($sum('otros')) ?></td>
        <td></td><td></td><td class="num mono"><?= dinero($sum('total')) ?></td><td colspan="2"></td>
        <td class="sino-t" id="pf-sino"><?= $si || $no ? "Sí $si · No $no" : '' ?></td>
        <td class="num mono" id="pf-real"><?= dinero($sum('real_dep')) ?></td>
        <td class="num mono pct <?= $pctClase($pp) ?>" id="pf-pct" title="Sobre las solicitudes con depósito capturado"><?= $pp === null ? '' : $pctTxt($pp) ?></td><td></td></tr></tfoot>
      <?php endif ?>
    </table>
    <div class="empty-state" id="empty"<?= $list ? ' hidden' : '' ?>><?= $conteo && ! $list ? '<strong>Sin resultados</strong>Ningún registro coincide con los filtros. Prueba con “Quitar filtros”.' : '<strong>Aún no hay solicitudes</strong>Captura la primera en la pestaña “Nueva solicitud”.' ?></div></div>
  </section>
<script type="application/json" id="mail-data"><?= json_encode(array_map(fn ($r) => fila_correo($r, $cons[(int) $r['id']] ?? null), $list), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?= $this->endSection() ?>
