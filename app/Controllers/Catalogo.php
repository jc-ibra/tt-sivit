<?php

namespace App\Controllers;

use App\Libraries\CatalogoImport;
use App\Models\BitacoraModel;
use App\Models\CatalogoActualizacionModel;
use App\Models\CentroCostoModel;
use App\Models\EmpleadoModel;
use App\Models\UsuarioModel;

/** Actualizar IDs y centros de costos (solo operador; ver filtro 'rol:operador' en Routes). */
class Catalogo extends BaseController
{
    private function pagina(array $extra = [])
    {
        $u = service('auth')->usuario();
        return view('catalogo/index', $extra + [
            'yo' => $u, 'tab' => 'cat', 'conteo' => model(\App\Models\SolicitudModel::class)->visiblesPara($u)->countAllResults(),
            'usuarios' => model(UsuarioModel::class)->paraPanel(),
            'nEmp' => model(EmpleadoModel::class)->countAllResults(), 'nCc' => model(CentroCostoModel::class)->countAllResults(),
            'ultima' => model(CatalogoActualizacionModel::class)->ultima(),
            'previa' => null,
        ]);
    }

    public function index()
    {
        return $this->pagina();
    }

    public function previa()
    {
        $archivos = $this->request->getFileMultiple('archivos') ?: [];
        $imp = new CatalogoImport();
        $tablas = [];
        try {
            foreach ($archivos as $f) {
                if (! $f->isValid() || ! preg_match('/\.(xlsx|xlsm|xls|csv)$/i', $f->getClientName()) || $f->getSizeByUnit('mb') > 10) {
                    continue;
                }
                $tablas = array_merge($tablas, $imp->leerTablas($f->getRealPath(), $f->getClientName()));
            }
        } catch (\Throwable $e) {
            log_message('error', 'Catálogo: ' . $e->getMessage());
            return $this->pagina(['error' => 'No se pudo leer el archivo: ' . $e->getMessage()]);
        }
        $plan = $imp->plan($tablas);
        if ($plan['emp'] === null && $plan['cc'] === null) {
            return $this->pagina(['error' => 'No encontré columnas reconocibles. Para IDs: NOMBRE y NUMERO DE NOMINA (y si los tienes, ESTATUS, COORDINADOR, REGIONAL). Para centros de costos: CLIENTE y CC.']);
        }
        session()->set('catalogo_plan', $plan);   // se aplica solo si el operador confirma
        return $this->pagina(['previa' => ['plan' => $plan, 'ch' => $imp->comparar($plan)]]);
    }

    public function aplicar()
    {
        $plan = session()->get('catalogo_plan');
        if (! $plan) {
            return redirect()->to('catalogo')->with('error', 'La vista previa venció; sube el archivo otra vez.');
        }
        $modo = $this->request->getPost('modo') === 'parcial' ? 'parcial' : 'completo';
        $u    = service('auth')->usuario();
        $r    = (new CatalogoImport())->aplicar($plan, $modo, $u);
        session()->remove('catalogo_plan');
        if (! $r['ok']) {
            return redirect()->to('catalogo')->with('error', 'No se pudo guardar el catálogo; no se hizo ningún cambio.');
        }
        model(BitacoraModel::class)->registrar($u['id'], 'catalogo', 'catalogo', null, null, ['modo' => $modo] + $r);
        return redirect()->to('catalogo')->with('ok', "Catálogo actualizado: {$r['empleados']} IDs · {$r['cc']} centros de costos");
    }

    /** Descarga los IDs o los CC actuales en CSV (como "Descargar IDs actuales" / "CC actuales"). */
    public function csv(string $que)
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        if ($que === 'ids') {
            fputcsv($out, ['NOMBRE', 'NUMERO DE NOMINA', 'ESTATUS IDC', 'COORDINADOR', 'REGIONAL'], ',', '"', '');
            foreach (model(EmpleadoModel::class)->orderBy('nombre')->findAll() as $e) {
                fputcsv($out, [$e['nombre'], $e['nomina'], $e['estatus'], $e['coordinador'], $e['regional']], ',', '"', '');
            }
            $nombre = 'SIVIT_IDs.csv';
        } else {
            fputcsv($out, ['CLIENTE', 'CC'], ',', '"', '');
            foreach (model(CentroCostoModel::class)->mapa() as $c => $cc) {
                fputcsv($out, [$c, $cc], ',', '"', '');
            }
            $nombre = 'SIVIT_centros_de_costos.csv';
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nombre . '"')->setBody($csv);
    }
}
