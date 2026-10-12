<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {
    $routes->group('configuracion', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('mis-apps', 'ConfiguracionControllerApi::listarMisApps', ['as' => 'apiv1_mis_apps']);
    });
    $routes->group('integraciones/whatsapp', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('conectado', 'IntegracionWhatsappControllerApi::conectado', ['as' => 'apiv1_whatsapp_conectado']);
    });
});

$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:admin']], static function ($routes) {
    $routes->group('integraciones/whatsapp', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'IntegracionWhatsappControllerApi::estado', ['as' => 'apiv1_whatsapp_estado']);
        $routes->put('app-meta', 'IntegracionWhatsappControllerApi::guardarAppMeta', ['as' => 'apiv1_whatsapp_app_meta']);
        $routes->post('regenerar-token', 'IntegracionWhatsappControllerApi::regenerarToken', ['as' => 'apiv1_whatsapp_regenerar_token']);
        $routes->post('numeros', 'IntegracionWhatsappControllerApi::conectarNumero', ['as' => 'apiv1_whatsapp_conectar_numero']);
        $routes->delete('numeros/(:num)', 'IntegracionWhatsappControllerApi::desconectarNumero/$1', ['as' => 'apiv1_whatsapp_desconectar_numero']);
    });
});

$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:admin']], static function ($routes) {
    $routes->group('configuracion', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('apps-disponibles', 'ConfiguracionControllerApi::listarAppsDisponibles', ['as' => 'apiv1_apps_disponibles']);
        $routes->get('apps', 'ConfiguracionControllerApi::listarApps', ['as' => 'apiv1_apps']);
        $routes->get('apps/(:num)/usuarios', 'ConfiguracionControllerApi::listarUsuariosPorApp/$1', ['as' => 'apiv1_app_usuarios']);
        $routes->post('apps/(:num)/usuarios', 'ConfiguracionControllerApi::asignarUsuarioAApp/$1', ['as' => 'apiv1_app_asignar_usuario']);
        $routes->delete('apps/(:num)/usuarios/(:num)', 'ConfiguracionControllerApi::desasignarUsuarioDeApp/$1/$2', ['as' => 'apiv1_app_desasignar_usuario']);
        $routes->get('portafolios-con-apps', 'ConfiguracionControllerApi::listarPortafoliosConApps', ['as' => 'apiv1_portafolios_con_apps']);
        $routes->post('crear-app', 'ConfiguracionControllerApi::crearApp', ['as' => 'apiv1_crear_app']);
    });
});

$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:operador']], static function ($routes) {
    $routes->group('configuracion', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('empresas', 'EmpresaControllerApi::listar', ['as' => 'apiv1_empresas']);
        $routes->post('empresas', 'EmpresaControllerApi::crear', ['as' => 'apiv1_empresas_crear']);
    });
});
