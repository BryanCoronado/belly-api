<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Solo para el servidor de tiempo real (valida la clave en el controlador): retoma los flujos en espera.
$routes->post('v1/interno/flujos/procesar', 'FlujoInternoControllerApi::procesar', [
    'namespace' => 'Belly\Flujos\Controllers\Api',
    'as' => 'apiv1_flujos_procesar',
]);

// Para los asesores (no solo administradores): qué recogió el bot en la conversación.
$routes->group('v1', ['filter' => ['authFilter', 'whatsappFilter']], static function ($routes) {
    $routes->get('bot/conversaciones/(:num)/resumen', 'BotConversacionControllerApi::resumen/$1', [
        'namespace' => 'Belly\Flujos\Controllers\Api',
        'as' => 'apiv1_bot_resumen',
    ]);
});

$routes->group('v1', ['filter' => ['authFilter', 'whatsappFilter', 'rolFilter:admin']], static function ($routes) {
    $routes->group('flujos', ['namespace' => 'Belly\Flujos\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'FlujoControllerApi::listar', ['as' => 'apiv1_flujos']);
        $routes->get('asesores', 'FlujoControllerApi::asesores', ['as' => 'apiv1_flujos_asesores']);
        $routes->post('/', 'FlujoControllerApi::crear', ['as' => 'apiv1_flujos_crear']);
        $routes->post('validar', 'FlujoControllerApi::validar', ['as' => 'apiv1_flujos_validar']);
        $routes->post('simular', 'FlujoControllerApi::simular', ['as' => 'apiv1_flujos_simular']);
        $routes->post('archivos', 'FlujoControllerApi::subirArchivo', ['as' => 'apiv1_flujos_archivos']);
        $routes->get('(:num)', 'FlujoControllerApi::ver/$1', ['as' => 'apiv1_flujos_ver']);
        $routes->put('(:num)', 'FlujoControllerApi::actualizar/$1', ['as' => 'apiv1_flujos_actualizar']);
        $routes->delete('(:num)', 'FlujoControllerApi::eliminar/$1', ['as' => 'apiv1_flujos_eliminar']);
        $routes->put('(:num)/estado', 'FlujoControllerApi::cambiarEstado/$1', ['as' => 'apiv1_flujos_estado']);
        $routes->post('(:num)/duplicar', 'FlujoControllerApi::duplicar/$1', ['as' => 'apiv1_flujos_duplicar']);
    });
});
