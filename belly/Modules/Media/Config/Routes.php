<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {

    $routes->group('media', ['namespace' => 'Belly\Media\Controllers\Api'], static function ($routes) {
        $routes->get('listar-imagenes', 'MediaControllerApi::listarImagenes', ['as' => 'apiv1_media_listar_imagenes']);
        $routes->get('listar-videos', 'MediaControllerApi::listarVideos', ['as' => 'apiv1_media_listar_videos']);
        $routes->get('listar-grupos-galeria', 'MediaControllerApi::listarGruposGaleria', ['as' => 'apiv1_media_listar_grupos_galeria']);
        $routes->get('listar-campanias-galeria', 'MediaControllerApi::listarCampaniasGaleria', ['as' => 'apiv1_media_listar_campanias_galeria']);
        $routes->post('subir-galeria', 'MediaControllerApi::subirGaleria', ['as' => 'apiv1_media_subir_galeria']);
        $routes->put('editar-galeria/(:num)', 'MediaControllerApi::editarGaleria/$1', ['as' => 'apiv1_media_editar_galeria']);
        $routes->delete('eliminar-galeria/(:num)', 'MediaControllerApi::eliminarGaleria/$1', ['as' => 'apiv1_media_eliminar_galeria']);

        $routes->get('listar-documentos', 'MediaControllerApi::listarDocumentos', ['as' => 'apiv1_media_listar_documentos']);
        $routes->post('subir-documento', 'MediaControllerApi::subirDocumento', ['as' => 'apiv1_media_subir_documento']);
        $routes->delete('eliminar-documento/(:num)', 'MediaControllerApi::eliminarDocumento/$1', ['as' => 'apiv1_media_eliminar_documento']);
    });

    $routes->group('grupo_producto', ['namespace' => 'Belly\Media\Controllers\Api'], static function ($routes) {
        $routes->get('listar', 'GrupoProductoController::listar', ['as' => 'apiv1_grupo_producto_listar']);
        $routes->post('crear', 'GrupoProductoController::crear', ['as' => 'apiv1_grupo_producto_crear']);
        $routes->put('editar/(:num)', 'GrupoProductoController::editar/$1', ['as' => 'apiv1_grupo_producto_editar']);
        $routes->post('editar-media/(:num)', 'GrupoProductoController::editarMedia/$1', ['as' => 'apiv1_grupo_producto_editar_media']);
        $routes->delete('eliminar/(:num)', 'GrupoProductoController::eliminar/$1', ['as' => 'apiv1_grupo_producto_eliminar']);
        $routes->get('ver/(:num)', 'GrupoProductoController::ver/$1', ['as' => 'apiv1_grupo_producto_ver']);
    });

    $routes->group('producto', ['namespace' => 'Belly\Media\Controllers\Api'], static function ($routes) {
        $routes->post('crear', 'ProductoController::crear', ['as' => 'apiv1_producto_crear']);
        $routes->get('listar', 'ProductoController::listar', ['as' => 'apiv1_producto_listar']);
        $routes->get('grupo/(:num)', 'ProductoController::listarPorGrupo/$1', ['as' => 'apiv1_producto_por_grupo']);
        $routes->put('editar/(:num)', 'ProductoController::editar/$1', ['as' => 'apiv1_producto_editar']);
        $routes->delete('eliminar/(:num)', 'ProductoController::eliminar/$1', ['as' => 'apiv1_producto_eliminar']);
        $routes->get('ver/(:num)', 'ProductoController::ver/$1', ['as' => 'apiv1_producto_ver']);

        $routes->post('crear-catalogo', 'ProductoController::crearConCatalogo', ['as' => 'apiv1_producto_crear_catalogo']);
        $routes->post('editar-catalogo/(:num)', 'ProductoController::editarConCatalogo/$1', ['as' => 'apiv1_producto_editar_catalogo']);
        $routes->post('editar-hero-media/(:num)', 'ProductoController::editarHeroMedia/$1', ['as' => 'apiv1_producto_editar_hero_media']);
    });

    $routes->group('producto_referencia', ['namespace' => 'Belly\Media\Controllers\Api'], static function ($routes) {
        $routes->post('crear', 'ProductoReferenciaController::crear', ['as' => 'apiv1_producto_referencia_crear']);
        $routes->get('listar', 'ProductoReferenciaController::listar', ['as' => 'apiv1_producto_referencia_listar']);
        $routes->get('producto/(:num)', 'ProductoReferenciaController::listarPorProducto/$1', ['as' => 'apiv1_producto_referencia_por_producto']);
        $routes->put('editar/(:num)', 'ProductoReferenciaController::editar/$1', ['as' => 'apiv1_producto_referencia_editar']);
        $routes->delete('eliminar/(:num)', 'ProductoReferenciaController::eliminar/$1', ['as' => 'apiv1_producto_referencia_eliminar']);
        $routes->get('ver/(:num)', 'ProductoReferenciaController::ver/$1', ['as' => 'apiv1_producto_referencia_ver']);
    });
});
