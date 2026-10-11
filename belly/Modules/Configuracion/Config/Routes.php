<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {

    $routes->group('configuracion', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('apps-disponibles', 'ConfiguracionControllerApi::listarAppsDisponibles', ['as' => 'apiv1_apps_disponibles']);
        $routes->get('apps', 'ConfiguracionControllerApi::listarApps', ['as' => 'apiv1_apps']);
        $routes->get('mis-apps', 'ConfiguracionControllerApi::listarMisApps', ['as' => 'apiv1_mis_apps']);
        $routes->get('apps/(:num)/usuarios', 'ConfiguracionControllerApi::listarUsuariosPorApp/$1', ['as' => 'apiv1_app_usuarios']);
        $routes->post('apps/(:num)/usuarios', 'ConfiguracionControllerApi::asignarUsuarioAApp/$1', ['as' => 'apiv1_app_asignar_usuario']);
        $routes->get('portafolios-con-apps', 'ConfiguracionControllerApi::listarPortafoliosConApps', ['as' => 'apiv1_portafolios_con_apps']);
        $routes->post('crear-app', 'ConfiguracionControllerApi::crearApp', ['as' => 'apiv1_crear_app']);
        $routes->get('empresas', 'EmpresaControllerApi::listar', ['as' => 'apiv1_empresas']);
        $routes->post('empresas', 'EmpresaControllerApi::crear', ['as' => 'apiv1_empresas_crear']);
        $routes->delete('apps/(:num)/usuarios/(:num)', 'ConfiguracionControllerApi::desasignarUsuarioDeApp/$1/$2', ['as' => 'apiv1_app_desasignar_usuario']);
    });
});
