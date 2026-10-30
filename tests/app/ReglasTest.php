<?php

namespace Tests\App;

use Tests\Support\SivitTestCase;

use App\Libraries\SolicitudReglas;

final class ReglasTest extends SivitTestCase
{
    private function validar(array $extra = [], ?string $justPrevia = null): array
    {
        return (new SolicitudReglas())->validar($this->datos($extra), $justPrevia);
    }

    public function testInternoActivoCalculaTotalYOk(): void
    {
        $r = $this->validar();
        $this->assertSame([], $r['errs']);
        $this->assertSame(150.5, $r['rec']['total']);
        $this->assertSame('OK', $r['rec']['ok']);
        $this->assertSame('INTERNO', $r['rec']['tipo']);
        $this->assertSame('ACTINVER MANTENIMIENTOS', $r['rec']['cliente']);   // sin importar mayúsculas
        $this->assertSame('302', $r['rec']['cc']);
        $this->assertSame('GP1', $r['rec']['folio_gp']);                      // mayúsculas automáticas
        $this->assertSame('CASA A CLIENTE', $r['rec']['traslado']);
    }

    public function testBajaYExternoQuedanEnRevisar(): void
    {
        $this->assertSame('REVISAR', $this->validar(['nomina' => '9388'])['rec']['ok']);
        $ext = $this->validar(['nomina' => 'JUAN PROVEEDOR - 55555'])['rec'];
        $this->assertSame('REVISAR', $ext['ok']);
        $this->assertSame('EXTERNO (PROVEEDOR)', $ext['tipo']);
        $this->assertSame('JUAN PROVEEDOR', $ext['nombre']);
        $this->assertSame('NO ESTA EN BASE', $this->validar(['nomina' => '777'])['rec']['nombre']);
    }

    public function testMensajesDeValidacion(): void
    {
        $casos = [
            [['fecha' => ''], 'Escribe la fecha.'],
            [['nomina' => ''], 'Escribe el número de nómina.'],
            [['folio' => ''], 'Escribe el Folio GP. Si no hay folio, pon 0000.'],
            [['cliente' => ''], 'Escribe el cliente.'],
            [['cliente' => 'NO EXISTE'], 'El cliente que escribiste no está en la lista: elígelo de las opciones que salen al escribir.'],
            [['lf' => ''], 'Elige L (local) o F (foráneo).'],
            [['traslado' => ''], 'Escribe la descripción del TRASLADO (campo obligatorio).'],
            [['comida' => 'abc'], 'Revisa el importe de comida: solo números en pesos.'],
            [['comida' => '-5'], 'Revisa el importe de comida: solo números en pesos.'],
            [['taxis' => '80'], 'Escribe la justificación del uso de taxi.'],
            [['taxis' => '80', 'justTaxi' => 'inventada'], 'La justificación de taxi que escribiste no está en la lista: elígela de las opciones que salen al escribir.'],
            [['justTaxi' => 'HORARIO NOCTURNO O DE MADRUGADA'], 'Escribiste una justificación de taxi pero no hay cantidad en TAXIS ($). Escribe el importe o borra la justificación.'],
            [['otros' => '30'], 'Hay cantidad en OTROS ($): elige el concepto (renta de escalera, gasolina, etc.).'],
            [['otros' => '30', 'conceptoOtros' => 'otros'], 'Hay cantidad en OTROS ($): escribe un comentario que explique el gasto.'],
            [['conceptoOtros' => 'otros'], 'Elegiste un concepto de OTROS pero no hay cantidad en OTROS ($).'],
            [['otros' => '30', 'conceptoOtros' => 'xx', 'comentarios' => 'c'], 'El concepto de OTROS no está en la lista: elígelo de las opciones que salen al escribir (o usa OTROS).'],
        ];
        foreach ($casos as [$extra, $msg]) {
            $this->assertContains($msg, $this->validar($extra)['errs'], json_encode($extra));
        }
    }

    public function testTaxiConJustificacionSinAcentoYJustificacionPrevia(): void
    {
        $r = $this->validar(['taxis' => '80', 'justTaxi' => 'fatiga por extension de jornada laboral']);
        $this->assertSame([], $r['errs']);
        $this->assertSame('FATIGA POR EXTENSIÓN DE JORNADA LABORAL', $r['rec']['just_taxi']);
        // al editar se acepta la justificación guardada aunque ya no esté en la lista
        $this->assertSame([], $this->validar(['taxis' => '80', 'justTaxi' => 'JUSTIFICACION VIEJA'], 'JUSTIFICACION VIEJA')['errs']);
    }

    public function testImportesConFormatoDeMoneda(): void
    {
        $r = $this->validar(['comida' => '$1,200.50', 'hotel' => '']);
        $this->assertSame(1200.5, $r['rec']['total']);
    }
}
