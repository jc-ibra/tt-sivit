<?php

/** Funciones de formato usadas en las vistas (equivalentes a fmt/dmy/esc del HTML original). */

if (! function_exists('dinero')) {
    function dinero($n): string
    {
        return '$' . number_format((float) $n, 2, '.', ',');
    }
}

if (! function_exists('dmy')) {
    /** 2026-10-29 → 29/10/2026 */
    function dmy(?string $iso): string
    {
        return $iso ? implode('/', array_reverse(explode('-', substr($iso, 0, 10)))) : '';
    }
}

if (! function_exists('fecha_hora')) {
    function fecha_hora(?string $dt): string
    {
        return $dt ? date('d/m/Y H:i', strtotime($dt)) : '';
    }
}

if (! function_exists('rol_texto')) {
    function rol_texto(array $u): string
    {
        return match ($u['rol']) {
            'operador' => 'Operador · administrador',
            'viatico'  => 'Coordinador viático',
            default    => 'Coordinador',
        };
    }
}

if (! function_exists('iniciales')) {
    function iniciales(string $n): string
    {
        $p = array_slice(preg_split('/\s+/', trim($n)) ?: [], 0, 2);
        return mb_strtoupper(implode('', array_map(fn ($x) => mb_substr($x, 0, 1), $p)));
    }
}

if (! function_exists('aclarar')) {
    function aclarar(string $hex, float $p): string
    {
        $n = hexdec(ltrim($hex, '#'));
        $c = [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
        return '#' . implode('', array_map(fn ($v) => str_pad(dechex((int) round($v + (255 - $v) * $p)), 2, '0', STR_PAD_LEFT), $c));
    }
}

if (! function_exists('avatar_html')) {
    /** Foto del perfil o, si no tiene, el ícono de persona en el color del perfil. */
    function avatar_html(array $u): string
    {
        static $seq = 0;
        $tieneFoto = ! empty($u['tiene_foto']) || ($u['rol'] ?? '') === 'operador';
        if ($tieneFoto) {
            $src = ! empty($u['tiene_foto']) ? site_url('usuarios/' . (int) $u['id'] . '/foto') : base_url('assets/operador.jpg');
            return '<img src="' . esc($src, 'attr') . '" alt="">';
        }
        $cols = ['#2f7fd6', '#8b5cf6', '#14a89a', '#e0457b', '#f08c1a', '#22a55a', '#4f5bd5', '#0e9fc4'];
        $c    = ! empty($u['tema']) ? aclarar($u['tema'], .18) : $cols[((int) ($u['color'] ?? 0)) % 8];
        $id   = 'pi' . (++$seq);
        $a35  = aclarar($c, .35);
        $a30  = aclarar($c, .3);
        return '<span class="pi-w"><svg class="pi" viewBox="0 0 64 64" aria-hidden="true"><defs>'
            . "<radialGradient id=\"{$id}h\" cx=\"38%\" cy=\"32%\" r=\"70%\"><stop offset=\"0\" stop-color=\"#fff\" stop-opacity=\".95\"/><stop offset=\".28\" stop-color=\"$a35\"/><stop offset=\"1\" stop-color=\"$c\"/></radialGradient>"
            . "<linearGradient id=\"{$id}b\" x1=\"0\" y1=\"0\" x2=\"0\" y2=\"1\"><stop offset=\"0\" stop-color=\"$a30\"/><stop offset=\"1\" stop-color=\"$c\"/></linearGradient>"
            . "<clipPath id=\"{$id}c\"><circle cx=\"32\" cy=\"32\" r=\"30\"/></clipPath></defs>"
            . '<circle cx="32" cy="32" r="31" fill="#f7f9fc" stroke="#dde3eb" stroke-width="1.5"/>'
            . "<g clip-path=\"url(#{$id}c)\"><path d=\"M9 64c0-14 10-22 23-22s23 8 23 22z\" fill=\"url(#{$id}b)\"/><ellipse cx=\"32\" cy=\"45.5\" rx=\"10\" ry=\"2.6\" fill=\"#fff\" opacity=\".35\"/></g>"
            . "<circle cx=\"32\" cy=\"24\" r=\"12.5\" fill=\"url(#{$id}h)\"/></svg></span>";
    }
}

if (! function_exists('columnas_solicitud')) {
    /** Columnas del concentrado: [título, clave, numérica?, columna BD]. La columna "Usuario" solo la ven operador y viático. */
    function columnas_solicitud(bool $verUsuario): array
    {
        $c = [
            ['#', 'n', 1, 'n'], ['Usuario', 'creadoPor', 0, 'creado_por'], ['Fecha', 'fecha', 0, 'fecha'], ['Coordinador', 'coordinador', 0, 'coordinador'],
            ['Regional', 'regional', 0, 'regional'], ['Ingeniero', 'nombre', 0, 'nombre'], ['Nómina', 'nomina', 1, 'nomina'], ['Estatus IDC', 'estatus', 0, 'estatus'],
            ['Interno/Externo', 'tipo', 0, 'tipo'], ['Folio GP', 'folioGP', 0, 'folio_gp'], ['Cliente', 'cliente', 0, 'cliente'], ['CC', 'cc', 1, 'cc'], ['L/F', 'lf', 0, 'lf'],
            ['Traslado', 'traslado', 0, 'traslado'], ['Tran público', 'tranPublico', 1, 'tran_publico'], ['Taxis', 'taxis', 1, 'taxis'],
            ['Justificación taxi', 'justTaxi', 0, 'just_taxi'], ['Autobús', 'autobus', 1, 'autobus'], ['Comida', 'comida', 1, 'comida'], ['Hotel', 'hotel', 1, 'hotel'],
            ['Otros', 'otros', 1, 'otros'], ['Concepto otros', 'conceptoOtros', 0, 'concepto_otros'], ['Comentarios', 'comentarios', 0, 'comentarios'],
            ['Viático $', 'total', 1, 'total'], ['OK', 'ok', 0, 'ok'], ['Enviado', 'enviadoEn', 0, 'enviado_en'], ['¿Depositado completo?', 'depCompleto', 0, 'dep_completo'],
            ['Viático real depositado', 'realDep', 1, 'real_dep'], ['% depositado', 'pctDep', 1, null],
        ];
        return array_values(array_filter($c, fn ($x) => $x[1] !== 'creadoPor' || $verUsuario));
    }
}

if (! function_exists('celda_solicitud')) {
    /** Celda de datos de una solicitud (misma presentación que el HTML original). */
    function celda_solicitud(array $r, string $k, bool $num, ?int $n): string
    {
        $money = ['tranPublico' => 'tran_publico', 'taxis' => 'taxis', 'autobus' => 'autobus', 'comida' => 'comida', 'hotel' => 'hotel', 'otros' => 'otros'];
        $txt   = ['folioGP' => 'folio_gp', 'justTaxi' => 'just_taxi', 'conceptoOtros' => 'concepto_otros', 'creadoPor' => 'creado_por'];
        if ($k === 'n') {
            return '<td class="num mono">' . (int) $n . '</td>';
        }
        if ($k === 'creadoPor') {
            return '<td class="usr">' . esc($r['creado_por'] ?: '—') . '</td>';
        }
        if ($k === 'ok') {
            return '<td><span class="pill ' . ($r['ok'] === 'OK' ? 'ok' : 'rev') . '">' . esc($r['ok']) . '</span></td>';
        }
        if ($k === 'fecha') {
            return '<td class="mono">' . dmy($r['fecha']) . '</td>';
        }
        if ($k === 'total') {
            return '<td class="num mono"><b>' . dinero($r['total']) . '</b></td>';
        }
        if (isset($money[$k])) {
            return '<td class="num mono">' . ((float) $r[$money[$k]] ? dinero($r[$money[$k]]) : '') . '</td>';
        }
        $v = $r[$txt[$k] ?? $k] ?? '';
        return '<td class="' . ($num ? 'num mono' : (in_array($k, ['traslado', 'justTaxi', 'comentarios'], true) ? 'wrapc' : '')) . '">' . esc((string) $v) . '</td>';
    }
}

if (! function_exists('fila_correo')) {
    /** Datos de una solicitud para armar el correo en el navegador. */
    function fila_correo(array $r, ?int $n): array
    {
        return [
            'id' => (int) $r['id'], 'n' => $n, 'fecha' => $r['fecha'], 'coordinador' => $r['coordinador'], 'regional' => $r['regional'], 'nombre' => $r['nombre'],
            'nomina' => $r['nomina'], 'estatus' => $r['estatus'], 'tipo' => $r['tipo'], 'folioGP' => $r['folio_gp'], 'cliente' => $r['cliente'], 'cc' => $r['cc'],
            'lf' => $r['lf'], 'traslado' => $r['traslado'], 'tranPublico' => (float) $r['tran_publico'], 'taxis' => (float) $r['taxis'], 'justTaxi' => $r['just_taxi'],
            'autobus' => (float) $r['autobus'], 'comida' => (float) $r['comida'], 'hotel' => (float) $r['hotel'], 'otros' => (float) $r['otros'],
            'conceptoOtros' => $r['concepto_otros'], 'comentarios' => $r['comentarios'], 'total' => (float) $r['total'], 'ok' => $r['ok'], 'enviado' => (bool) $r['enviado_en'],
        ];
    }
}
