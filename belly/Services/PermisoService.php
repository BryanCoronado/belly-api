<?php

namespace Belly\Services;

use Config\Database;

class PermisoService
{
    protected array $permisos = [];

    public function cargarPermisos(int $idUsuario): void
    {
        $db = Database::connect();
        
        try {
            $query = $db->query("CALL get_usuario_permisos('$idUsuario');");
            $result = $query->getResult();
            
            $totalPermisos = count($result);
            
            foreach ($result as $row) {
                $this->permisos[$row->alias] = [
                    'acc_crear' => (bool)$row->acc_crear,
                    'acc_lectura'   => (bool)$row->acc_lectura,
                    'acc_actualizar' => (bool)$row->acc_actualizar,
                    'acc_eliminar' => (bool)$row->acc_eliminar,
                ];
            }
            
        } catch (\Exception $e) {
            log_message('error', "PermisoService::cargarPermisos - ERROR para usuario ID: {$idUsuario} - Mensaje: " . $e->getMessage());
            throw $e;
        } finally {
            $db->close();
        }
    }

    public function tienePermiso(string $alias, string $accion = 'acc_lectura'): bool
    {
        $permisoExiste = isset($this->permisos[$alias]);
        $valorPermiso = $permisoExiste ? ($this->permisos[$alias][$accion] ? true : false) : null;
        $tienePermiso = $permisoExiste && $valorPermiso === true;
        
        // Log solo cuando NO tiene permiso
        if (!$tienePermiso) {
            $usuarioId = $this->getUsuarioId() ?? 'N/A';
        }
        
        return $tienePermiso;
    }
    
    /**
     * Obtener ID del usuario actual (para logs)
     */
    private function getUsuarioId(): ?int
    {
        try {
            if (function_exists('auth') && auth()->getUsuario()) {
                return auth()->getUsuario()->id ?? null;
            }
        } catch (\Exception $e) {
            // Ignorar errores al obtener usuario
        }
        return null;
    }

    public function getPermisos(): array
    {
        return $this->permisos;
    }
}
