<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {

    $routes->group('modulo', ['namespace' => 'Belly\Modulos\Controllers\Api','filter' => 'permisosFilter:mod_sistemas_access,acc_lectura'], static function ($routes) {
        $routes->get('/', 'ModuloController::listar', ['as' => 'apiv1_modulos_list']);
        $routes->get('(:num)', 'ModuloController::ver/$1', ['as' => 'api-v1_modulos_read']);
        $routes->post('crear', 'ModuloController::crear', ['as' => 'apiv1_modulos_create']);
        $routes->put('(:num)', 'ModuloController::editar/$1', ['as' => 'apiv1_modulos_update']);
        $routes->delete('(:num)', 'ModuloController::eliminar/$1', ['as' => 'api-v1_modulos_delete']);
        $routes->get('menu', 'ModuloController::menu', ['as' => 'apiv1_modulos_menu']);

        $routes->group('grupo', ['namespace' => 'Belly\Modulos\Controllers\Api'], static function ($routes) {
            $routes->get('/', 'ModuloGrupoController::listar', ['as' => 'apiv1_modulos_grupo_list']);
            $routes->post('crear', 'ModuloGrupoController::crear', ['as' => 'apiv1_modulos_grupo_create']);
            $routes->put('(:num)', 'ModuloGrupoController::editar/$1', ['as' => 'apiv1_modulos_grupo_update']);
            $routes->delete('(:num)', 'ModuloGrupoController::eliminar/$1', ['as' => 'apiv1_modulos_grupo_delete']);
        });
    });
});