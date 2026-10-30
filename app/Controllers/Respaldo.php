<?php

namespace App\Controllers;

use App\Models\CatalogoActualizacionModel;
use App\Models\CentroCostoModel;
use App\Models\EmpleadoModel;
use App\Models\SolicitudModel;
use App\Models\UsuarioModel;

/** Respaldo completo en un solo .json con el mismo formato del sistema anterior (sin contraseñas). */
class Respaldo extends BaseController
{
    private function ms(?string $dt): ?int
    {
        return $dt ? strtotime($dt) * 1000 : null;
    }

    public function descargar()
    {
        $users = array_map(fn ($u) => [
            'id' => $u['legacy_id'] ?: 'u' . $u['id'], 'nombre' => $u['nombre'], 'rol' => $u['rol'], 'hash' => null, 'salt' => null,
            'foto' => $u['foto'] ? 'data:image/jpeg;base64,' . $u['foto'] : null, 'color' => (int) $u['color'], 'tema' => $u['tema'],
        ], model(UsuarioModel::class)->findAll());
        $uid = [];
        foreach (model(UsuarioModel::class)->withDeleted()->findAll() as $u) {
            $uid[$u['id']] = $u['legacy_id'] ?: 'u' . $u['id'];
        }
        $rows = array_map(fn ($r) => [
            'id' => $r['legacy_id'] ?: 's' . $r['id'], 'usuarioId' => $uid[$r['usuario_id']] ?? null, 'fecha' => $r['fecha'], 'nomina' => $r['nomina'],
            'nombre' => $r['nombre'], 'coordinador' => $r['coordinador'], 'regional' => $r['regional'], 'estatus' => $r['estatus'], 'tipo' => $r['tipo'],
            'folioGP' => $r['folio_gp'], 'cliente' => $r['cliente'], 'cc' => $r['cc'], 'lf' => $r['lf'], 'traslado' => $r['traslado'],
            'tranPublico' => (float) $r['tran_publico'], 'taxis' => (float) $r['taxis'], 'justTaxi' => $r['just_taxi'], 'autobus' => (float) $r['autobus'],
            'comida' => (float) $r['comida'], 'hotel' => (float) $r['hotel'], 'otros' => (float) $r['otros'], 'conceptoOtros' => $r['concepto_otros'],
            'comentarios' => $r['comentarios'], 'total' => (float) $r['total'], 'ok' => $r['ok'], 'creadoPor' => $r['creado_por'], 'creadoEn' => $this->ms($r['created_at']),
            'editadoPor' => $r['editado_por'], 'editadoEn' => $r['editado_por'] ? $this->ms($r['updated_at']) : null,
            'enviadoEn' => $this->ms($r['enviado_en']), 'enviadoA' => $r['enviado_a'], 'depCompleto' => $r['dep_completo'],
            'realDep' => $r['real_dep'] === null ? null : (float) $r['real_dep'], 'realDepPor' => $r['real_dep_por'], 'realDepEn' => $this->ms($r['real_dep_en']),
        ], model(SolicitudModel::class)->findAll());
        $ult = model(CatalogoActualizacionModel::class)->ultima();
        $cat = [
            'emp' => array_map(fn ($e) => [$e['nombre'], $e['nomina'], $e['estatus'], $e['coordinador'], $e['regional']], model(EmpleadoModel::class)->orderBy('nombre')->findAll()),
            'cc'  => array_map(fn ($c, $k) => [$k, $c], model(CentroCostoModel::class)->mapa(), array_keys(model(CentroCostoModel::class)->mapa())),
            'info' => $ult ? ['t' => $this->ms($ult['created_at']), 'por' => $ult['por']] : null,
        ];
        $json = json_encode(['app' => 'SIVIT', 'v' => 1, 'guardado' => time() * 1000, 'users' => $users, 'rows' => $rows, 'catalogo' => $cat], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        return $this->response->setHeader('Content-Type', 'application/json')
            ->setHeader('Content-Disposition', 'attachment; filename="SIVIT_respaldo_' . date('Y-m-d') . '.json"')->setBody($json);
    }
}
