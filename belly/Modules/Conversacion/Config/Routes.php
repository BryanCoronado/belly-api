<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => ['authFilter', 'whatsappFilter']], static function ($routes) {

    $routes->group('conversacion', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
        $routes->get('listar', 'ConversacionControllerApi::listar', ['as' => 'apiv1_conversacion_listar']);
        $routes->get('supervision', 'ConversacionControllerApi::supervision', ['as' => 'apiv1_conversacion_supervision']);
        $routes->get('ver/(:num)', 'ConversacionControllerApi::ver/$1', ['as' => 'apiv1_conversacion_ver']);
        $routes->get('mis-conversaciones', 'ConversacionControllerApi::listarMisConversaciones', ['as' => 'apiv1_conversacion_mis_conversaciones']);
        $routes->get('finalizadas', 'ConversacionControllerApi::listarFinalizadas', ['as' => 'apiv1_conversacion_finalizadas']);
        $routes->get('no-leidos', 'ConversacionControllerApi::listarNoLeidos', ['as' => 'apiv1_conversacion_no_leidos']);
        $routes->get('falta-responder', 'ConversacionControllerApi::listarFaltaResponder', ['as' => 'apiv1_conversacion_falta_responder']);
        $routes->get('contadores-sidebar', 'ConversacionControllerApi::contadoresSidebar', ['as' => 'apiv1_conversacion_contadores_sidebar']);
        $routes->get('ventas', 'ConversacionControllerApi::listarVentas', ['as' => 'apiv1_conversacion_ventas']);
        $routes->put('abrir/(:num)', 'ConversacionControllerApi::abrir/$1', ['as' => 'apiv1_conversacion_abrir']);
        $routes->put('etiqueta/(:num)', 'ConversacionControllerApi::actualizarEtiqueta/$1', ['as' => 'apiv1_conversacion_actualizar_etiqueta']);
        $routes->put('(:num)', 'ConversacionControllerApi::editar/$1', ['as' => 'apiv1_conversacion_editar']);
        $routes->put('temperatura/(:num)', 'ConversacionControllerApi::actualizarTemperatura/$1', ['as' => 'apiv1_conversacion_actualizar_temperatura']);
        $routes->put('finalizar/(:num)', 'ConversacionControllerApi::finalizar/$1', ['as' => 'apiv1_conversacion_finalizar']);
        $routes->put('transferir/(:num)', 'ConversacionControllerApi::transferir/$1', ['as' => 'apiv1_conversacion_transferir']);
        $routes->post('transferir-masivo', 'ConversacionControllerApi::transferirMasivo', ['as' => 'apiv1_conversacion_transferir_masivo']);
        $routes->get('usuarios-disponibles/(:num)', 'ConversacionControllerApi::obtenerUsuariosDisponibles/$1', ['as' => 'apiv1_conversacion_usuarios_disponibles']);
        $routes->get('finalizadas/cliente/(:num)', 'ConversacionControllerApi::listarFinalizadasPorCliente/$1', ['as' => 'apiv1_conversacion_finalizadas_cliente']);
    });
});

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {

    $routes->group('etiqueta', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
        $routes->get('listar', 'EtiquetaControllerApi::listar', ['as' => 'apiv1_etiqueta_listar']);
        $routes->post('crear', 'EtiquetaControllerApi::crear', ['as' => 'apiv1_etiqueta_crear']);
        $routes->put('(:num)', 'EtiquetaControllerApi::editar/$1', ['as' => 'apiv1_etiqueta_editar']);
        $routes->delete('(:num)', 'EtiquetaControllerApi::eliminar/$1', ['as' => 'apiv1_etiqueta_eliminar']);
    });

    $routes->group('motivo-conversacion', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
        $routes->get('listar', 'MotivoConversacionControllerApi::listar', ['as' => 'apiv1_motivo_conversacion_listar']);
        $routes->get('(:num)', 'MotivoConversacionControllerApi::obtener/$1', ['as' => 'apiv1_motivo_conversacion_obtener']);
        $routes->post('crear', 'MotivoConversacionControllerApi::crear', ['as' => 'apiv1_motivo_conversacion_crear']);
        $routes->put('(:num)', 'MotivoConversacionControllerApi::editar/$1', ['as' => 'apiv1_motivo_conversacion_editar']);
        $routes->delete('(:num)', 'MotivoConversacionControllerApi::eliminar/$1', ['as' => 'apiv1_motivo_conversacion_eliminar']);
    });
});
