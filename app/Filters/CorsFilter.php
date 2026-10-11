<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class CorsFilter implements FilterInterface {

    public function before(RequestInterface $request, $arguments = null) {
        $response = service('response');
                
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE, PATCH');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Max-Age', '86400');

        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(204);
            $response->setBody('');
            return $response;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {
        if (!$response->hasHeader('Access-Control-Allow-Origin')) {
            $response->setHeader('Access-Control-Allow-Origin', '*');
        }
        if (!$response->hasHeader('Access-Control-Allow-Methods')) {
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE, PATCH');
        }
        if (!$response->hasHeader('Access-Control-Allow-Headers')) {
            $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        }
        
        return $response;
    }
}
