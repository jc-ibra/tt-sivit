<!doctype html>
<html lang="es-MX"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIVIT · Sistema Integral de Viáticos Trantor</title>
<?= view('partials/favicon') ?>
<meta name="base-url" content="<?= esc(site_url('/'), 'attr') ?>"><meta name="csrf-name" content="<?= csrf_token() ?>"><meta name="csrf-hash" content="<?= csrf_hash() ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Barlow:wght@400;500;600&family=JetBrains+Mono:wght@400;600&display=swap">
<link rel="stylesheet" href="<?= base_url('assets/sivit.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/sivit-extra.css') ?>">
<?php $tema = $yo['tema'] ?? null; if ($tema): ?>
<style <?= csp_style_nonce() ?>>:root{--brand:<?= esc($tema, 'css') ?>;--brand-2:<?= esc(aclarar($tema, .22), 'css') ?>}</style>
<?php endif ?>
</head><body class="<?= $yo ? ($yo['rol'] === 'operador' ? 'op' : '') : 'locked' ?>" data-rol="<?= esc($yo['rol'] ?? '') ?>">
<?= view('partials/sidebar', ['yo' => $yo ?? null, 'usuarios' => $usuarios ?? []]) ?>

<header class="top"><div class="wrap">
  <div class="brand"><div class="btxt"><h1>SIVIT</h1><p>Sistema Integral de Viáticos Trantor</p></div></div>
  <div class="hdr-user" id="hdr-user">
  <?php if ($yo): ?>
    <span class="av"><?= avatar_html($yo + ['tiene_foto' => (bool) $yo['foto']]) ?></span><span class="hu-t"><b><?= esc($yo['nombre']) ?></b><small><?= esc(rol_texto($yo)) ?></small></span>
    <button type="button" class="hu-b" data-e="<?= (int) $yo['id'] ?>">Mi perfil</button>
    <form method="post" action="<?= site_url('logout') ?>" class="inline-form" data-salir><?= csrf_field() ?><button class="hu-b out" type="submit">Guardar y salir</button></form>
  <?php endif ?>
  </div>
  <nav class="tabs" role="tablist">
    <a href="<?= site_url('solicitudes') ?>" role="tab" aria-selected="<?= ($tab ?? '') === 'form' ? 'true' : 'false' ?>">Nueva solicitud</a>
    <a href="<?= site_url('concentrado') ?>" role="tab" aria-selected="<?= ($tab ?? '') === 'list' ? 'true' : 'false' ?>">Concentrado de solicitudes<span class="count"><?= (int) ($conteo ?? 0) ?></span></a>
    <a href="<?= site_url('indicadores') ?>" role="tab" aria-selected="<?= ($tab ?? '') === 'kpi' ? 'true' : 'false' ?>">Indicadores</a>
  </nav>
</div></header>

<?php if (! $yo): ?>
<section id="welcome" class="wrap"><div class="welcome"><h2>Bienvenido a SIVIT</h2><p class="wsub">Sistema Integral de Viáticos Trantor</p>
  <p><?= ! empty($operadorTieneClave) ? 'Elige tu perfil en el panel de la izquierda y escribe tu contraseña.' : 'Para empezar, da clic en “Operador Virtual” (a la izquierda) y crea la contraseña del administrador.' ?></p>
  <div class="arrow" aria-hidden="true">←</div></div></section>
<?php endif ?>

<?= view('partials/tt_mark') ?>
<main class="wrap">
  <?php if (session('error') && $yo): ?><div class="notice" role="alert"><?= esc(session('error')) ?></div><?php endif ?>
  <?= $this->renderSection('contenido') ?>
</main>

<div class="toast" id="toast" hidden></div>
<?php if (session('ok')): ?><div id="flash" data-msg="<?= esc(session('ok'), 'attr') ?>" hidden></div><?php endif ?>
<?php if (session('error') && ! $yo): ?><div id="flash-error" data-msg="<?= esc(session('error'), 'attr') ?>" data-abrir="<?= (int) session('abrir') ?>" hidden></div><?php endif ?>
<script src="<?= base_url('assets/sivit.js') ?>" defer></script>
</body></html>
