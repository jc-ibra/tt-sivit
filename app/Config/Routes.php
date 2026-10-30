<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);

// Instalador web (migraciones sin terminal): protegido por token del .env y se bloquea al terminar
$routes->get('setup.php', 'Setup::index');
$routes->post('setup.php', 'Setup::ejecutar');

$routes->get('/', 'Auth::inicio');
$routes->post('login', 'Auth::login');
$routes->post('login/crear-operador', 'Auth::crearOperador');
$routes->post('logout', 'Auth::logout');

$routes->get('solicitudes', 'Solicitudes::index');
$routes->post('solicitudes', 'Solicitudes::guardar');
$routes->get('solicitudes/(:num)/editar', 'Solicitudes::editar/$1');
$routes->post('solicitudes/(:num)', 'Solicitudes::actualizar/$1');
$routes->post('solicitudes/(:num)/eliminar', 'Solicitudes::eliminar/$1');
$routes->post('solicitudes/(:num)/deposito', 'Solicitudes::deposito/$1');
$routes->post('solicitudes/marcar-enviadas', 'Solicitudes::marcarEnviadas');

$routes->get('concentrado', 'Concentrado::index');
$routes->get('concentrado/csv', 'Concentrado::csv');
$routes->get('indicadores', 'Indicadores::index');

$routes->get('usuarios/(:num)/foto', 'Usuarios::foto/$1');
$routes->post('usuarios', 'Usuarios::crear');
$routes->post('usuarios/(:num)', 'Usuarios::actualizar/$1');
$routes->post('usuarios/(:num)/eliminar', 'Usuarios::eliminar/$1');
$routes->post('usuarios/correo', 'Usuarios::correo');

$routes->get('catalogo', 'Catalogo::index');
$routes->post('catalogo/previa', 'Catalogo::previa');
$routes->post('catalogo/aplicar', 'Catalogo::aplicar');
$routes->get('catalogo/csv/(:segment)', 'Catalogo::csv/$1');

$routes->get('respaldo', 'Respaldo::descargar');
