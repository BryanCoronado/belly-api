<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => null], static function ($routes) {
    $routes->group('webhooks', ['namespace' => 'Belly\Webhooks\Controllers\Api'], static function ($routes) {
        $routes->get('meta/(:segment)', 'WebhookController::verificarMeta/$1', ['as' => 'apiv1_webhook_meta_get']);
        $routes->post('meta/(:segment)', 'WebhookController::recibirMeta/$1', ['as' => 'apiv1_webhook_meta_post']);
    });
});
