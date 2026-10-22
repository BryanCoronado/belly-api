<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Asistentes de IA: solo administradores.
$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:admin']], static function ($routes) {
    $routes->group('ia', ['namespace' => 'Belly\AgenteIa\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'AgenteIaControllerApi::estado', ['as' => 'apiv1_ia']);
        $routes->put('conexion', 'AgenteIaControllerApi::conectar', ['as' => 'apiv1_ia_conectar']);
        $routes->delete('conexion', 'AgenteIaControllerApi::desconectar', ['as' => 'apiv1_ia_desconectar']);

        $routes->post('agentes', 'AgenteIaControllerApi::crear', ['as' => 'apiv1_ia_agentes_crear']);
        $routes->get('agentes/(:num)', 'AgenteIaControllerApi::obtener/$1', ['as' => 'apiv1_ia_agentes_obtener']);
        $routes->put('agentes/(:num)', 'AgenteIaControllerApi::actualizar/$1', ['as' => 'apiv1_ia_agentes_actualizar']);
        $routes->delete('agentes/(:num)', 'AgenteIaControllerApi::eliminar/$1', ['as' => 'apiv1_ia_agentes_eliminar']);
        $routes->post('agentes/(:num)/probar', 'AgenteIaControllerApi::probar/$1', ['as' => 'apiv1_ia_agentes_probar']);

        $routes->post('agentes/(:num)/conocimiento', 'AgenteIaControllerApi::agregarConocimiento/$1', ['as' => 'apiv1_ia_conocimiento_crear']);
        $routes->put('agentes/(:num)/conocimiento/(:num)', 'AgenteIaControllerApi::actualizarConocimiento/$1/$2', ['as' => 'apiv1_ia_conocimiento_actualizar']);
        $routes->delete('agentes/(:num)/conocimiento/(:num)', 'AgenteIaControllerApi::eliminarConocimiento/$1/$2', ['as' => 'apiv1_ia_conocimiento_eliminar']);
    });
});
