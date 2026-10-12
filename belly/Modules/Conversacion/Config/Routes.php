<?php

use CodeIgniter\Router\RouteCollection;


$routes->group('v1/conversacion/cita-conversacion', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
    $routes->post('actualizar-asistencia', 'CitaConversacionControllerApi::actualizarAsistenciaCita', ['as' => 'apiv1_cita_conversacion_actualizar_asistencia']);
});

$routes->group('v1/conversacion', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
    $routes->get('campanias-meta', 'ConversacionControllerApi::listarCampaniasMeta', ['as' => 'apiv1_conversacion_campanias_meta']);
});

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {

    $routes->group('conversacion', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {

        $routes->group('cita-conversacion', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
            $routes->post('crear', 'CitaConversacionControllerApi::crear', ['as' => 'apiv1_cita_conversacion_crear']);
            $routes->get('listar', 'CitaConversacionControllerApi::listar', ['as' => 'apiv1_cita_conversacion_listar']);
            $routes->get('(:num)', 'CitaConversacionControllerApi::ver/$1', ['as' => 'apiv1_cita_conversacion_ver']);   
        });
    });
});

$routes->group('v1', ['filter' => ['authFilter', 'whatsappFilter']], static function ($routes) {

    $routes->group('conversacion', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
        $routes->get('listar', 'ConversacionControllerApi::listar', ['as' => 'apiv1_conversacion_listar'] );
        $routes->get('ver/(:num)', 'ConversacionControllerApi::ver/$1', ['as' => 'apiv1_conversacion_ver'] );
        $routes->get('mis-conversaciones', 'ConversacionControllerApi::listarMisConversaciones', ['as' => 'apiv1_conversacion_mis_conversaciones'] );
        $routes->get('finalizadas', 'ConversacionControllerApi::listarFinalizadas', ['as' => 'apiv1_conversacion_finalizadas'] );
        $routes->get('contact-center', 'ConversacionControllerApi::listarContactCenter', ['as' => 'apiv1_conversacion_contact_center'] );
        $routes->get('campanias-meta-filtro', 'ConversacionControllerApi::listarCampaniasMetaFiltro', ['as' => 'apiv1_conversacion_campanias_meta_filtro']);
        $routes->get('asesores-filtro', 'ConversacionControllerApi::listarAsesoresFiltro', ['as' => 'apiv1_conversacion_asesores_filtro']);
        $routes->get('customer-success', 'ConversacionControllerApi::listarCustomerSuccess', ['as' => 'apiv1_conversacion_customer_success'] );
        $routes->get('exportar', 'ConversacionControllerApi::exportar', ['as' => 'apiv1_conversacion_exportar']);
        $routes->get('no-leidos', 'ConversacionControllerApi::listarNoLeidos', ['as' => 'apiv1_conversacion_no_leidos'] );
        $routes->get('falta-responder', 'ConversacionControllerApi::listarFaltaResponder', ['as' => 'apiv1_conversacion_falta_responder'] );
        $routes->get('contadores-sidebar', 'ConversacionControllerApi::contadoresSidebar', ['as' => 'apiv1_conversacion_contadores_sidebar'] );
        $routes->get('ventas', 'ConversacionControllerApi::listarVentas', ['as' => 'apiv1_conversacion_ventas']);
        $routes->put('abrir/(:num)', 'ConversacionControllerApi::abrir/$1', ['as' => 'apiv1_conversacion_abrir']);
        $routes->put('etiqueta/(:num)', 'ConversacionControllerApi::actualizarEtiqueta/$1', ['as' => 'apiv1_conversacion_actualizar_etiqueta']);
        $routes->put('(:num)', 'ConversacionControllerApi::editar/$1', ['as' => 'apiv1_conversacion_editar'] );
        $routes->put('temperatura/(:num)', 'ConversacionControllerApi::actualizarTemperatura/$1', ['as' => 'apiv1_conversacion_actualizar_temperatura']);
        $routes->put('finalizar/(:num)', 'ConversacionControllerApi::finalizar/$1', ['as' => 'apiv1_conversacion_finalizar']);
        $routes->put('transferir/(:num)', 'ConversacionControllerApi::transferir/$1', ['as' => 'apiv1_conversacion_transferir']);
        $routes->post('transferir-masivo', 'ConversacionControllerApi::transferirMasivo', ['as' => 'apiv1_conversacion_transferir_masivo']);
        $routes->get('usuarios-disponibles/(:num)', 'ConversacionControllerApi::obtenerUsuariosDisponibles/$1', ['as' => 'apiv1_conversacion_usuarios_disponibles']);
        $routes->get('finalizadas/cliente/(:num)', 'ConversacionControllerApi::listarFinalizadasPorCliente/$1', ['as' => 'apiv1_conversacion_finalizadas_cliente']);


        // Reportes: ConversacionReporteControllerApi + ConversacionReporteUseCase
        $routes->group('reportes', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
            $routes->get('leads-por-asesor', 'ConversacionReporteControllerApi::obtenerLeadsPorAsesor', ['as' => 'apiv1_conversacion_reportes_leads_por_asesor']);
            $routes->get('tasa-cierre-por-asesor', 'ConversacionReporteControllerApi::obtenerTasaCierrePorAsesor', ['as' => 'apiv1_conversacion_reportes_tasa_cierre_por_asesor']);
            $routes->get('metricas-campanias', 'ConversacionReporteControllerApi::obtenerMetricasCampanias', ['as' => 'apiv1_conversacion_reportes_metricas_campanias']);
            $routes->get('eficiencia-campanias', 'ConversacionReporteControllerApi::obtenerEficienciaCampanias', ['as' => 'apiv1_conversacion_reportes_eficiencia_campanias']);
            $routes->get('metricas-origen', 'ConversacionReporteControllerApi::obtenerMetricasPorOrigen', ['as' => 'apiv1_conversacion_reportes_metricas_origen']);
            $routes->get('conversaciones-origen', 'ConversacionReporteControllerApi::listarConversacionesPorOrigen', ['as' => 'apiv1_conversacion_reportes_conversaciones_origen']);

            $routes->get('obtener-ult-conversacion-asesores', 'ConversacionReporteControllerApi::obtenerUltimaConversacionAsesores', ['as' => 'apiv1_conversacion_reportes_obtener_ultima_conversacion_asesor']);
            $routes->get('estados-asesores', 'ConversacionReporteControllerApi::estadosAsesores', ['as' => 'apiv1_conversacion_reportes_estados_asesores']);
        });

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
        $routes->get('listar', 'MotivoConversacionControllerApi::listar', ['as' => 'apiv1_motivo_conversacion_listar'] );
        $routes->get('(:num)', 'MotivoConversacionControllerApi::obtener/$1', ['as' => 'apiv1_motivo_conversacion_obtener'] );
        $routes->post('crear', 'MotivoConversacionControllerApi::crear', ['as' => 'apiv1_motivo_conversacion_crear'] );
        $routes->put('(:num)', 'MotivoConversacionControllerApi::editar/$1', ['as' => 'apiv1_motivo_conversacion_editar'] );
        $routes->delete('(:num)', 'MotivoConversacionControllerApi::eliminar/$1', ['as' => 'apiv1_motivo_conversacion_eliminar'] );
    });

    $routes->group('comentario-conversacion', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
        $routes->get('listar/conversacion/(:num)', 'ComentarioConversacionControllerApi::listar/$1', ['as' => 'apiv1_comentario_conversacion_listar'] );
        $routes->get('(:num)', 'ComentarioConversacionControllerApi::obtener/$1', ['as' => 'apiv1_comentario_conversacion_obtener'] );
        $routes->post('crear', 'ComentarioConversacionControllerApi::crear', ['as' => 'apiv1_comentario_conversacion_crear'] );
        $routes->put('(:num)', 'ComentarioConversacionControllerApi::editar/$1', ['as' => 'apiv1_comentario_conversacion_editar'] );
        $routes->delete('(:num)', 'ComentarioConversacionControllerApi::eliminar/$1', ['as' => 'apiv1_comentario_conversacion_eliminar'] );
    });


});


// Rutas de métricas del conversaciones (solo requiere autenticación)
$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {
    $routes->group('conversacion-metricas', ['namespace' => 'Belly\Conversacion\Controllers\Api'], static function ($routes) {
        $routes->get('dashboard', 'ConversacionMetricaController::dashboard', ['as' => 'apiv1_conversacion_metricas_dashboard']);
    });
});
