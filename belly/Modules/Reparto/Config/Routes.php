<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Cómo llegan los chats: solo administradores.
$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:admin']], static function ($routes) {
    $routes->group('reparto', ['namespace' => 'Belly\Reparto\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'RepartoControllerApi::obtener', ['as' => 'apiv1_reparto']);
        $routes->put('/', 'RepartoControllerApi::guardar', ['as' => 'apiv1_reparto_guardar']);
        $routes->post('revisar', 'RepartoControllerApi::revisar', ['as' => 'apiv1_reparto_revisar']);
        $routes->post('probar', 'RepartoControllerApi::probar', ['as' => 'apiv1_reparto_probar']);

        $routes->get('grupos', 'RepartoControllerApi::listarGrupos', ['as' => 'apiv1_reparto_grupos']);
        $routes->post('grupos', 'RepartoControllerApi::crearGrupo', ['as' => 'apiv1_reparto_grupos_crear']);
        $routes->put('grupos/(:num)', 'RepartoControllerApi::actualizarGrupo/$1', ['as' => 'apiv1_reparto_grupos_actualizar']);
        $routes->delete('grupos/(:num)', 'RepartoControllerApi::eliminarGrupo/$1', ['as' => 'apiv1_reparto_grupos_eliminar']);
    });
});
