<?php
namespace Belly\Usuarios\UseCases;

use CodeIgniter\Database\ConnectionInterface;
use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Entities\GrupoUsuarioPermisoRolEntity;
use Belly\Usuarios\Models\GrupoUsuarioPermisoRolModel;

class GrupoUsuarioPermisoRolUseCase
{
    protected ?GrupoUsuarioPermisoRolModel $grupo_usuario_permiso_rol_provider = NULL;
    protected UsuarioConfig $config;
    protected ConnectionInterface $db;

    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
        $this->db = \Config\Database::connect();
    }

    public function getGrupoUsuarioPermisoRolProvider(): GrupoUsuarioPermisoRolModel
    {
        if ($this->grupo_usuario_permiso_rol_provider !== null) {
            return $this->grupo_usuario_permiso_rol_provider;
        }

        $className = $this->config->grupo_usuario_permiso_rol_provider;
        $this->grupo_usuario_permiso_rol_provider = new $className();

        return $this->grupo_usuario_permiso_rol_provider;
    }

    public function encontrarPermisosPorGrupoUsuarioYGrupoUsuarioRol(int $grupoUsuarioId, int $grupoUsuarioRolId)
    {
        $permisos = $this->getGrupoUsuarioPermisoRolProvider()
            ->where('id_grupo_usuario', $grupoUsuarioId)
            ->where('id_grupo_usuario_rol', $grupoUsuarioRolId)
            ->findAll();

        return $permisos;
    }

    /**
     * Obtiene todos los permisos disponibles para un grupo de usuario y un rol específico.
     * Retorna TODOS los permisos del sistema, incluso si no están asignados al grupo.
     * Combina permisos generales del grupo con permisos específicos del rol.
     * Similar a obtenerGrupoUsuarioPermisoUsuario pero para roles.
     * 
     * @param int $grupoUsuarioId ID del grupo de usuario
     * @param int $grupoUsuarioRolId ID del rol dentro del grupo
     * @return array|null Retorna array con grupo, rol y permisos, o null si no existe el grupo/rol
     */
    public function obtenerPermisosPorGrupoUsuarioYGrupoUsuarioRol(int $grupoUsuarioId, int $grupoUsuarioRolId)
    {
        $db = \Config\Database::connect();
        
        // Obtener datos del grupo y rol
        $sql_base = "
        SELECT 
            gu.id AS id_grupo_usuario,
            gu.grupo,
            gur.id AS id_grupo_usuario_rol,
            gur.rol
        FROM grupo_usuario gu
        JOIN grupo_usuario_rol gur ON gur.id = ?
        WHERE gu.id = ?
        ";
        
        $query_base = $db->query($sql_base, [$grupoUsuarioRolId, $grupoUsuarioId]);
        $row_base = $query_base->getRow();
        
        if (!$row_base) {
            return null;
        }
        
        // Obtener SOLO los permisos del grupo + los permisos ya asignados al rol
        // Esto evita confusión y muestra solo permisos relevantes al contexto del grupo
        $sql_permisos = "
        SELECT 
            p.id,
            p.permiso,
            p.alias,
            p.descripcion,
            COALESCE(gupr_rol.acc_crear, gupr.acc_crear, 0) as acc_crear,
            COALESCE(gupr_rol.acc_lectura, gupr.acc_lectura, 0) as acc_lectura,
            COALESCE(gupr_rol.acc_actualizar, gupr.acc_actualizar, 0) as acc_actualizar,
            COALESCE(gupr_rol.acc_eliminar, gupr.acc_eliminar, 0) as acc_eliminar,
            IF(gupr_rol.id_permiso IS NOT NULL OR gupr.id_permiso IS NOT NULL, 1, 0) as checked
        FROM permiso p
        INNER JOIN (
            -- Permisos del grupo
            SELECT DISTINCT id_permiso
            FROM grupo_usuario_permiso
            WHERE id_grupo_usuario = ?
            AND deleted_at IS NULL
            UNION
            -- Permisos ya asignados al rol (para no perderlos si no están en el grupo)
            SELECT DISTINCT id_permiso
            FROM grupo_usuario_permiso_rol
            WHERE id_grupo_usuario = ?
            AND id_grupo_usuario_rol = ?
            AND deleted_at IS NULL
        ) AS permisos_relevantes ON permisos_relevantes.id_permiso = p.id
        LEFT JOIN grupo_usuario_permiso gupr 
            ON gupr.id_permiso = p.id
            AND gupr.id_grupo_usuario = ?
            AND gupr.deleted_at IS NULL
        LEFT JOIN grupo_usuario_permiso_rol gupr_rol
            ON gupr_rol.id_permiso = p.id
            AND gupr_rol.id_grupo_usuario = ?
            AND gupr_rol.id_grupo_usuario_rol = ?
            AND gupr_rol.deleted_at IS NULL
        WHERE p.deleted_at IS NULL
        ORDER BY p.id
        ";
        
        $query_permisos = $db->query($sql_permisos, [
            $grupoUsuarioId,  // Para UNION: permisos del grupo
            $grupoUsuarioId,  // Para UNION: permisos del rol (grupo)
            $grupoUsuarioRolId,  // Para UNION: permisos del rol (rol)
            $grupoUsuarioId,  // Para LEFT JOIN grupo
            $grupoUsuarioId,  // Para LEFT JOIN rol (grupo)
            $grupoUsuarioRolId  // Para LEFT JOIN rol (rol)
        ]);
        
        $permisos_rows = $query_permisos->getResultArray();
        
        // Convertir a array (similar a obtenerGrupoUsuarioPermisoUsuario)
        $permisos = [];
        foreach ($permisos_rows as $perm) {
            $permisos[] = [
                'id' => (int)$perm['id'],
                'permiso' => $perm['permiso'],
                'alias' => $perm['alias'],
                'descripcion' => $perm['descripcion'],
                'acc_crear' => (int)$perm['acc_crear'],
                'acc_lectura' => (int)$perm['acc_lectura'],
                'acc_actualizar' => (int)$perm['acc_actualizar'],
                'acc_eliminar' => (int)$perm['acc_eliminar'],
                'checked' => (bool)$perm['checked']
            ];
        }
        
        return [
            'id_grupo_usuario' => (string) $row_base->id_grupo_usuario,
            'grupo' => $row_base->grupo,
            'id_grupo_usuario_rol' => (string) $row_base->id_grupo_usuario_rol,
            'rol' => $row_base->rol,
            'permisos' => $permisos
        ];
    }

    public function actualizarPermisosPorGrupoUsuarioYGrupoUsuarioRol(array $data): array
    {
        $permisosValidos = ['acc_lectura', 'acc_crear', 'acc_actualizar', 'acc_eliminar'];
        $idGrupoUsuario = $data['id_grupo_usuario'] ?? null;
        $idGrupoUsuarioRol = $data['id_grupo_usuario_rol'] ?? null;
        $idPermiso = $data['id_permiso'] ?? null;

        if (!$idGrupoUsuario || !$idGrupoUsuarioRol || !$idPermiso) {
            throw new \InvalidArgumentException('Faltan datos obligatorios.');
        }

        $db = $this->db;
        $grupoUsuario = $db->table('grupo_usuario')->where('id', $idGrupoUsuario)->get()->getRow();
        if (!$grupoUsuario) {
            throw new \RuntimeException('El grupo de usuario no existe.');
        }
        $grupoUsuarioRol = $db->table('grupo_usuario_rol')->where('id', $idGrupoUsuarioRol)->get()->getRow();
        if (!$grupoUsuarioRol) {
            throw new \RuntimeException('El rol de grupo de usuario no existe.');
        }
        $permiso = $db->table('permiso')->where('id', $idPermiso)->get()->getRow();
        if (!$permiso) {
            throw new \RuntimeException('El permiso no existe.');
        }

        $campos = $permisosValidos;
        $valores = array_map(fn($campo) => ($data[$campo] == 1) ? 1 : 0, $permisosValidos);

        $camposInsert = implode(', ', $campos);
        $placeholders = implode(', ', array_fill(0, count($campos), '?'));
        $camposUpdate = implode(', ', array_map(fn($c) => "$c = VALUES($c)", $campos));

        $sql = "INSERT INTO grupo_usuario_permiso_rol (id_grupo_usuario, id_permiso, id_grupo_usuario_rol, $camposInsert, created_at) VALUES (?, ?, ?, $placeholders, NOW()) 
        ON DUPLICATE KEY UPDATE $camposUpdate, updated_at = NOW()";

        $params = array_merge([
            $idGrupoUsuario,
            $idPermiso,
            $idGrupoUsuarioRol
        ], $valores);

        try {
            $result = $this->db->query($sql, $params);
            if (!$result) {
                $error = $this->db->error();
                log_message('error', "GrupoUsuarioPermisoRolUseCase::actualizarPermisosPorGrupoUsuarioYGrupoUsuarioRol - Error SQL: " . ($error['message'] ?? 'Error desconocido'));
                throw new \RuntimeException('Error al actualizar los permisos: ' . ($error['message'] ?? 'Error desconocido'));
            }
        } catch (\Throwable $e) {
            log_message('error', "GrupoUsuarioPermisoRolUseCase::actualizarPermisosPorGrupoUsuarioYGrupoUsuarioRol - Excepción: " . $e->getMessage());
            throw new \RuntimeException('Excepción al actualizar permisos: ' . $e->getMessage(), 0, $e);
        }

        return [
            'success' => true,
            'message' => 'Permisos actualizados correctamente.'
        ];
    }

    public function eliminar(int $id_grupo_usuario, int $id_grupo_usuario_rol, int $id_permiso){
        $grupo_usuario_permiso_rol_model = $this->getGrupoUsuarioPermisoRolProvider();

        if (isset($id_grupo_usuario) && isset($id_grupo_usuario_rol) && isset($id_permiso)) {
            $existe = $grupo_usuario_permiso_rol_model->where(['id_grupo_usuario' => $id_grupo_usuario,'id_grupo_usuario_rol' => $id_grupo_usuario_rol,'id_permiso' => $id_permiso])->first();

            if ($existe) {
                $delete=$grupo_usuario_permiso_rol_model->where(['id_grupo_usuario' => $id_grupo_usuario,'id_grupo_usuario_rol' => $id_grupo_usuario_rol,'id_permiso' => $id_permiso])->delete();
                if (!$delete) {
                    throw new \Exception(json_encode($grupo_usuario_permiso_rol_model->errors()));
                }
            }
            return true;
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }
}
