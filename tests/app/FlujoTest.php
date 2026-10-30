<?php

namespace Tests\App;

use Tests\Support\SivitTestCase;

use App\Libraries\Auth;
use App\Libraries\CatalogoImport;
use App\Models\CentroCostoModel;
use App\Models\EmpleadoModel;
use App\Models\SolicitudModel;
use App\Models\UsuarioModel;

final class FlujoTest extends SivitTestCase
{
    public function testSinSesionRedirigeAlInicio(): void
    {
        $this->get('solicitudes')->assertRedirectTo('/');
        $this->get('concentrado')->assertRedirectTo('/');
        $this->get('indicadores')->assertRedirectTo('/');
        $this->get('respaldo')->assertRedirectTo('/');
    }

    public function testPostSinTokenCsrfEsRechazado(): void
    {
        // CIUnitTestCase desactiva CSRF en pruebas; aquí se verifica que el filtro esté en la lista global
        $f = config('Filters');
        $this->assertContains('csrf', $f->globals['before']);
        $this->assertContains('secureheaders', $f->globals['after']);
        $this->assertSame('session', config('Security')->csrfProtection);
    }

    public function testLoginMigraHashViejoSha256(): void
    {
        $m = model(UsuarioModel::class);
        $salt = 'sal123';
        $id = $m->insert(['nombre' => 'VIEJO', 'rol' => 'coordinador', 'legacy_salt' => $salt, 'legacy_hash' => hash('sha256', $salt . 'miClave')]);
        $auth = new Auth();
        $this->assertFalse($auth->verificar($m->find($id), 'otra'));
        $this->assertTrue($auth->verificar($m->find($id), 'miClave'));
        $n = $m->find($id);
        $this->assertNotEmpty($n['password_hash']);
        $this->assertNull($n['legacy_hash']);
        $this->assertTrue((new Auth())->verificar($n, 'miClave'));
    }

    /** En producción esto es una página 404; en las pruebas el framework lanza la excepción. */
    private function assertNoEncontrada(callable $f): void
    {
        try {
            $r = $f();
            $this->assertSame(404, $r->response()->getStatusCode());
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testCoordinadorSoloVeEditaYBorraLoSuyo(): void
    {
        $mia = $this->crear('c1');
        $ajena = $this->crear('c2');
        $this->como('c1')->get("solicitudes/$mia/editar")->assertStatus(200);
        $this->assertNoEncontrada(fn () => $this->como('c1')->get("solicitudes/$ajena/editar"));
        $this->assertNoEncontrada(fn () => $this->enviar('c1', "solicitudes/$ajena/eliminar"));
        $this->assertNotNull(model(SolicitudModel::class)->find($ajena));
        $this->enviar('c1', "solicitudes/$mia/eliminar");
        $this->assertNull(model(SolicitudModel::class)->find($mia));
        $this->assertNotNull(model(SolicitudModel::class)->withDeleted()->find($mia)['deleted_at']);   // borrado lógico
    }

    public function testViaticoVeTodoPeroNoElimina(): void
    {
        $s = $this->crear('c1');
        $this->como('vi')->get("solicitudes/$s/editar")->assertStatus(200);
        $this->enviar('vi', "solicitudes/$s/eliminar");
        $this->assertNotNull(model(SolicitudModel::class)->find($s));
    }

    public function testOperadorVeTodoYElimina(): void
    {
        $s = $this->crear('c1');
        $this->enviar('op', "solicitudes/$s/eliminar");
        $this->assertNull(model(SolicitudModel::class)->find($s));
    }

    public function testGuardarCalculaEnServidorIgnorandoLoQueMandeElNavegador(): void
    {
        $this->enviar('c1', 'solicitudes', $this->datos(['total' => '1', 'ok' => 'OK', 'usuario_id' => $this->u['c2']['id']]));
        $s = model(SolicitudModel::class)->first();
        $this->assertEquals(150.5, $s['total']);
        $this->assertEquals($this->u['c1']['id'], $s['usuario_id']);   // el dueño sale de la sesión, no del formulario
        $this->assertSame('COORDINADOR UNO', $s['creado_por']);
    }

    public function testSoloElOperadorGestionaPerfilesYCatalogo(): void
    {
        $this->como('c1')->get('catalogo')->assertStatus(403);
        $this->como('c1')->get('respaldo')->assertStatus(403);
        $this->como('vi')->get('catalogo')->assertStatus(403);
        $this->enviar('c1', 'usuarios', ['nombre' => 'NUEVO', 'clave' => 'abcd', 'clave2' => 'abcd'])->assertStatus(403);
        $this->enviar('c1', 'usuarios/' . $this->u['c2']['id'], ['tema' => '#1f4e79'])->assertStatus(403);
        $this->como('op')->get('catalogo')->assertStatus(200);
    }

    public function testOperadorDaDeAltaYElimina(): void
    {
        $this->enviar('op', 'usuarios', ['nombre' => 'nuevo coord', 'rol' => 'coordinador', 'clave' => 'abcd', 'clave2' => 'abcd']);
        $n = model(UsuarioModel::class)->where('nombre', 'NUEVO COORD')->first();
        $this->assertNotNull($n);
        $this->assertTrue(password_verify('abcd', $n['password_hash']));
        $this->enviar('op', 'usuarios', ['nombre' => 'NUEVO COORD', 'clave' => 'abcd', 'clave2' => 'abcd']);   // repetido
        $this->assertSame(1, model(UsuarioModel::class)->where('nombre', 'NUEVO COORD')->countAllResults());
        $this->enviar('op', 'usuarios', ['nombre' => 'CORTO', 'clave' => 'ab', 'clave2' => 'ab']);
        $this->assertNull(model(UsuarioModel::class)->where('nombre', 'CORTO')->first());
        $this->enviar('op', 'usuarios/' . $n['id'] . '/eliminar');
        $this->assertNull(model(UsuarioModel::class)->find($n['id']));
    }

    public function testDepositoYCalculoDeEstado(): void
    {
        $s = $this->crear('c1');   // total 150.50
        $r = $this->enviar('c1', "solicitudes/$s/deposito", ['accion' => 'si']);
        $j = json_decode($r->getJSON(), true);
        $this->assertSame('si', $j['estado']);
        $this->assertEquals(150.5, $j['real_dep']);
        $j = json_decode($this->enviar('c1', "solicitudes/$s/deposito", ['accion' => 'no'])->getJSON(), true);
        $this->assertSame('no', $j['estado']);
        $this->assertNull($j['real_dep']);   // si coincidía con el total, se limpia para obligar a capturar
        $j = json_decode($this->enviar('c1', "solicitudes/$s/deposito", ['accion' => 'monto', 'valor' => '$100.25'])->getJSON(), true);
        $this->assertEqualsWithDelta(66.6, $j['pct'], 0.1);
        $this->enviar('c1', "solicitudes/$s/deposito", ['accion' => 'monto', 'valor' => 'abc'])->assertStatus(422);
        $otra = $this->crear('c2');
        $this->enviar('c1', "solicitudes/$otra/deposito", ['accion' => 'si'])->assertStatus(404);
    }

    public function testMarcarEnviadasSoloLasVisibles(): void
    {
        $mia = $this->crear('c1');
        $ajena = $this->crear('c2');
        $j = json_decode($this->enviar('c1', 'solicitudes/marcar-enviadas', ['ids' => [(string) $mia, (string) $ajena], 'enviado_a' => 'a@b.mx'])->getJSON(), true);
        $this->assertSame(1, $j['marcadas']);
        $this->assertNotNull(model(SolicitudModel::class)->find($mia)['enviado_en']);
        $this->assertNull(model(SolicitudModel::class)->find($ajena)['enviado_en']);
    }

    public function testConcentradoFiltraPorRolYGeneraCsvConBom(): void
    {
        $this->crear('c1');
        $this->crear('c2', ['cliente' => 'AFIRME MANTENIMIENTOS']);
        $html = $this->como('c1')->get('concentrado')->response()->getBody();
        $this->assertStringContainsString('ACTINVER MANTENIMIENTOS', $html);
        $this->assertStringNotContainsString('AFIRME MANTENIMIENTOS</td>', $html);
        $csv = $this->como('op')->get('concentrado/csv')->response()->getBody();
        $this->assertStringStartsWith("\xEF\xBB\xBF#,FECHA,COORDINADOR", $csv);
        $this->assertSame(3, substr_count(trim($csv), "\n") + 1);   // encabezado + 2 filas
        // el orden solo acepta columnas conocidas: un valor raro no rompe ni inyecta SQL
        $this->como('op')->get('concentrado?orden=fecha;DROP TABLE solicitudes')->assertStatus(200);
        $this->assertSame(2, model(SolicitudModel::class)->countAllResults());
    }

    public function testIndicadoresSumanLoVisible(): void
    {
        $this->crear('c1');
        $this->crear('c2');
        $this->assertStringContainsString('$301.00', $this->como('op')->get('indicadores')->response()->getBody());
        $this->assertStringContainsString('$150.50', $this->como('c1')->get('indicadores')->response()->getBody());
    }

    public function testCatalogoCompletoMarcaBajaYParcialNo(): void
    {
        $imp = new CatalogoImport();
        $plan = ['emp' => ['9313' => ['nomina' => '9313', 'nombre' => 'ABREGO NUEVO', 'estatus' => 'ACTIVO', 'coordinador' => 'X', 'regional' => 'Y'],
                           '1' => ['nomina' => '1', 'nombre' => 'ALTA', 'estatus' => 'ACTIVO', 'coordinador' => '', 'regional' => '']],
                 'cc' => ['ACTINVER MANTENIMIENTOS' => '999', 'NUEVO CLIENTE' => '1'], 'repetidos' => 0, 'ignoradas' => []];
        $imp->aplicar($plan, 'parcial', $this->u['op']);
        $this->assertSame('BAJA', model(EmpleadoModel::class)->porNomina('9388')['estatus']);   // parcial: lo que no viene no se toca
        $this->assertSame('ABREGO NUEVO', model(EmpleadoModel::class)->porNomina('9313')['nombre']);
        $this->assertArrayHasKey('AFIRME MANTENIMIENTOS', model(CentroCostoModel::class)->mapa());
        $this->assertSame('999', model(CentroCostoModel::class)->mapa()['ACTINVER MANTENIMIENTOS']);

        model(EmpleadoModel::class)->update(model(EmpleadoModel::class)->porNomina('1')['id'], ['estatus' => 'ACTIVO']);
        $imp->aplicar(['emp' => ['9313' => $plan['emp']['9313']], 'cc' => ['NUEVO CLIENTE' => '1']] + $plan, 'completo', $this->u['op']);
        $this->assertSame('BAJA', model(EmpleadoModel::class)->porNomina('1')['estatus']);
        $this->assertArrayNotHasKey('AFIRME MANTENIMIENTOS', model(CentroCostoModel::class)->mapa());
    }

    public function testSolicitudesViejasNoCambianConElCatalogo(): void
    {
        $s = $this->crear('c1');
        model(EmpleadoModel::class)->update(model(EmpleadoModel::class)->porNomina('9313')['id'], ['nombre' => 'OTRO NOMBRE']);
        $this->assertSame('ABREGO RAMIREZ ROLANDO', model(SolicitudModel::class)->find($s)['nombre']);
    }
}
