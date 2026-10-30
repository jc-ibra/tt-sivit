<?php

namespace Tests\Support;

use App\Libraries\Auth;
use App\Models\CentroCostoModel;
use App\Models\EmpleadoModel;
use App\Models\SolicitudModel;
use App\Models\UsuarioModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

abstract class SivitTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    protected array $u = [];

    protected function setUp(): void
    {
        parent::setUp();
        $m = model(UsuarioModel::class);
        foreach ([
            'op'   => ['OPERADOR VIRTUAL', 'operador'], 'c1' => ['COORDINADOR UNO', 'coordinador'],
            'c2'   => ['COORDINADOR DOS', 'coordinador'], 'vi' => ['VIATICO UNO', 'viatico'],
        ] as $k => [$n, $rol]) {
            $id = $m->insert(['nombre' => $n, 'rol' => $rol, 'password_hash' => Auth::hash('clave1234')]);
            $this->u[$k] = $m->find($id);
        }
        model(EmpleadoModel::class)->insertBatch([
            ['nomina' => '9313', 'nombre' => 'ABREGO RAMIREZ ROLANDO', 'estatus' => 'ACTIVO', 'coordinador' => 'VAQUERO', 'regional' => 'DTN'],
            ['nomina' => '9388', 'nombre' => 'BEZA SAUCEDO ALEJANDRO', 'estatus' => 'BAJA', 'coordinador' => 'VAQUERO', 'regional' => 'DTN'],
        ]);
        model(CentroCostoModel::class)->insertBatch([['cliente' => 'ACTINVER MANTENIMIENTOS', 'cc' => '302'], ['cliente' => 'AFIRME MANTENIMIENTOS', 'cc' => '303']]);
    }

    /** Datos válidos de una solicitud del formulario. */
    protected function datos(array $extra = []): array
    {
        return $extra + ['fecha' => '2026-10-29', 'nomina' => 'ABREGO RAMIREZ ROLANDO - 9313', 'folio' => 'gp1', 'cliente' => 'actinver mantenimientos',
            'lf' => 'L', 'traslado' => 'casa a cliente', 'comida' => '100', 'hotel' => '50.5'];
    }

    protected function como(string $k): static
    {
        return $this->withSession(['usuario_id' => (string) $this->u[$k]['id']]);
    }

    /** POST con sesión del usuario y token CSRF válido (el filtro CSRF queda activo, como en producción). */
    protected function enviar(string $k, string $ruta, array $datos = [])
    {
        $tok = str_repeat('a', 32);
        \Config\Services::resetSingle('security');   // el servicio guarda el token de la petición anterior
        return $this->withSession(['usuario_id' => (string) $this->u[$k]['id'], 'csrf_test_name' => $tok])->post($ruta, $datos + ['csrf_test_name' => $tok]);
    }

    protected function crear(string $dueno, array $extra = []): int
    {
        $res = (new \App\Libraries\SolicitudReglas())->validar($this->datos($extra));
        $this->assertSame([], $res['errs']);
        return model(SolicitudModel::class)->insert($res['rec'] + ['usuario_id' => $this->u[$dueno]['id'], 'creado_por' => $this->u[$dueno]['nombre']]);
    }
}
