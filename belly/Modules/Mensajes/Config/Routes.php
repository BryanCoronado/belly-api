<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => ['authFilter', 'whatsappFilter']], static function ($routes) {
    $routes->group('mensaje', ['namespace' => 'Belly\Mensajes\Controllers\Api'], static function ($routes) {
        $routes->post('enviar', 'MensajeController::enviar');
        $routes->post('enviar-plantilla', 'MensajeController::enviarPlantilla');
        $routes->get('listar-conversacion/(:num)', 'MensajeController::listarPorConversacion/$1');
        $routes->post('subir-archivo', 'MensajeController::subirArchivo');
        $routes->get('stickers', 'StickerController::listar');
    });
    
    $routes->group('plantilla', ['namespace' => 'Belly\Mensajes\Controllers\Api'], static function ($routes) {
        $routes->get('listar', 'PlantillaMensajeController::listarTodas');
        $routes->get('listar-activas', 'PlantillaMensajeController::listarActivas');
        $routes->post('crear', 'PlantillaMensajeController::crear');
        $routes->put('actualizar-estado', 'PlantillaMensajeController::actualizarEstado');
        $routes->post('subir-archivo-reanudable', 'PlantillaMensajeController::subirArchivoReanudable');
        $routes->delete('eliminar/(:num)/(:any)', 'PlantillaMensajeController::eliminar/$1/$2');
    });

    $routes->group('plantilla-rapida', ['namespace' => 'Belly\Mensajes\Controllers\Api'], static function ($routes) {
        $routes->post('crear', 'PlantillaRapidaController::crear');
        $routes->get('listar', 'PlantillaRapidaController::listar');
        $routes->get('listar-por-usuario', 'PlantillaRapidaController::listarPorUsuario');      
        $routes->post('editar/(:num)', 'PlantillaRapidaController::editar/$1');
        $routes->put('editar/(:num)', 'PlantillaRapidaController::editar/$1');
        $routes->get('ver/(:num)', 'PlantillaRapidaController::ver/$1');
        $routes->delete('eliminar/(:num)', 'PlantillaRapidaController::eliminar/$1');

    });

});
