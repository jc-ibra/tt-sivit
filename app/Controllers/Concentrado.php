<?php

namespace App\Controllers;

use App\Models\CentroCostoModel;
use App\Models\EmpleadoModel;
use App\Models\SolicitudModel;
use App\Models\UsuarioModel;

class Concentrado extends BaseController
{
    private const LIMITE = 5000;

    private function filtros(): array
    {
        $g = $this->request->getGet();
        $f = [];
        foreach (['desde', 'hasta'] as $k) {
            $v = (string) ($g[$k] ?? '');
            $f[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        }
        foreach (['cliente', 'coord', 'lf', 'ok', 'q'] as $k) {
            $f[$k] = trim((string) ($g[$k] ?? ''));
        }
        return $f;
    }

    private function consulta(array $u, array $f, string $orden, int $dir): array
    {
        $m   = model(SolicitudModel::class);
        $col = SolicitudModel::ORDENABLES[$orden] ?? 'fecha';
        $q   = $m->visiblesPara($u)->filtrar($f);
        // pctDep no es una columna: se ordena por real_dep/total
        $q->orderBy($col, $dir > 0 ? 'ASC' : 'DESC')->orderBy('id', 'DESC');
        return $q->findAll(self::LIMITE);
    }

    public function index()
    {
        $u   = service('auth')->usuario();
        $f   = $this->filtros();
        $ord = (string) $this->request->getGet('orden') ?: 'fecha';
        $dir = $this->request->getGet('dir') === 'asc' ? 1 : -1;
        if (! isset(SolicitudModel::ORDENABLES[$ord])) {
            $ord = 'fecha';
        }
        $cons = model(SolicitudModel::class)->consecutivos($u);
        $list = $this->consulta($u, $f, $ord, $dir);
        return view('concentrado/index', [
            'yo' => $u, 'tab' => 'list', 'filtros' => $f, 'orden' => $ord, 'dir' => $dir, 'list' => $list, 'cons' => $cons,
            'conteo' => count($cons),
            'clientes' => array_keys(model(CentroCostoModel::class)->mapa()),
            'coordinadores' => array_values(array_unique(array_filter(array_column(model(EmpleadoModel::class)->findAll(), 'coordinador')))),
            'usuarios' => model(UsuarioModel::class)->paraPanel(),
            'correo' => $u['mail_destino'] ?: config('Sivit')->correoPredeterminado,
        ]);
    }

    /** CSV con las mismas columnas que el botón "Descargar para Excel" original (con BOM para Excel). */
    public function csv()
    {
        $u   = service('auth')->usuario();
        $f   = $this->filtros();
        $ord = (string) $this->request->getGet('orden') ?: 'fecha';
        $dir = $this->request->getGet('dir') === 'asc' ? 1 : -1;
        $cons = model(SolicitudModel::class)->consecutivos($u);
        $list = $this->consulta($u, $f, $ord, $dir);

        $cab = ['#', 'FECHA', 'COORDINADOR', 'REGIONAL', 'INGENIERO', 'NUMERO DE NOMINA', 'ESTATUS IDC', 'INTERNO O EXTERNO', 'FOLIO GP', 'CLIENTE', 'CC', 'L/F', 'TRASLADO',
            'TRAN PUBLICO ($)', 'TAXIS ($)', 'JUSTIFICACION DE USO DE TAXI', 'AUTOBUS ($)', 'COMIDA ($)', 'HOTEL ($)', 'OTROS ($)', 'CONCEPTO DE OTROS', 'COMENTARIOS',
            'VIATICO $', 'OK', 'ENVIADO', '¿DEPOSITADO COMPLETO?', 'VIATICO REAL DEPOSITADO', '% DEPOSITADO'];
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $cab, ',', '"', '');
        foreach ($list as $r) {
            $pct = SolicitudModel::pctDep($r);
            fputcsv($out, [
                $cons[(int) $r['id']] ?? '', dmy($r['fecha']), $r['coordinador'], $r['regional'], $r['nombre'], $r['nomina'], $r['estatus'], $r['tipo'], $r['folio_gp'],
                $r['cliente'], $r['cc'], $r['lf'], $r['traslado'], $r['tran_publico'], $r['taxis'], $r['just_taxi'], $r['autobus'], $r['comida'], $r['hotel'], $r['otros'],
                $r['concepto_otros'], $r['comentarios'], $r['total'], $r['ok'], fecha_hora($r['enviado_en']),
                ['si' => 'SI', 'no' => 'NO'][SolicitudModel::depEstado($r)] ?? '', $r['real_dep'] ?? '', $pct === null ? '' : round($pct, 1) . '%',
            ], ',', '"', '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="SIVIT_viaticos_' . date('Y-m-d') . '.csv"')->setBody($csv);
    }
}
