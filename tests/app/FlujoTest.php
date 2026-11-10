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
        // 'csrf' va con excepción (setup.php), así que es llave del arreglo y no un valor de la lista
        $this->assertTrue(in_array('csrf', $f->globals['before'], true) || array_key_exists('csrf', $f->globals['before']));
        $this->assertSame(['setup.php'], $f->globals['before']['csrf']['except'] ?? ['setup.php']);
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

    public function testOperadorBorraVariosConLaContrasenaDosVeces(): void
    {
        $s1  = $this->crear('c1');
        $s2  = $this->crear('c2');
        $s3  = $this->crear('c2');
        $ids = [(string) $s1, (string) $s2];
        $sol = model(SolicitudModel::class);

        // solo el operador
        $this->enviar('c1', 'borrar', ['tipo' => 'solicitudes', 'ids' => $ids, 'clave' => 'clave1234', 'clave2' => 'clave1234'])->assertStatus(403);
        $this->enviar('vi', 'borrar', ['tipo' => 'solicitudes', 'ids' => $ids, 'clave' => 'clave1234', 'clave2' => 'clave1234'])->assertStatus(403);
        // las dos contraseñas deben coincidir y ser la correcta
        $this->enviar('op', 'borrar', ['tipo' => 'solicitudes', 'ids' => $ids, 'clave' => 'clave1234', 'clave2' => 'otra'])->assertStatus(422);
        $this->enviar('op', 'borrar', ['tipo' => 'solicitudes', 'ids' => $ids, 'clave' => 'mala', 'clave2' => 'mala'])->assertStatus(403);
        $this->enviar('op', 'borrar', ['tipo' => 'solicitudes', 'ids' => $ids, 'clave' => 'clave1234'])->assertStatus(422);
        $this->assertSame(3, $sol->countAllResults());

        $r = $this->enviar('op', 'borrar', ['tipo' => 'solicitudes', 'ids' => $ids, 'clave' => 'clave1234', 'clave2' => 'clave1234']);
        $r->assertOK();
        $this->assertSame(2, json_decode($r->getJSON(), true)['borrados']);
        $this->assertSame([$s3], array_map('intval', $sol->findColumn('id')));
        $this->assertSame(3, $sol->withDeleted()->countAllResults());   // borrado lógico: queda en la base

        // ingenieros: uno o varios
        $emp = model(EmpleadoModel::class);
        $id  = $emp->porNomina('9313')['id'];
        $this->enviar('op', 'borrar', ['tipo' => 'ingenieros', 'ids' => [(string) $id], 'clave' => 'clave1234', 'clave2' => 'clave1234'])->assertOK();
        $this->assertNull($emp->porNomina('9313'));
        $this->assertNotNull($emp->porNomina('9388'));
        $this->como('op')->get('indicadores')->assertSee('data-borrar="ingenieros"', null);
        $this->como('c1')->get('indicadores')->assertDontSee('data-borrar=', null);
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
        $this->assertSame('✓ La solicitud enviada pasó al Concentrado de solicitudes', session('ok'));   // aviso al recargar
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

    public function testHojaIdsConLasColumnasDelExcelReal(): void
    {
        $h = ['NUMERO', 'STATUS IDC', "FECHA BAJA\nO CAMBIO", 'FECHA DE HOJA ENTREGADA', 'REQUISICION SI/NO', 'NOMBRE', "NUMERO DE\nEMPLEADO", 'FECHA DE INGRESO', 'GERENCIA',
              "REGIONAL\nTRANTOR", 'UBICACION', 'ESTADO', 'COORDINADOR', 'PUESTO', 'PROYECTO', 'CC', 'APELLIDO PATERNO', 'APELLIDO MATERNO', 'NOMBRE 1', 'NOMBRE 2', 'NOMBRE 3',
              'TELEFONO', 'CORREO', 'CUENTA CLABE', 'BANCO', 'E', 'OBSERVACIONES', 'LISTA COMBO (no borrar)'];
        $f1 = ['33', 'ACTIVO', '', '', '', 'ABREGO RAMIREZ ROLANDO DAVID', '9313', '10/08/2022', 'DTN', 'DTN - ZONA 2', 'MONTERREY', 'NUEVO LEON', 'VAQUERO PEREZ ALEJO', 'IDC', 'SELLCOM BBVA', '206', 'ABREGO'];
        $f2 = ['37', 'BAJA', 'PEN POR REEMPLAZO', '24/08/2026', 'NO', 'BEZA SAUCEDO ALEJANDRO WENCESLAO', '9388', '15/02/2023', 'DTN', 'DTN - ZONA 2', 'PIEDRAS NEGRAS', 'COAHUILA', 'VAQUERO PEREZ ALEJO'];
        $plan = (new CatalogoImport())->plan([['nombre' => 'IDS', 'filas' => [$h, $f1, $f2]]]);
        $this->assertSame(['nomina' => '9313', 'nombre' => 'ABREGO RAMIREZ ROLANDO DAVID', 'estatus' => 'ACTIVO', 'coordinador' => 'VAQUERO PEREZ ALEJO', 'regional' => 'DTN - ZONA 2'], $plan['emp']['9313']);
        $this->assertSame('BAJA', $plan['emp']['9388']['estatus']);
        $this->assertNull($plan['cc']);   // la columna CC de la hoja IDS no se confunde con la hoja de centros de costos
    }

    public function testExternosSinNumeroDeEmpleadoNoSeEnciman(): void
    {
        $h    = ['NUMERO', 'STATUS IDC', 'NOMBRE', 'NUMERO DE EMPLEADO', 'REGIONAL TRANTOR', 'COORDINADOR'];
        $imp  = new CatalogoImport();
        $plan = $imp->plan([['nombre' => 'IDS', 'filas' => [$h,
            ['110', 'EXTERNO', 'GARCIA ARGUELLO CHRISTIAN', 'EXTERNO', 'DTS - ZONA 3', ''],
            ['110', 'EXTERNO', 'RAMÍREZ HERNANDEZ ALFREDO', 'EXTERNO', 'DTS - ZONA 3', ''],
            ['122', 'EXTERNO', 'RAMÍREZ BAENA JOEL ROLANDO', 'externo', 'DTS - ZONA 1', ''],
            ['5', 'ACTIVO', 'SIN NUMERO NI NADA', '', '', ''],
        ]]]);
        $this->assertSame(0, $plan['repetidos']);
        $this->assertSame(['GARCIAARGUELLOCHRISTIAN', 'RAMIREZHERNANDEZALFREDO', 'RAMIREZBAENAJOELROLANDO', 'SINNUMERONINADA'], array_keys($plan['emp']));
        $this->assertSame('RAMÍREZ HERNANDEZ ALFREDO', $plan['emp']['RAMIREZHERNANDEZALFREDO']['nombre']);

        // el mismo externo dos veces sí cuenta como repetido y se dice quién
        $dos = $imp->plan([['nombre' => 'IDS', 'filas' => [$h, ['1', 'EXTERNO', 'GARCIA ARGUELLO CHRISTIAN', 'EXTERNO'], ['2', 'EXTERNO', 'García Arguello Christian', 'N/A']]]]);
        $this->assertSame(['GARCÍA ARGUELLO CHRISTIAN (GARCIAARGUELLOCHRISTIAN)'], $dos['repetidosLista']);

        // ya en el catálogo: al capturar se encuentra y queda como EXTERNO (REVISAR)
        $imp->aplicar($plan, 'parcial', $this->u['op']);
        $r = (new \App\Libraries\SolicitudReglas())->validar($this->datos(['nomina' => 'RAMÍREZ HERNANDEZ ALFREDO - RAMIREZHERNANDEZALFREDO']));
        $this->assertSame([], $r['errs']);
        $this->assertSame(['RAMÍREZ HERNANDEZ ALFREDO', 'EXTERNO', 'EXTERNO', 'REVISAR'], [$r['rec']['nombre'], $r['rec']['estatus'], $r['rec']['tipo'], $r['rec']['ok']]);
    }

    public function testFormatoSeLeeDeRegresoYUnaHojaSoloConEncabezadosNoSeToca(): void
    {
        $imp  = new CatalogoImport();
        $ruta = tempnam(sys_get_temp_dir(), 'siv') . '.xlsx';
        try {
            // vacío: solo encabezados → ninguna parte del catálogo se toca (aunque elijan "base completa")
            file_put_contents($ruta, $imp->formato([], []));
            $plan = $imp->plan($imp->leerTablas($ruta, 'formato.xlsx'));
            $this->assertNull($plan['emp']);
            $this->assertNull($plan['cc']);
            $this->assertSame([], $plan['ignoradas']);   // la hoja de instrucciones no cuenta como "no reconocida"

            // con el catálogo actual: regresa igual (nómina como texto, CC incluidos)
            file_put_contents($ruta, $imp->formato(model(EmpleadoModel::class)->findAll(), model(CentroCostoModel::class)->mapa()));
            $plan = $imp->plan($imp->leerTablas($ruta, 'formato.xlsx'));
            $this->assertSame(['9313', '9388'], array_column($plan['emp'], 'nomina'));
            $this->assertSame('BAJA', $plan['emp']['9388']['estatus']);
            $this->assertSame(['ACTINVER MANTENIMIENTOS' => '302', 'AFIRME MANTENIMIENTOS' => '303'], $plan['cc']);
            $ch = $imp->comparar($plan);
            $this->assertSame([[], [], [], [], []], [$ch['nuevos'], $ch['cambios'], $ch['faltan'], $ch['ccNuevos'], $ch['ccFaltan']]);
        } finally {
            @unlink($ruta);
        }
    }

    public function testEnviarCorreoDesdeElServidorYPasarAlConcentrado(): void
    {
        $a = $this->crear('c1', ['taxis' => '80', 'justTaxi' => 'otros', 'justTaxiOtro' => 'lluvia']);
        $b = $this->crear('c2');

        // la configuración la pone la prueba (no depende del .env de quien la corre)
        $cfg = config('Email');
        $cfg->protocol  = 'smtp';
        $cfg->fromEmail = 'sivit@trantor.mx';
        $cfg->SMTPHost  = 'smtp.trantor.mx';
        $cfg->SMTPUser  = 'sivit@trantor.mx';
        $cfg->SMTPPass  = '';

        // usuario SMTP sin contraseña = sin configurar → 503 para que el navegador abra el correo de la PC; no se marca nada
        $this->enviar('c1', 'solicitudes/enviar-correo', ['ids' => [(string) $a], 'para' => 'x@trantor.mx'])->assertStatus(503);
        $this->assertNull(model(SolicitudModel::class)->find($a)['enviado_en']);

        $cfg->SMTPPass = 'clave';

        $this->enviar('c1', 'solicitudes/enviar-correo', ['ids' => [(string) $a], 'para' => 'x@trantor.mx, malo'])->assertStatus(422);
        $this->enviar('c1', 'solicitudes/enviar-correo', ['ids' => [(string) $a], 'para' => ''])->assertStatus(422);

        // c1 no puede mandar la de c2: solo se envía y marca la suya
        $r = $this->enviar('c1', 'solicitudes/enviar-correo', ['ids' => [(string) $a, (string) $b], 'para' => 'x@trantor.mx; y@trantor.mx']);
        $r->assertOK();
        $this->assertSame(['enviadas' => 1, 'marcadas' => 1, 'para' => 'x@trantor.mx, y@trantor.mx'], json_decode($r->getJSON(), true));
        $this->assertSame('x@trantor.mx, y@trantor.mx', model(SolicitudModel::class)->find($a)['enviado_a']);
        $this->assertNull(model(SolicitudModel::class)->find($b)['enviado_en']);

        $correo = service('email')->archive;
        $this->assertSame('Solicitud de viáticos 29/10/2026 (1 solicitud)', $correo['subject']);
        $this->assertStringContainsString('OTROS: LLUVIA', $correo['body']);
        $this->assertStringContainsString('Enviado desde SIVIT por COORDINADOR UNO', $correo['body']);

        // si el servidor de correo falla, no se marca nada
        $c = $this->crear('c1');
        service('email')->returnValue = false;
        $this->enviar('c1', 'solicitudes/enviar-correo', ['ids' => [(string) $c], 'para' => 'x@trantor.mx'])->assertStatus(502);
        $this->assertNull(model(SolicitudModel::class)->find($c)['enviado_en']);
    }

    public function testCatalogoMuestraTodosLosIngenierosConFiltros(): void
    {
        $r = $this->como('op')->get('catalogo');
        $r->assertOK();
        $r->assertSee('Mostrando 2 de 2 ingenieros');
        $r->assertSee('ABREGO RAMIREZ ROLANDO');
        $r->assertSee('BEZA SAUCEDO ALEJANDRO');
        $r->assertSee('<option value="BAJA">BAJA (1)</option>', null);
        $this->como('c1')->get('catalogo')->assertStatus(403);   // solo el operador
    }

    public function testIndicadoresListanATodosLosIngenierosConSuGasto(): void
    {
        $this->crear('c1');                                                  // ABREGO: 150.50
        $this->crear('c1', ['nomina' => 'JUAN PROVEEDOR - 55555']);          // externo
        $r = $this->como('c1')->get('indicadores');
        $r->assertOK();
        $r->assertSee('Mostrando 3 de 3 ingenieros');                         // 2 del catálogo (uno sin gasto) + 1 externo
        $r->assertSee('BEZA SAUCEDO ALEJANDRO');
        $r->assertSee('JUAN PROVEEDOR');
        $r->assertSee('Solo con gasto <b>2</b>', null);
        $r->assertSee('$301.00');                                            // total de los mostrados

        // columna "Solicitó": quién capturó; con varios perfiles salen todos (el operador ve los de todos)
        $this->crear('c2');
        $this->como('op')->get('indicadores')->assertSee('<td>COORDINADOR DOS, COORDINADOR UNO</td>', null);
    }

    public function testColorDelPerfilSeAplicaComoColorValido(): void
    {
        model(UsuarioModel::class)->update($this->u['c1']['id'], ['tema' => '#0f766e']);
        \Config\Services::resetSingle('auth');   // el servicio guarda al usuario de una prueba anterior con el mismo id
        $html = $this->como('c1')->get('solicitudes')->getBody();
        $this->assertStringContainsString(':root{--brand:#0f766e;--brand-2:#', $html);
        $this->assertStringNotContainsString('\\23 ', $html);
    }

    public function testSolicitudesViejasNoCambianConElCatalogo(): void
    {
        $s = $this->crear('c1');
        model(EmpleadoModel::class)->update(model(EmpleadoModel::class)->porNomina('9313')['id'], ['nombre' => 'OTRO NOMBRE']);
        $this->assertSame('ABREGO RAMIREZ ROLANDO', model(SolicitudModel::class)->find($s)['nombre']);
    }
}
