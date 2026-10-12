<?php

use CodeIgniter\Router\RouteCollection;

// El catálogo de permisos es global para todas las empresas: solo el operador de la plataforma lo gestiona.
$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:operador']], static function ($routes) {

    $routes->group('grupo_permiso', ['namespace' => 'Belly\Permisos\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'GrupoPermisoController::listar', ['as' => 'apiv1_grupo_permiso_list']);
        $routes->get('(:num)', 'GrupoPermisoController::ver/$1', ['as' => 'api-v1_grupo_permiso_read']);
        $routes->post('crear', 'GrupoPermisoController::crear', ['as' => 'api-v1_grupo_permiso_create']);
        $routes->put('(:num)', 'GrupoPermisoController::actualizar/$1', ['as' => 'api-v1_grupo_permiso_update']);
        $routes->delete('(:num)', 'GrupoPermisoController::eliminar/$1', ['as' => 'api-v1_grupo_permiso_delete']);

        $routes->group('permiso', ['namespace' => 'Belly\Permisos\Controllers\Api'], static function ($routes) {
            $routes->get('/', 'PermisoController::listar', ['as' => 'api-v1_permiso_list']);
            $routes->get('(:num)', 'PermisoController::ver/$1', ['as' => 'api-v1_permiso_read']);
            $routes->post('crear', 'PermisoController::crear', ['as' => 'api-v1_permiso_create']);
            $routes->put('(:num)', 'PermisoController::actualizar/$1', ['as' => 'api-v1_permiso_update']);
            $routes->put('estado/(:num)', 'PermisoController::actualizarEstado/$1', ['as' => 'api-v1_permiso_update_estado']);
            $routes->delete('(:num)', 'PermisoController::eliminar/$1', ['as' => 'api-v1_permiso_delete']);
        });
    });
});
