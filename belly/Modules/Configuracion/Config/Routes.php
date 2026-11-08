<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {
    $routes->group('configuracion', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('mis-apps', 'ConfiguracionControllerApi::listarMisApps', ['as' => 'apiv1_mis_apps']);
    });
    $routes->group('integraciones/whatsapp', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('conectado', 'IntegracionWhatsappControllerApi::conectado', ['as' => 'apiv1_whatsapp_conectado']);
    });
    $routes->group('empresa', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('modo', 'EmpresaControllerApi::leerModo', ['as' => 'apiv1_empresa_modo']);
        $routes->get('cupo', 'EmpresaControllerApi::leerCupo', ['as' => 'apiv1_empresa_cupo']);
        $routes->get('app-asesor', 'EmpresaControllerApi::leerAppAsesor', ['as' => 'apiv1_empresa_app_asesor']);
    });
});

$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:admin']], static function ($routes) {
    $routes->group('empresa', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->put('modo', 'EmpresaControllerApi::guardarModo', ['as' => 'apiv1_empresa_modo_guardar']);
        $routes->put('app-asesor', 'EmpresaControllerApi::guardarAppAsesor', ['as' => 'apiv1_empresa_app_asesor_guardar']);
        $routes->get('pagos', 'PagoControllerApi::mios', ['as' => 'apiv1_empresa_pagos']);
    });
});

$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:admin']], static function ($routes) {
    $routes->group('integraciones/whatsapp', ['namespace' => 'Belly\Configuracion\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'IntegracionWhatsappControllerApi::estado', ['as' => 'apiv1_whatsapp_estado']);
        $routes->get('costos', 'IntegracionWhatsappControllerApi::costos', ['as' => 'apiv1_whatsapp_costos']);
        $routes->delete('/', 'IntegracionWhatsappControllerApi::desconectar', ['as' => 'apiv1_whatsapp_desconectar']);
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
        $routes->put('empresas/(:num)/estado', 'EmpresaControllerApi::cambiarEstado/$1', ['as' => 'apiv1_empresas_estado']);
        $routes->put('empresas/(:num)/plan', 'PlanControllerApi::asignar/$1', ['as' => 'apiv1_empresas_plan']);
        $routes->post('empresas', 'EmpresaControllerApi::crear', ['as' => 'apiv1_empresas_crear']);
        $routes->get('planes', 'PlanControllerApi::listar', ['as' => 'apiv1_planes']);
        $routes->post('planes', 'PlanControllerApi::crear', ['as' => 'apiv1_planes_crear']);
        $routes->put('planes/(:num)', 'PlanControllerApi::actualizar/$1', ['as' => 'apiv1_planes_actualizar']);
        $routes->delete('planes/(:num)', 'PlanControllerApi::eliminar/$1', ['as' => 'apiv1_planes_eliminar']);
        $routes->get('pagos', 'PagoControllerApi::listar', ['as' => 'apiv1_pagos']);
        $routes->post('pagos', 'PagoControllerApi::crear', ['as' => 'apiv1_pagos_crear']);
        $routes->put('pagos/(:num)', 'PagoControllerApi::marcar/$1', ['as' => 'apiv1_pagos_marcar']);
    });
});
