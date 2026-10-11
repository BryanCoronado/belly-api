<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {

    $routes->group('auth', ['namespace' => 'Belly\Auth\Controllers\Api'], static function ($routes) {
        $routes->post('login', 'AuthController::login', ['as' => 'apiv1_auth_login', 'filter' => NULL]);
        $routes->get('permisos', 'AuthController::getProcedure', ['as' => 'apiv1_auth_procedure']);
        $routes->get('roles', 'AuthController::getRoles', ['as' => 'apiv1_auth_roles']);
        $routes->get('logout', 'AuthController::logout', ['as' => 'apiv1_auth_logout']);
        $routes->post('impersonar/(:num)', 'AuthController::impersonar/$1', ['as' => 'apiv1_auth_impersonar']);
    });
});
