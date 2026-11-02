<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        \Belly\Services\ContextoEmpresa::limpiar();

        $response = service('response');
        $this->aplicar($request, $response);

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response->setStatusCode(204);
            $response->setBody('');

            return $response;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $this->aplicar($request, $response);

        return $response;
    }

    private function aplicar(RequestInterface $request, ResponseInterface $response): void
    {
        $origen = trim($request->getHeaderLine('Origin'));
        $permitido = $origen !== '' && $this->origenPermitido($origen);
        if ($permitido) {
            $response->setHeader('Access-Control-Allow-Origin', $origen);
            $response->setHeader('Vary', 'Origin');
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE, PATCH');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->setHeader('Access-Control-Max-Age', '86400');
    }

    private function origenPermitido(string $origen): bool
    {
        foreach ($this->origenes() as $permitido) {
            if (strcasecmp($origen, $permitido) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function origenes(): array
    {
        $lista = [];
        foreach (explode(',', (string) env('SOCKET_ORIGENES', '')) as $origen) {
            $origen = rtrim(trim($origen), '/');
            if ($origen !== '') {
                $lista[] = $origen;
            }
        }

        $tienda = rtrim((string) (function_exists('config') ? config('Plataforma')->urlTienda() : ''), '/');
        if ($tienda !== '') {
            $lista[] = $tienda;
        }

        if (env('CI_ENVIRONMENT') === 'development') {
            $lista[] = 'http://localhost:5173';
            $lista[] = 'http://127.0.0.1:5173';
        }

        return array_values(array_unique($lista));
    }
}
