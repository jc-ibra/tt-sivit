<?php

namespace App\Libraries;

/**
 * Envía por SMTP (desde el servidor) el correo con la tabla de solicitudes.
 * Mismo asunto, saludo y tabla que armaba el navegador (mailTable en sivit.js).
 * Los datos del servidor de correo van en el .env (email.*).
 */
class CorreoSolicitudes
{
    /** [encabezado, campo, ¿importe?] en el orden del correo original. */
    private const COLS = [
        ['#', 'n', false], ['FECHA', 'fecha', false], ['CORDINADOR', 'coordinador', false], ['REGIONAL', 'regional', false],
        ['INGENIERO', 'nombre', false], ['NUMERO DE NOMINA', 'nomina', false], ['ESTATUS IDC', 'estatus', false],
        ['INTERNO O EXTERNO', 'tipo', false], ['FOLIO GP', 'folio_gp', false], ['CLIENTE', 'cliente', false], ['CC', 'cc', false],
        ['L/F', 'lf', false], ['TRASLADO', 'traslado', false], ['TRAN PUBLICO ($)', 'tran_publico', true], ['TAXIS ($)', 'taxis', true],
        ['JUSTIFICACION DE USO DE TAXI', 'just_taxi', false], ['AUTOBÚS ($)', 'autobus', true], ['COMIDA ($)', 'comida', true],
        ['HOTEL ($)', 'hotel', true], ['OTROS ($)', 'otros', true], ['CONCEPTO DE OTROS', 'concepto_otros', false],
        ['COMENTARIOS', 'comentarios', false], ['VIATICO $', 'total', true], ['OK', 'ok', false],
    ];

    /** Columnas con encabezado amarillo (como en el formato original). */
    private const AMARILLAS = ['lf', 'traslado', 'tran_publico', 'taxis', 'autobus', 'comida', 'hotel', 'otros', 'comentarios'];

    public const MAX_DESTINATARIOS = 20;

    /** ¿Hay servidor de correo configurado en el .env? */
    public static function configurado(): bool
    {
        $c = config('Email');
        // con usuario SMTP pero sin contraseña todavía no está listo (fallaría siempre)
        return $c->fromEmail !== '' && ($c->protocol !== 'smtp' || ($c->SMTPHost !== '' && ($c->SMTPUser === '' || $c->SMTPPass !== '')));
    }

    /**
     * "a@x.mx, b@x.mx; c@x.mx" → [válidos, inválidos]. Sin repetidos.
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function destinatarios(string $texto): array
    {
        $ok  = [];
        $mal = [];
        foreach (preg_split('/[,;\s]+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY) as $d) {
            if (filter_var($d, FILTER_VALIDATE_EMAIL)) {
                $ok[mb_strtolower($d)] = $d;
            } else {
                $mal[] = $d;
            }
        }
        return [array_values($ok), $mal];
    }

    /**
     * Envía el correo. Devuelve null si salió bien o el detalle técnico del error (para el log).
     * @param list<array>     $rows solicitudes (filas de la BD)
     * @param array<int,int>  $cons id → consecutivo visible
     * @param list<string>    $para
     */
    public function enviar(array $rows, array $cons, array $para, array $usuario): ?string
    {
        $cfg = config('Email');
        [$asunto, $html, $texto] = $this->armar($rows, $cons, $usuario);

        $email = service('email');
        $email->clear(true);
        $email->setFrom($cfg->fromEmail, $cfg->fromName !== '' ? $cfg->fromName : 'SIVIT');
        $email->setTo($para);
        $email->setSubject($asunto);
        $email->setMailType('html');
        $email->setMessage($html);
        $email->setAltMessage($texto);
        if ($email->send(false)) {
            return null;
        }
        // solo encabezados: el debugger completo podría incluir el cuerpo
        return trim(strip_tags((string) $email->printDebugger(['headers'])));
    }

    /** @return array{0: string, 1: string, 2: string} asunto, HTML y texto plano */
    public function armar(array $rows, array $cons, array $usuario): array
    {
        helper('sivit');
        $fechas = array_unique(array_column($rows, 'fecha'));
        sort($fechas);
        $rango = count($fechas) === 1 ? dmy($fechas[0]) : dmy($fechas[0]) . ' al ' . dmy(end($fechas));
        $n     = count($rows);
        $s     = $n > 1 ? 'es' : '';
        $total = array_sum(array_map(static fn ($r) => (float) $r['total'], $rows));

        $asunto = "Solicitud de viáticos {$rango} ({$n} solicitud{$s})";
        $intro  = "Se envían las solicitudes de viáticos del {$rango}: {$n} solicitud{$s}, total " . dinero($total) . '.';
        $p      = '<p style="font-family:Tahoma,Arial,sans-serif;font-size:10pt">';
        $html   = $p . 'Buen día,<br><br>' . esc($intro) . '</p>' . $this->tabla($rows, $cons, $total)
            . $p . 'Saludos.<br><br><span style="color:#666666;font-size:8pt">Enviado desde SIVIT por ' . esc($usuario['nombre']) . '.</span></p>';

        $lineas = array_map(fn ($r) => '#' . ($cons[(int) $r['id']] ?? '') . ' | ' . dmy($r['fecha']) . ' | ' . $r['nombre'] . ' - ' . $r['nomina']
            . ' | ' . $r['cliente'] . ' (CC ' . $r['cc'] . ') | ' . $r['lf'] . ' | ' . dinero($r['total']) . ' | ' . $r['ok'], $rows);
        $texto = "Buen día,\n\n{$intro}\n\n" . implode("\n", $lineas) . "\n\nSaludos.\n\nEnviado desde SIVIT por {$usuario['nombre']}.";

        return [$asunto, $html, $texto];
    }

    private function tabla(array $rows, array $cons, float $total): string
    {
        $th  = 'style="background:%s;color:%s;font-family:Tahoma,Arial,sans-serif;font-size:9pt;font-weight:bold;padding:5px 8px;border:1px solid #163a5c;text-align:center;white-space:nowrap"';
        $td  = static fn (bool $der) => 'style="font-family:Tahoma,Arial,sans-serif;font-size:9pt;padding:4px 8px;border:1px solid #bfbfbf;white-space:nowrap;' . ($der ? 'text-align:right;' : '') . '"';
        $out = '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse"><thead><tr>';
        foreach (self::COLS as [$titulo, $k]) {
            $out .= '<th nowrap ' . (in_array($k, self::AMARILLAS, true) ? sprintf($th, '#FFFF99', '#000000') : sprintf($th, '#1F4E79', '#ffffff')) . '>' . esc($titulo) . '</th>';
        }
        $out .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $out .= '<tr>';
            foreach (self::COLS as [, $k, $money]) {
                $v = match (true) {
                    $k === 'n'         => (string) ($cons[(int) $r['id']] ?? ''),
                    $k === 'fecha'     => dmy($r['fecha']),
                    $k === 'just_taxi' => just_taxi_texto($r),
                    $money             => (float) $r[$k] ? dinero($r[$k]) : '',
                    default            => (string) ($r[$k] ?? ''),
                };
                $out .= '<td nowrap ' . $td($money || $k === 'n') . '>' . esc($v) . '</td>';
            }
            $out .= '</tr>';
        }
        $out .= '<tr><td colspan="' . (count(self::COLS) - 2) . '" ' . $td(true) . '><b>TOTAL (' . count($rows) . ')</b></td><td '
            . $td(true) . '><b>' . dinero($total) . '</b></td><td ' . $td(false) . '></td></tr></tbody></table>';
        return $out;
    }
}
