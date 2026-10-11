<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => null], static function ($routes) {
    $routes->group('webhooks', ['namespace' => 'Belly\Webhooks\Controllers\Api'], static function ($routes) {
        $routes->get('webhook_meta', 'WebhookController::conectarMeta', ['as' => 'apiv1_webhook_meta_get']);
        $routes->post('webhook_meta', 'WebhookController::conectarMeta', ['as' => 'apiv1_webhook_meta_post']);
    });
});
