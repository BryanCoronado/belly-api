<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {

    $routes->group('clientes', ['namespace' => 'Belly\Clientes\Controllers\Api'], static function ($routes) {
        $routes->post('crear', 'ClienteControllerApi::crear', ['as' => 'apiv1_clientes_crear'] );
        $routes->post('crear-manual-asesor', 'ClienteControllerApi::crearManualAsesor', ['as' => 'apiv1_clientes_crear_manual_asesor'] );
        $routes->post('preview-importacion', 'ClienteControllerApi::previewImportacion', ['as' => 'apiv1_clientes_preview_importacion'] );
        $routes->post('importar', 'ClienteControllerApi::importar', ['as' => 'apiv1_clientes_importar'] );
        $routes->post('eliminar-multiples', 'ClienteControllerApi::eliminarMultiples', ['as' => 'apiv1_clientes_eliminar_multiples'] );
        $routes->get('listar', 'ClienteControllerApi::listar', ['as' => 'apiv1_clientes_listar'] );
        $routes->get('listar-mis-clientes', 'ClienteControllerApi::listarMisClientes', ['as' => 'apiv1_clientes_listar_mis_clientes'] );
        $routes->get('buscar-telefono', 'ClienteControllerApi::buscarPorTelefono', ['as' => 'apiv1_clientes_buscar_telefono'] );
        $routes->get('(:num)', 'ClienteControllerApi::ver/$1', ['as' => 'apiv1_clientes_ver'] );
        $routes->put('(:num)', 'ClienteControllerApi::actualizar/$1', ['as' => 'apiv1_clientes_actualizar'] );
        $routes->delete('(:num)', 'ClienteControllerApi::eliminar/$1', ['as' => 'apiv1_clientes_eliminar'] );
    });

    $routes->group('lista-negra', ['namespace' => 'Belly\Clientes\Controllers\Api'], static function ($routes) {
        $routes->post('crear', 'ListaNegraControllerApi::crear', ['as' => 'apiv1_lista_negra_crear'] );
        $routes->post('eliminar-multiples', 'ListaNegraControllerApi::eliminarMultiples', ['as' => 'apiv1_lista_negra_eliminar_multiples'] );
        $routes->get('listar', 'ListaNegraControllerApi::listar', ['as' => 'apiv1_lista_negra_listar'] );
        $routes->get('verificar', 'ListaNegraControllerApi::verificar', ['as' => 'apiv1_lista_negra_verificar'] );
        $routes->get('(:num)', 'ListaNegraControllerApi::ver/$1', ['as' => 'apiv1_lista_negra_ver'] );
        $routes->put('(:num)', 'ListaNegraControllerApi::actualizar/$1', ['as' => 'apiv1_lista_negra_actualizar'] );
        $routes->delete('(:num)', 'ListaNegraControllerApi::eliminar/$1', ['as' => 'apiv1_lista_negra_eliminar'] );
    });

    $routes->group('metricas', ['namespace' => 'Belly\Clientes\Controllers\Api'], static function ($routes) {
        $routes->get('atencion', 'ClienteMetricaControllerApi::obtenerMetricasAtencion', ['as' => 'apiv1_metricas_atencion'] );
    });
});
