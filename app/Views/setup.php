<!doctype html>
<html lang="es-MX"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>SIVIT · Instalación</title>
<style <?= csp_style_nonce() ?>>
body{font:16px/1.5 system-ui,Arial,sans-serif;background:#f3f5f8;color:#16202c;margin:0;padding:24px}
main{max-width:680px;margin:0 auto;background:#fff;border:1px solid #d4dbe5;border-radius:14px;padding:28px}
h1{margin:0 0 4px;color:#1f4e79}p.s{margin:0 0 18px;color:#5b6782}
table{width:100%;border-collapse:collapse;margin:12px 0}td{padding:6px 8px;border-bottom:1px solid #eaeef4;font-size:14px}
.ok{color:#0a7a3d;font-weight:700}.mal{color:#b3261e;font-weight:700}
.err{background:#fde8e6;border:1px solid #f3b6b0;padding:10px 12px;border-radius:8px;margin:12px 0}
.aviso{background:#fff7e0;border:1px solid #f0d98a;padding:10px 12px;border-radius:8px;margin:12px 0}
input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d4dbe5;border-radius:8px;font-size:16px}
button{margin-top:12px;background:#1f4e79;color:#fff;border:0;border-radius:8px;padding:11px 20px;font-size:16px;cursor:pointer}
code{background:#eaeef4;padding:1px 5px;border-radius:4px}ul{margin:6px 0 6px 20px;padding:0;font-size:14px}
</style></head><body><main>
<h1>SIVIT · Instalación</h1><p class="s">Crea las tablas de la base de datos y el Operador Virtual.</p>

<?php if ($estado === 'sin_token'): ?>
  <div class="aviso"><b>El instalador está desactivado.</b> Para usarlo, agrega esta línea al archivo <code>.env</code> (con un valor largo y propio) y recarga esta página:
  <p><code>sivit.setupToken = UNA-CLAVE-LARGA-Y-SECRETA</code></p></div>

<?php elseif ($estado === 'terminado'): ?>
  <p class="ok">✓ Instalación terminada.</p>
  <p>Migraciones aplicadas ahora: <b><?= count($resultado['nuevas']) ?></b> (total registradas: <?= (int) $resultado['total'] ?>).</p>
  <?php if ($resultado['nuevas']): ?><ul><?php foreach ($resultado['nuevas'] as $n): ?><li><?= esc($n) ?></li><?php endforeach ?></ul><?php endif ?>
  <p>Tablas en la base: <?= esc(implode(', ', $resultado['tablas'])) ?></p>
  <div class="aviso"><b>Ahora:</b><ol>
    <li>Entra a <a href="<?= esc(site_url('/'), 'attr') ?>">SIVIT</a>, da clic en <b>Operador Virtual</b> y crea su contraseña.</li>
    <li>Con el operador: sube el catálogo (<b>Subir actualización de IDs y centros de costos</b>) y da de alta a los coordinadores.</li>
    <li>Quita la línea <code>sivit.setupToken</code> del <code>.env</code>.</li></ol>
  Este instalador ya quedó bloqueado<?= $resultado['bloqueado'] ? ' (archivo <code>writable/setup.lock</code>)' : ', pero <b>no se pudo escribir writable/setup.lock</b>: quita el token del .env de inmediato' ?>.</div>

<?php else: ?>
  <?php if ($error): ?><div class="err"><?= esc($error) ?></div><?php endif ?>
  <table><?php foreach ($reqs as [$t, $ok, $d]): ?><tr><td><?= esc($t) ?></td><td class="<?= $ok ? 'ok' : 'mal' ?>"><?= $ok ? '✓' : '✗' ?></td><td><?= esc($d) ?></td></tr><?php endforeach ?></table>
  <form method="post" action="<?= esc(site_url('setup.php'), 'attr') ?>" autocomplete="off">
    <label>Token de instalación (el de <code>sivit.setupToken</code> en el <code>.env</code>)
    <input type="password" name="token" required autofocus></label>
    <button type="submit">Ejecutar migraciones</button>
  </form>
<?php endif ?>
</main></body></html>
