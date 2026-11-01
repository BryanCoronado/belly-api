<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group('v1', ['namespace' => 'Belly\Catalogo\Controllers\Api'], static function ($routes) {
    $routes->get('publico/catalogo/(:segment)/seo', 'CatalogoControllerApi::seo/$1', ['as' => 'apiv1_catalogo_publico_seo']);
    $routes->get('publico/catalogo/(:segment)', 'CatalogoControllerApi::publico/$1', ['as' => 'apiv1_catalogo_publico']);
});

$routes->group('c', ['namespace' => 'Belly\Catalogo\Controllers'], static function ($routes) {
    $routes->get('(:segment)/p/(:num)', 'CatalogoSeoController::pagina/$1/$2', ['as' => 'catalogo_seo_producto']);
    $routes->get('(:segment)', 'CatalogoSeoController::pagina/$1', ['as' => 'catalogo_seo']);
});

// Ver el catálogo: cualquier persona de la empresa (los asesores lo consultan al atender).
$routes->group('v1', ['filter' => ['authFilter']], static function ($routes) {
    $routes->group('catalogo', ['namespace' => 'Belly\Catalogo\Controllers\Api'], static function ($routes) {
        $routes->get('familias', 'CatalogoControllerApi::listarFamilias', ['as' => 'apiv1_catalogo_familias']);
        $routes->get('productos', 'CatalogoControllerApi::listarProductos', ['as' => 'apiv1_catalogo_productos']);
        $routes->get('productos/(:num)', 'CatalogoControllerApi::verProducto/$1', ['as' => 'apiv1_catalogo_producto']);
        $routes->get('vitrina', 'CatalogoControllerApi::verVitrina', ['as' => 'apiv1_catalogo_vitrina']);
    });
});

// Crear, editar y borrar: solo administradores.
$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:admin']], static function ($routes) {
    $routes->group('catalogo', ['namespace' => 'Belly\Catalogo\Controllers\Api'], static function ($routes) {
        $routes->post('familias', 'CatalogoControllerApi::crearFamilia', ['as' => 'apiv1_catalogo_familias_crear']);
        $routes->put('familias/(:num)', 'CatalogoControllerApi::actualizarFamilia/$1', ['as' => 'apiv1_catalogo_familias_actualizar']);
        $routes->delete('familias/(:num)', 'CatalogoControllerApi::eliminarFamilia/$1', ['as' => 'apiv1_catalogo_familias_eliminar']);

        $routes->post('productos', 'CatalogoControllerApi::crearProducto', ['as' => 'apiv1_catalogo_productos_crear']);
        $routes->post('productos/imagen', 'CatalogoControllerApi::subirImagen', ['as' => 'apiv1_catalogo_productos_imagen']);
        $routes->put('productos/(:num)', 'CatalogoControllerApi::actualizarProducto/$1', ['as' => 'apiv1_catalogo_productos_actualizar']);
        $routes->put('productos/(:num)/estado', 'CatalogoControllerApi::cambiarEstadoProducto/$1', ['as' => 'apiv1_catalogo_productos_estado']);
        $routes->delete('productos/(:num)', 'CatalogoControllerApi::eliminarProducto/$1', ['as' => 'apiv1_catalogo_productos_eliminar']);

        $routes->get('vitrina/logo', 'CatalogoControllerApi::verLogoVitrina', ['as' => 'apiv1_catalogo_vitrina_logo']);
        $routes->put('vitrina', 'CatalogoControllerApi::guardarVitrina', ['as' => 'apiv1_catalogo_vitrina_guardar']);
    });
});
