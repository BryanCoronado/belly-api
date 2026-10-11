<?php
namespace Belly\Config;


use CodeIgniter\Router\RouteCollection;

$routes->options('v1/(:any)', static function () {
    $response = response();
    $response->setStatusCode(204);
    $response->setHeader('Allow:', 'OPTIONS, GET, POST, PUT, DELETE');

    return $response;
}, ['filter' => 'cors']);
