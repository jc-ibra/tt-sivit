<?php

namespace App\Libraries;

use App\Models\CentroCostoModel;
use App\Models\EmpleadoModel;
use Config\Sivit;

/**
 * Validación y cálculos de una solicitud (port de collect/lookup/recalc del HTML original).
 * El servidor es quien decide: lo que calcula el navegador es solo una vista previa.
 */
class SolicitudReglas
{
    private Sivit $cfg;

    public function __construct(?Sivit $cfg = null)
    {
        $this->cfg = $cfg ?? config('Sivit');
    }

    /** Quita espacios de cualquier tipo (incluido el no separable \u00A0 que traen algunos Excel) de los extremos. */
    public static function limpiar($v): string
    {
        return (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', (string) $v);
    }

    /** Nómina normalizada: sin espacios en ningún lado, sin ".0" final de Excel. */
    public static function limpiarNomina($v): string
    {
        return (string) preg_replace('/[\s\x{00A0}]+/u', '', (string) preg_replace('/\.0+$/', '', self::limpiar($v)));
    }

    public static function sinAcento(?string $t): string
    {
        $t = trim((string) $t);
        $n = \Normalizer::normalize($t, \Normalizer::FORM_D);
        return mb_strtoupper(preg_replace('/\p{Mn}/u', '', $n === false ? $t : $n));
    }

    /** Devuelve el elemento de la lista que coincide sin importar mayúsculas ni acentos, o ''. */
    public static function deLista(?string $v, array $lista): string
    {
        $k = self::sinAcento($v);
        if ($k === '') {
            return '';
        }
        foreach ($lista as $x) {
            if (self::sinAcento($x) === $k) {
                return $x;
            }
        }
        return '';
    }

    /** "NOMBRE - 9313", "9313" o un nombre libre → datos del empleado (INTERNO) o proveedor (EXTERNO). */
    public function buscarEmpleado(string $raw): ?array
    {
        $raw = mb_strtoupper(self::limpiar($raw));
        if ($raw === '') {
            return null;
        }
        $m   = [];
        $key = self::limpiarNomina(preg_match('/-\s*(\S+)\s*$/u', $raw, $m) ? $m[1] : $raw);
        $e   = model(EmpleadoModel::class)->porNomina($key);
        if ($e) {
            return ['nombre' => $e['nombre'], 'num' => $e['nomina'], 'est' => $e['estatus'], 'coord' => $e['coordinador'], 'reg' => $e['regional'], 'tipo' => 'INTERNO'];
        }
        return [
            'nombre' => $m ? self::limpiar(preg_replace('/\s*-\s*\S+\s*$/u', '', $raw)) : 'NO ESTA EN BASE',
            'num' => $key, 'est' => '', 'coord' => '', 'reg' => '', 'tipo' => 'EXTERNO (PROVEEDOR)',
        ];
    }

    public static function numero($v): float
    {
        $v = preg_replace('/[$,\s]/', '', (string) $v);
        return is_numeric($v) ? (float) $v : 0.0;
    }

    /**
     * @param array $in      datos del formulario (POST)
     * @param string|null $justPrevia justificación ya guardada (al editar se acepta aunque ya no esté en la lista)
     * @return array{errs: list<string>, rec: array}
     */
    public function validar(array $in, ?string $justPrevia = null): array
    {
        $errs = [];
        $cfg  = $this->cfg;
        $s    = static fn ($k) => trim((string) ($in[$k] ?? ''));

        $fecha = $s('fecha');
        if ($fecha === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || ! checkdate((int) substr($fecha, 5, 2), (int) substr($fecha, 8, 2), (int) substr($fecha, 0, 4))) {
            $errs[] = 'Escribe la fecha.';
        }
        $e = $this->buscarEmpleado($s('nomina'));
        if (! $e) {
            $errs[] = 'Escribe el número de nómina.';
        }
        if ($s('folio') === '') {
            $errs[] = 'Escribe el Folio GP. Si no hay folio, pon 0000.';
        }
        $ccs     = model(CentroCostoModel::class)->mapa();
        $cliente = self::deLista($s('cliente'), array_keys($ccs));
        if ($cliente === '') {
            $errs[] = $s('cliente') !== '' ? 'El cliente que escribiste no está en la lista: elígelo de las opciones que salen al escribir.' : 'Escribe el cliente.';
        }
        $concepto = self::deLista($s('conceptoOtros'), $cfg->conceptosOtros);
        if ($s('conceptoOtros') !== '' && $concepto === '') {
            $errs[] = 'El concepto de OTROS no está en la lista: elígelo de las opciones que salen al escribir (o usa OTROS).';
        }
        $lf = $s('lf');
        if (! in_array($lf, ['L', 'F'], true)) {
            $errs[] = 'Elige L (local) o F (foráneo).';
        }
        if ($s('traslado') === '') {
            $errs[] = 'Escribe la descripción del TRASLADO (campo obligatorio).';
        }

        $nombresMoney = ['tran_publico' => 'tranPublico', 'taxis' => 'taxis', 'autobus' => 'autobus', 'comida' => 'comida', 'hotel' => 'hotel', 'otros' => 'otros'];
        $monto = [];
        foreach ($nombresMoney as $col => $campo) {
            $raw = $s($campo);
            if ($raw !== '' && (! is_numeric(preg_replace('/[$,\s]/', '', $raw)) || self::numero($raw) < 0)) {
                $errs[] = 'Revisa el importe de ' . ($campo === 'tranPublico' ? 'transporte público' : $campo) . ': solo números en pesos.';
            }
            $monto[$col] = round(self::numero($raw), 2);
        }

        $just = self::deLista($s('justTaxi'), $cfg->justificacionesTaxi);
        if ($just === '' && $s('justTaxi') !== '' && $justPrevia !== null && mb_strtoupper($s('justTaxi')) === mb_strtoupper($justPrevia)) {
            $just = mb_strtoupper($s('justTaxi'));
        }
        if ($monto['taxis'] > 0 && $just === '') {
            $errs[] = $s('justTaxi') !== '' ? 'La justificación de taxi que escribiste no está en la lista: elígela de las opciones que salen al escribir.' : 'Escribe la justificación del uso de taxi.';
        }
        if ($monto['taxis'] <= 0 && $s('justTaxi') !== '') {
            $errs[] = 'Escribiste una justificación de taxi pero no hay cantidad en TAXIS ($). Escribe el importe o borra la justificación.';
        }
        if ($monto['otros'] > 0 && $s('conceptoOtros') === '') {
            $errs[] = 'Hay cantidad en OTROS ($): elige el concepto (renta de escalera, gasolina, etc.).';
        }
        if ($monto['otros'] > 0 && $s('comentarios') === '') {
            $errs[] = 'Hay cantidad en OTROS ($): escribe un comentario que explique el gasto.';
        }
        if ($monto['otros'] <= 0 && $s('conceptoOtros') !== '') {
            $errs[] = 'Elegiste un concepto de OTROS pero no hay cantidad en OTROS ($).';
        }
        if ($errs) {
            return ['errs' => $errs, 'rec' => []];
        }

        $total = array_sum($monto);
        $rec   = [
            'fecha' => $fecha, 'nomina' => $e['num'], 'nombre' => $e['nombre'], 'coordinador' => $e['coord'], 'regional' => $e['reg'],
            'estatus' => $e['est'], 'tipo' => $e['tipo'],
            'folio_gp' => mb_strtoupper($s('folio')), 'cliente' => $cliente, 'cc' => (string) ($ccs[$cliente] ?? 'SIN CC'), 'lf' => $lf,
            'traslado' => mb_strtoupper($s('traslado')), 'just_taxi' => $just, 'concepto_otros' => $concepto,
            'comentarios' => mb_strtoupper($s('comentarios')),
        ] + $monto + [
            'total' => round($total, 2),
            'ok'    => $e['tipo'] === 'INTERNO' && ! in_array($e['est'], $cfg->estatusRevisar, true) ? 'OK' : 'REVISAR',
        ];
        return ['errs' => [], 'rec' => $rec];
    }
}
