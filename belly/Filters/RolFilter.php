<?php

namespace Belly\Filters;

use Belly\Services\Autorizacion;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Uso: 'rolFilter:admin' (administrador de la empresa) o 'rolFilter:operador' (dueño de la plataforma).
 * Debe ir después de authFilter.
 */
class RolFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $rol = $arguments[0] ?? 'admin';

        $permitido = match ($rol) {
            'operador' => Autorizacion::esOperadorPlataforma(),
            'admin' => Autorizacion::esAdministradorEmpresa(),
            default => false,
        };

        if (! $permitido) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)
                ->setJSON([
                    'status' => ResponseInterface::HTTP_FORBIDDEN,
                    'error' => 'RolFilter',
                    'messages' => ['error' => 'No tienes permiso para realizar esta acción.'],
                ]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
