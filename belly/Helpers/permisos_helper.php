<?php

use Belly\Services\PermisoService;

if (!function_exists('service_permiso')) {
    function service_permiso(): PermisoService
    {
        static $instance;

        if (!$instance) {
            // log_message('debug', "service_permiso() - Inicializando PermisoService");
            
            $usuario = auth()->getUsuario();

            if (!$usuario || empty($usuario->id)) {
                // log_message('error', "service_permiso() - Usuario no autenticado o inválido");
                throw new \RuntimeException('Usuario no autenticado o inválido.');
            }

            // log_message('debug', "service_permiso() - Usuario autenticado ID: {$usuario->id}");

            $instance = new PermisoService();
            $instance->cargarPermisos($usuario->id);
            
            // log_message('debug', "service_permiso() - PermisoService inicializado y permisos cargados para usuario ID: {$usuario->id}");
        } else {
            // log_message('debug', "service_permiso() - Reutilizando instancia existente de PermisoService");
            
        }

        return $instance;
    }
}
