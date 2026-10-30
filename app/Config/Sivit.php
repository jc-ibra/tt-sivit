<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Listas y constantes de negocio de SIVIT (antes estaban dentro del HTML).
 */
class Sivit extends BaseConfig
{
    /** Importes que se suman en el total. */
    public array $money = ['tran_publico', 'taxis', 'autobus', 'comida', 'hotel', 'otros'];

    /** Estatus del empleado que obligan a revisar la solicitud. */
    public array $estatusRevisar = ['BAJA', 'INCAPACIDAD'];

    public array $conceptosOtros = [
        'RENTA DE ESCALERA', 'PAGO DE GASOLINA', 'PAGO DE PROVEEDOR',
        'DATOS MÓVILES', 'COMPRA DE HERRAMIENTA', 'OTROS',
    ];

    public array $justificacionesTaxi = [
        'HORARIO NOCTURNO O DE MADRUGADA',
        'ZONA DE ALTO RIESGO O DELINCUENCIA',
        'FATIGA POR EXTENSIÓN DE JORNADA LABORAL',
        'VEHÍCULO UTILITARIO FUERA DE SERVICIO O ASIGNADO',
        'OPTIMIZACIÓN DE RUTAS PARA MÚLTIPLES CLIENTES',
        'RESTRICCIONES VEHICULARES LOCALES',
    ];

    /** Colores de perfil [nombre, hex]. */
    public array $temas = [
        ['Azul marino', '#1f4e79'], ['Azul rey', '#1d4ed8'], ['Azul claro', '#2a6fb0'], ['Azul cielo', '#2a77ba'],
        ['Azul agua', '#0b7285'], ['Turquesa', '#0f766e'], ['Azul petróleo', '#164e63'], ['Azul índigo', '#3730a3'],
    ];

    public array $roles = ['operador', 'coordinador', 'viatico'];

    public string $correoPredeterminado = 'viatico.emergente@trantortechnologies.mx';

    public int $passwordMinimo = 4;

    /** Token secreto para /setup.php (en .env: sivit.setupToken = ...). Vacío = el instalador está desactivado. */
    public string $setupToken = '';
}
