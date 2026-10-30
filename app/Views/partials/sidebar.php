<?php
/** @var array|null $yo @var array $usuarios */
$esOp = $yo && $yo['rol'] === 'operador';
$conteos = [];
if ($esOp) {
    foreach (db_connect()->table('solicitudes')->select('usuario_id, COUNT(*) AS n')->where('deleted_at', null)->groupBy('usuario_id')->get()->getResultArray() as $r) {
        $conteos[(int) $r['usuario_id']] = (int) $r['n'];
    }
    $nEmp = model(\App\Models\EmpleadoModel::class)->countAllResults();
    $nCc  = model(\App\Models\CentroCostoModel::class)->countAllResults();
    $ult  = model(\App\Models\CatalogoActualizacionModel::class)->ultima();
}
$tarjeta = function (array $u) use ($yo, $esOp, $conteos) {
    $on = $yo && (int) $yo['id'] === (int) $u['id'];
    $n  = $esOp && $u['rol'] !== 'operador' ? ($conteos[(int) $u['id']] ?? 0) : null;
    $sub = $on ? '● En sesión' : ($u['tiene_clave'] ? ($n !== null ? $n . ' solicitud' . ($n === 1 ? '' : 'es') : 'Entrar con contraseña') : ($u['rol'] === 'operador' ? 'Crear contraseña' : 'Sin contraseña'));
    ob_start(); ?>
    <div class="ucard <?= $on ? 'on' : '' ?>"><button type="button" class="ubtn" data-u="<?= (int) $u['id'] ?>" data-nombre="<?= esc($u['nombre'], 'attr') ?>" data-rol="<?= esc($u['rol'], 'attr') ?>" data-clave="<?= $u['tiene_clave'] ? 1 : 0 ?>" data-rol-txt="<?= esc(rol_texto($u), 'attr') ?>" data-on="<?= $on ? 1 : 0 ?>" title="<?= $on ? 'Sesión activa' : 'Entrar como ' . esc($u['nombre'], 'attr') ?>">
      <span class="av"><?= avatar_html($u) ?></span><span class="ut"><b><?= esc($u['nombre']) ?></b><small><?= esc($sub) ?></small></span></button>
      <?php if ($esOp || $on): ?><button type="button" class="uedit" data-e="<?= (int) $u['id'] ?>" data-nombre="<?= esc($u['nombre'], 'attr') ?>" data-rol="<?= esc($u['rol'], 'attr') ?>" data-tema="<?= esc((string) $u['tema'], 'attr') ?>" data-foto="<?= $u['tiene_foto'] ? 1 : 0 ?>" data-nsol="<?= (int) ($conteos[(int) $u['id']] ?? 0) ?>" title="Editar perfil" aria-label="Editar perfil de <?= esc($u['nombre'], 'attr') ?>">✎</button><?php endif ?>
    </div>
    <?php return ob_get_clean();
};
$coords = array_filter($usuarios, fn ($u) => $u['rol'] === 'coordinador');
$vis    = array_filter($usuarios, fn ($u) => $u['rol'] === 'viatico');
$ops    = array_filter($usuarios, fn ($u) => $u['rol'] === 'operador');
?>
<aside class="side" id="side" aria-label="Usuarios">
  <button type="button" class="side-close" id="side-close" aria-label="Cerrar panel de usuarios">✕</button>
  <div class="side-brand">Usuarios</div>
  <?php if ($yo): ?>
  <div class="side-me" id="side-me"><div class="ringwrap"><div class="ring"><div class="av lg"><?= avatar_html($yo + ['tiene_foto' => (bool) $yo['foto']]) ?></div></div>
    <button type="button" class="cam" id="me-cam" title="Cambiar mi foto" aria-label="Cambiar mi foto" data-e="<?= (int) $yo['id'] ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg></button></div>
    <div class="me-b"><button type="button" class="sbtn" data-e="<?= (int) $yo['id'] ?>">Mi perfil</button>
    <form method="post" action="<?= site_url('logout') ?>" class="inline-form" data-salir><?= csrf_field() ?><button class="sbtn" type="submit">Guardar y salir</button></form></div></div>
  <?php else: ?>
  <div class="side-me" id="side-me"><div class="ring off"><div class="av lg"><span style="background:rgba(255,255,255,.12)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></span></div></div></div>
  <?php endif ?>
  <?php if ($esOp): ?>
  <button type="button" class="side-add" id="side-add"><b>+</b>Adicionar coordinador</button>
  <a class="side-upd" href="<?= site_url('catalogo') ?>" style="text-decoration:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 20h16"/></svg><span>Subir actualización de IDs y centros de costos</span></a>
  <div class="side-cat" id="side-cat">Catálogo: <?= $nEmp ?> IDs · <?= $nCc ?> CC<?= $ult ? ' · act. ' . esc(date('d/m/Y', strtotime($ult['created_at']))) : ' · original' ?></div>
  <?php endif ?>
  <div class="side-h">Coordinadores <span class="n"><?= count($coords) ?></span></div>
  <div class="side-list" id="side-coords">
    <?php if ($coords): foreach ($coords as $u) echo $tarjeta($u); else: ?><p class="side-empty"><?= $esOp ? 'Aún no hay coordinadores. Usa “Adicionar coordinador”.' : 'Aún no hay coordinadores dados de alta.' ?></p><?php endif ?>
  </div>
  <?php if ($vis || $esOp): ?><div id="side-vi-wrap"><div class="side-h">Coordinador viático <span class="n"><?= count($vis) ?></span></div><div class="side-list" id="side-vi"><?php foreach ($vis as $u) echo $tarjeta($u); ?></div></div><?php endif ?>
  <div class="side-h">Operador</div>
  <div class="side-list" id="side-ops"><?php foreach ($ops as $u) echo $tarjeta($u); ?></div>
  <?php if ($esOp): ?><div class="side-data"><a class="side-dbtn" href="<?= site_url('respaldo') ?>" style="text-decoration:none;display:block">⬇ Descargar respaldo</a></div><?php endif ?>
  <p class="side-foot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg><span>Cada coordinador ve solo las solicitudes que captura. El operador ve las de todos.</span></p>
</aside>
<script type="application/json" id="cfg-temas"><?= json_encode(config('Sivit')->temas, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="cfg-min"><?= (int) config('Sivit')->passwordMinimo ?></script>
