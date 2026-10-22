<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$opciones = ['namespace' => 'Belly\Crm\Controllers\Api'];

// Clientes: cada quien ve los que atiende; el administrador, todos (lo resuelve el caso de uso).
$routes->group('v1/crm', ['filter' => ['authFilter']] + $opciones, static function ($routes) {
    $routes->get('ajustes', 'CrmControllerApi::ajustes', ['as' => 'apiv1_crm_ajustes']);
    $routes->get('dashboard', 'CrmControllerApi::dashboard', ['as' => 'apiv1_crm_dashboard']);
    $routes->get('clientes', 'CrmControllerApi::listarClientes', ['as' => 'apiv1_crm_clientes']);
    $routes->get('clientes/tablero', 'CrmControllerApi::tablero', ['as' => 'apiv1_crm_tablero']);
    $routes->get('clientes/(:num)', 'CrmControllerApi::verCliente/$1', ['as' => 'apiv1_crm_cliente']);
    $routes->put('clientes/(:num)', 'CrmControllerApi::actualizarCliente/$1', ['as' => 'apiv1_crm_cliente_actualizar']);
    $routes->post('clientes/varios', 'CrmControllerApi::actualizarVarios', ['as' => 'apiv1_crm_clientes_varios']);
    // Alta manual e importación: quien no es administrador queda como responsable de lo que carga.
    $routes->post('clientes', 'CrmControllerApi::crearCliente', ['as' => 'apiv1_crm_clientes_crear']);
    $routes->post('clientes/importar', 'CrmControllerApi::importarClientes', ['as' => 'apiv1_crm_clientes_importar']);
    $routes->post('clientes/(:num)/chat', 'CrmControllerApi::iniciarChat/$1', ['as' => 'apiv1_crm_cliente_chat']);
});

// Etapas, tipos y etiquetas: quien puede editar clientes.
$routes->group('v1/crm', ['filter' => ['authFilter', 'permisosFilter:mod_leads_clientes_access,acc_actualizar']] + $opciones, static function ($routes) {
    $routes->post('etapas', 'CrmControllerApi::crearEtapa', ['as' => 'apiv1_crm_etapas_crear']);
    $routes->put('etapas/orden', 'CrmControllerApi::ordenarEtapas', ['as' => 'apiv1_crm_etapas_orden']);
    $routes->put('etapas/(:num)', 'CrmControllerApi::actualizarEtapa/$1', ['as' => 'apiv1_crm_etapas_actualizar']);
    $routes->delete('etapas/(:num)', 'CrmControllerApi::eliminarEtapa/$1', ['as' => 'apiv1_crm_etapas_eliminar']);

    $routes->post('tipos', 'CrmControllerApi::crearTipo', ['as' => 'apiv1_crm_tipos_crear']);
    $routes->put('tipos/orden', 'CrmControllerApi::ordenarTipos', ['as' => 'apiv1_crm_tipos_orden']);
    $routes->put('tipos/(:num)', 'CrmControllerApi::actualizarTipo/$1', ['as' => 'apiv1_crm_tipos_actualizar']);
    $routes->delete('tipos/(:num)', 'CrmControllerApi::eliminarTipo/$1', ['as' => 'apiv1_crm_tipos_eliminar']);

    $routes->post('etiquetas', 'CrmControllerApi::crearEtiqueta', ['as' => 'apiv1_crm_etiquetas_crear']);
    $routes->put('etiquetas/(:num)', 'CrmControllerApi::actualizarEtiqueta/$1', ['as' => 'apiv1_crm_etiquetas_actualizar']);
    $routes->delete('etiquetas/(:num)', 'CrmControllerApi::eliminarEtiqueta/$1', ['as' => 'apiv1_crm_etiquetas_eliminar']);

    $routes->post('motivos', 'CrmControllerApi::crearMotivo', ['as' => 'apiv1_crm_motivos_crear']);
    $routes->put('motivos/orden', 'CrmControllerApi::ordenarMotivos', ['as' => 'apiv1_crm_motivos_orden']);
    $routes->put('motivos/(:num)', 'CrmControllerApi::actualizarMotivo/$1', ['as' => 'apiv1_crm_motivos_actualizar']);
    $routes->delete('motivos/(:num)', 'CrmControllerApi::eliminarMotivo/$1', ['as' => 'apiv1_crm_motivos_eliminar']);
});

// Envíos masivos: módulo con su propio permiso.
$routes->group('v1/envios', ['filter' => ['authFilter', 'permisosFilter:mod_envios_masivos_access,acc_lectura']] + $opciones, static function ($routes) {
    $routes->get('/', 'CrmControllerApi::listarEnvios', ['as' => 'apiv1_envios']);
    $routes->get('plantillas', 'CrmControllerApi::plantillas', ['as' => 'apiv1_envios_plantillas']);
    $routes->post('audiencia', 'CrmControllerApi::audiencia', ['as' => 'apiv1_envios_audiencia']);
    $routes->get('(:num)', 'CrmControllerApi::verEnvio/$1', ['as' => 'apiv1_envios_ver']);
});

$routes->group('v1/envios', ['filter' => ['authFilter', 'permisosFilter:mod_envios_masivos_access,acc_crear']] + $opciones, static function ($routes) {
    $routes->post('/', 'CrmControllerApi::crearEnvio', ['as' => 'apiv1_envios_crear']);
    $routes->put('(:num)/cancelar', 'CrmControllerApi::cancelarEnvio/$1', ['as' => 'apiv1_envios_cancelar']);
});

// Solo para el servidor de tiempo real (valida la clave en el controlador).
$routes->post('v1/interno/envios/procesar', 'EnvioInternoControllerApi::procesar', $opciones + ['as' => 'apiv1_envios_procesar']);
