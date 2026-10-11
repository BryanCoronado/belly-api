<?php

namespace Belly\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PermisosFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $uri = $request->getUri()->getPath();
        
        if (empty($arguments) || !isset($arguments[0])) {
            log_message('error', "PermisosFilter::before - Permiso no especificado en los argumentos - URI: {$uri}");
            return $this->unauthorized('Permiso no especificado.');
        }

        $alias  = $arguments[0];
        $acciones = isset($arguments[1])
            ? explode('|', $arguments[1])
            : ['acc_lectura'];

        try {
            $usuario = auth()->getUsuario();

            if (!$usuario || empty($usuario->id)) {
                log_message('error', "PermisosFilter::before - Usuario no autenticado - URI: {$uri}");
                return $this->unauthorized('Usuario no autenticado.');
            }

            $permisos = service_permiso();

            // Reglas jerárquicas: acciones que requieren otra
            $jerarquia = [
                'acc_crear' => ['acc_lectura'],
                'acc_actualizar' => ['acc_lectura'],
                'acc_eliminar' => ['acc_lectura', 'acc_crear'],
            ];

            $permitido = false;

            foreach ($acciones as $accion) {
                $accionesRequeridas = $jerarquia[$accion] ?? [];
                $accionesRequeridas[] = $accion;

                $tieneTodo = true;
                
                foreach ($accionesRequeridas as $accionRequerida) {
                    if (!$permisos->tienePermiso($alias, $accionRequerida)) {
                        $tieneTodo = false;
                        break;
                    }
                }

                if ($tieneTodo) {
                    $permitido = true;
                    break;
                }
            }

            if (!$permitido) {
                $mensajeError = "Permiso insuficiente para [{$alias}] con acciones [" . implode('|', $acciones) . "]";
                return $this->unauthorized($mensajeError);
            }

            return; // permitir continuar
        } catch (\Throwable $e) {
            $usuarioId = isset($usuario) && !empty($usuario->id) ? $usuario->id : 'N/A';
            log_message('error', "PermisosFilter::before - EXCEPCIÓN - Usuario ID: {$usuarioId} - Permiso: {$alias} - URI: {$uri} - Error: " . $e->getMessage());
            return $this->unauthorized('Error interno: ' . $e->getMessage());
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No necesario
    }

    private function unauthorized(string $mensaje)
    {
        return service('response')
            ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)
            ->setJSON([
                'status'   => ResponseInterface::HTTP_FORBIDDEN,
                'error'    => 'PermisosFilter',
                'messages' => ['error' => $mensaje],
            ]);
    }
}
