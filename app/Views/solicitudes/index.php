<?= $this->extend('layout') ?>
<?= $this->section('contenido') ?>
<?php
$editando = $edit !== null;
$v = function (string $campo, $dflt = '') use ($old, $edit) {
    if ($old) { return (string) ($old[$campo] ?? ''); }
    return $dflt;
};
// valores de partida: lo escrito (si hubo errores), la solicitud que se edita, o vacío
$nominaEdit = '';
if ($editando) {
    $nominaEdit = ($edit['nombre'] !== 'NO ESTA EN BASE' ? $edit['nombre'] . ' - ' : '') . $edit['nomina'];
}
$val = [
    'fecha' => $v('fecha', $editando ? $edit['fecha'] : date('Y-m-d')),
    'nomina' => $v('nomina', $nominaEdit), 'folio' => $v('folio', $editando ? $edit['folio_gp'] : ''),
    'cliente' => $v('cliente', $editando ? $edit['cliente'] : ''), 'lf' => $v('lf', $editando ? $edit['lf'] : ''),
    'traslado' => $v('traslado', $editando ? $edit['traslado'] : ''),
    'tranPublico' => $v('tranPublico', $editando && (float) $edit['tran_publico'] ? $edit['tran_publico'] : ''),
    'taxis' => $v('taxis', $editando && (float) $edit['taxis'] ? $edit['taxis'] : ''),
    'justTaxi' => $v('justTaxi', $editando ? $edit['just_taxi'] : ''),
    'autobus' => $v('autobus', $editando && (float) $edit['autobus'] ? $edit['autobus'] : ''),
    'comida' => $v('comida', $editando && (float) $edit['comida'] ? $edit['comida'] : ''),
    'hotel' => $v('hotel', $editando && (float) $edit['hotel'] ? $edit['hotel'] : ''),
    'otros' => $v('otros', $editando && (float) $edit['otros'] ? $edit['otros'] : ''),
    'conceptoOtros' => $v('conceptoOtros', $editando ? $edit['concepto_otros'] : ''),
    'comentarios' => $v('comentarios', $editando ? $edit['comentarios'] : ''),
];
$cfg = config('Sivit');
$verUsuario = in_array($yo['rol'], ['operador', 'viatico'], true);
$cols = array_values(array_filter(columnas_solicitud($verUsuario), fn ($c) => ! in_array($c[1], ['enviadoEn', 'depCompleto', 'realDep', 'pctDep'], true)));
$puedeBorrar = $yo['rol'] !== 'viatico';
$tot = array_sum(array_map(fn ($r) => (float) $r['total'], $porEnviar));
$accion = $editando ? site_url('solicitudes/' . (int) $edit['id']) : site_url('solicitudes');
?>
  <section id="view-form">
    <form id="frm" method="post" action="<?= $accion ?>" novalidate autocomplete="off">
      <?= csrf_field() ?>
      <?php if ($editando): ?><div id="editing" class="notice">Editando la solicitud #<?= (int) ($cons[(int) $edit['id']] ?? 0) ?> de <?= esc($edit['nombre']) ?>. Guarda para aplicar los cambios o usa Limpiar para cancelar.</div><?php endif ?>
      <div class="sec"><h2>Solicitud de viáticos · desliza a la derecha para ver todas las columnas →</h2>
        <div class="linebox"><div class="grid lineal">
          <label class="f"><span>FECHA <b class="req">*</b></span><small>DD/MM/AAAA</small><input type="date" id="fecha" name="fecha" value="<?= esc($val['fecha'], 'attr') ?>" required></label>
          <label class="f"><span>CORDINADOR</span><small>AUTOMÁTICO POR NÓMINA</small><div class="auto empty" id="a-coord">—</div></label>
          <label class="f"><span>REGIONAL</span><small>AUTOMÁTICO POR NÓMINA</small><div class="auto empty" id="a-reg">—</div></label>
          <label class="f"><span>INGENIERO</span><small>AUTOMÁTICO POR NÓMINA</small><div class="auto empty" id="a-nombre">—</div></label>
          <label class="f"><span>NUMERO DE NOMINA <b class="req">*</b></span><small>ESCRIBIR NÓMINA</small><input id="nomina" name="nomina" list="lista-nominas" placeholder="Número o nombre" value="<?= esc($val['nomina'], 'attr') ?>" required<?= $editando ? '' : ' autofocus' ?>><datalist id="lista-nominas"></datalist></label>
          <label class="f"><span>ESTATUS IDC</span><small>AUTOMÁTICO</small><div class="auto empty" id="a-est">—</div></label>
          <label class="f"><span>INTERNO O EXTERNO</span><small>INTERNO / PROVEEDOR</small><div class="auto empty" id="a-tipo">—</div></label>
          <label class="f"><span>FOLIO GP <b class="req">*</b></span><small>SI NO HAY: 0000</small><input id="folio" name="folio" autocomplete="off" required class="uc" value="<?= esc($val['folio'], 'attr') ?>"></label>
          <label class="f"><span>CLIENTE <b class="req">*</b></span><small>ELEGIR CLIENTE</small><input id="cliente" name="cliente" list="lista-clientes" placeholder="Escribe el cliente" autocomplete="off" required value="<?= esc($val['cliente'], 'attr') ?>"><datalist id="lista-clientes"></datalist></label>
          <label class="f"><span>CC</span><small title="Automático (centros de costos)">AUTOMÁTICO</small><div class="auto empty" id="a-cc">—</div></label>
          <div class="f"><span>L/F <b class="req">*</b></span><small>L LOCAL / F FORÁNEO</small><div class="seg" id="lf"><button type="button" data-v="L" aria-pressed="<?= $val['lf'] === 'L' ? 'true' : 'false' ?>">L</button><button type="button" data-v="F" aria-pressed="<?= $val['lf'] === 'F' ? 'true' : 'false' ?>">F</button></div><input type="hidden" name="lf" id="lf-val" value="<?= esc($val['lf'], 'attr') ?>"></div>
          <label class="f"><span>TRASLADO <b class="req">*</b></span><small>SE PROPORCIONA</small><input id="traslado" name="traslado" required class="uc" value="<?= esc($val['traslado'], 'attr') ?>"></label>
          <label class="f"><span>TRAN PUBLICO ($)</span><small>EN PESOS ($)</small><input class="money" id="tranPublico" name="tranPublico" inputmode="decimal" placeholder="0.00" value="<?= esc($val['tranPublico'], 'attr') ?>"></label>
          <label class="f"><span>TAXIS ($)</span><small>EN PESOS ($)</small><input class="money" id="taxis" name="taxis" inputmode="decimal" placeholder="0.00" value="<?= esc($val['taxis'], 'attr') ?>"></label>
          <label class="f" id="wrap-just"><span>JUSTIFICACION DE USO DE TAXI</span><small>ELEGIR JUSTIFICACIÓN</small><input id="justTaxi" name="justTaxi" list="lista-just" placeholder="Escribe la justificación" autocomplete="off" value="<?= esc($val['justTaxi'], 'attr') ?>"><datalist id="lista-just"><?php foreach ($cfg->justificacionesTaxi as $j): ?><option value="<?= esc($j, 'attr') ?>"><?php endforeach ?></datalist></label>
          <label class="f"><span>AUTOBÚS ($)</span><small>EN PESOS ($)</small><input class="money" id="autobus" name="autobus" inputmode="decimal" placeholder="0.00" value="<?= esc($val['autobus'], 'attr') ?>"></label>
          <label class="f"><span>COMIDA ($)</span><small>EN PESOS ($)</small><input class="money" id="comida" name="comida" inputmode="decimal" placeholder="0.00" value="<?= esc($val['comida'], 'attr') ?>"></label>
          <label class="f"><span>HOTEL ($)</span><small>EN PESOS ($)</small><input class="money" id="hotel" name="hotel" inputmode="decimal" placeholder="0.00" value="<?= esc($val['hotel'], 'attr') ?>"></label>
          <label class="f"><span>OTROS ($)</span><small>EN PESOS ($)</small><input class="money" id="otros" name="otros" inputmode="decimal" placeholder="0.00" value="<?= esc($val['otros'], 'attr') ?>"></label>
          <label class="f" id="wrap-concepto"><span>CONCEPTO DE OTROS</span><small>SEÑALAR SI HAY MONTO EN OTROS</small><input id="conceptoOtros" name="conceptoOtros" list="lista-conceptos" placeholder="Escribe el concepto" autocomplete="off" value="<?= esc($val['conceptoOtros'], 'attr') ?>"><datalist id="lista-conceptos"><?php foreach ($cfg->conceptosOtros as $j): ?><option value="<?= esc($j, 'attr') ?>"><?php endforeach ?></datalist></label>
          <label class="f" id="wrap-coment"><span>COMENTARIOS</span><small>DESCRIBIR (OBLIGATORIO SI HAY OTROS)</small><input id="comentarios" name="comentarios" class="uc" value="<?= esc($val['comentarios'], 'attr') ?>"></label>
          <label class="f"><span>VIATICO $</span><small>SUMA AUTOMÁTICA</small><div class="auto" id="a-total">$0.00</div></label>
          <div class="f"><span>GUARDAR</span><small>CLIC AL TERMINAR</small><button type="submit" class="btn chk" id="btn-chk" title="Guardar esta solicitud">✓ Guardar</button></div>
        </div></div>
      </div>
      <div id="errors" class="err" role="alert"><?= implode('<br>', array_map(fn ($e) => esc($e), $errs)) ?></div>
      <div class="totalbar">
        <div><div class="t">Viático total · suma automática</div><div class="amt" id="total">$0.00</div></div>
        <div class="actions">
          <button type="button" class="btn ghost" id="btn-gomail">Enviar las de hoy por correo</button>
          <a class="btn ghost" id="btn-clear" href="<?= site_url('solicitudes') ?>" style="text-decoration:none">Limpiar</a>
          <button type="submit" class="btn" id="btn-save"><?= $editando ? 'Guardar cambios' : 'Guardar solicitud' ?></button>
        </div>
      </div>
    </form>

    <div class="sec hoy">
      <div class="hd"><h2>Solicitudes por enviar</h2><div class="hdr-r"><div class="tt"><span id="h-n"><?= count($porEnviar) ?></span> solicitudes · Total <b id="h-t"><?= dinero($tot) ?></b></div></div></div>
      <div class="mailrow" style="margin-bottom:12px">
        <label class="f" style="flex:1 1 320px"><span>Enviar a</span><small>Correo destino (puedes agregar varios separados por coma)</small><input id="h-to" type="text" value="<?= esc($correo, 'attr') ?>" data-guardar-correo></label>
        <div class="mailinfo" id="h-info"></div>
        <button class="btn" id="h-send" type="button">Enviar por correo</button>
      </div>
      <div class="notice okmsg" id="h-msg" hidden style="margin-bottom:12px"></div>
      <div class="tablebox"><table>
        <thead><tr><th class="ck"><input type="checkbox" id="h-ck-all" title="Marcar / desmarcar todas" checked></th>
          <?php foreach ($cols as [$t, $k, $n]): ?><th class="<?= $n ? 'num' : '' ?> <?= in_array($k, ['lf','traslado','tranPublico','taxis','autobus','comida','hotel','otros','comentarios'], true) ? 'yel' : '' ?> <?= $k === 'creadoPor' ? 'usr' : '' ?>"><?= esc($t) ?></th><?php endforeach ?><th></th></tr></thead>
        <tbody id="h-body">
        <?php foreach ($porEnviar as $r): $n = $cons[(int) $r['id']] ?? null; ?>
          <tr data-id="<?= (int) $r['id'] ?>"><td class="ck"><input type="checkbox" data-hck="<?= (int) $r['id'] ?>" checked title="Marcada = se envía"></td>
          <?php foreach ($cols as [$t, $k, $num]) echo celda_solicitud($r, $k, (bool) $num, $n); ?>
          <td><div class="rowact"><a class="btn ghost sm" href="<?= site_url('solicitudes/' . (int) $r['id'] . '/editar') ?>" style="text-decoration:none">Editar</a>
            <?php if ($puedeBorrar): ?><form method="post" action="<?= site_url('solicitudes/' . (int) $r['id'] . '/eliminar') ?>" class="inline-form" data-confirmar><?= csrf_field() ?><button class="btn ghost sm" type="submit">Eliminar</button></form><?php endif ?></div></td></tr>
        <?php endforeach ?>
        </tbody>
      </table>
      <div class="empty-state" id="h-empty"<?= $porEnviar ? ' hidden' : '' ?>><strong>No hay solicitudes por enviar</strong>Las que ya enviaste están en el Concentrado de solicitudes. Para una nueva, llena la línea de arriba y da clic en “✓ Guardar”.</div></div>
    </div>
  </section>
<script type="application/json" id="catalogo-data"><?= json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="cfg-form"><?= json_encode(['estatusRevisar' => $cfg->estatusRevisar, 'justificaciones' => $cfg->justificacionesTaxi, 'conceptos' => $cfg->conceptosOtros, 'editando' => $editando, 'justPrevia' => $editando ? $edit['just_taxi'] : ''], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script type="application/json" id="mail-data"><?= json_encode(array_map(fn ($r) => fila_correo($r, $cons[(int) $r['id']] ?? null), $porEnviar), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?= $this->endSection() ?>
