<?php

namespace Belly\Usuarios\UseCases;

use CodeIgniter\Database\ConnectionInterface;
use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Entities\GrupoUsuarioPermisoUsuarioEntity;
use Belly\Usuarios\Entities\UsuarioPermisoEspecialEntity;
use Belly\Usuarios\Models\GrupoUsuarioPermisoUsuarioModel;
use Belly\Usuarios\Models\UsuarioPermisoEspecialModel;

class GrupoUsuarioPermisoUsuarioUseCase
{
    protected ?GrupoUsuarioPermisoUsuarioModel $grupo_usuario_permiso_user_provider = NULL;
    protected UsuarioConfig $config;
    protected ConnectionInterface $db;

    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
        $this->db = \Config\Database::connect();
    }

    public function getGrupoUsuarioPermisoUsuarioProvider(): GrupoUsuarioPermisoUsuarioModel
    {
        if ($this->grupo_usuario_permiso_user_provider !== null) {
            return $this->grupo_usuario_permiso_user_provider;
        }

        $className = $this->config->grupo_usuario_permiso_user_provider;
        $this->grupo_usuario_permiso_user_provider = new $className();

        return $this->grupo_usuario_permiso_user_provider;
    }

    public function listar()
    {
        $grupo_user_permi_usuario_model = $this->getGrupoUsuarioPermisoUsuarioProvider();

        return $grupo_user_permi_usuario_model->select(['grupo_usuario_permiso_usuario.id', 'id_permiso', 'p.permiso', 'id_usuario', 'u.usuario', 'id_grupo_usuario'])
        ->join('usuario as u', 'u.id = grupo_usuario_permiso_usuario.id_usuario')
        ->join('permiso as p', 'p.id = grupo_usuario_permiso_usuario.id_permiso')
        ->paginate(10);
    }

    public function crear(array $datos)
    {
        $grupo_user_permi_usuario_model = $this->getGrupoUsuarioPermisoUsuarioProvider();

        $grupo_usuario_permiso_usuario = new GrupoUsuarioPermisoUsuarioEntity();
        $grupo_usuario_permiso_usuario->id_grupo_usuario = $datos['id_grupo_usuario'];
        $grupo_usuario_permiso_usuario->id_usuario = $datos['id_usuario'];
        $grupo_usuario_permiso_usuario->id_permiso = $datos['id_permiso'];
        $grupo_usuario_permiso_usuario->acc_crear = $datos['acc_crear'];
        $grupo_usuario_permiso_usuario->acc_lectura = $datos['acc_lectura'];
        $grupo_usuario_permiso_usuario->acc_actualizar = $datos['acc_actualizar'];
        $grupo_usuario_permiso_usuario->acc_eliminar = $datos['acc_eliminar'];

        if (!$grupo_user_permi_usuario_model->save($grupo_usuario_permiso_usuario)) {
            throw new \Exception(json_encode($grupo_user_permi_usuario_model->errors()));
        } else {
            $grupo_usuario_permiso_usuario->id = $grupo_user_permi_usuario_model->insertID;
            return $grupo_usuario_permiso_usuario;
        }
    }

    public function editar(array $datos, int $id)
    {
        $grupo_user_permi_usuario_model = $this->getGrupoUsuarioPermisoUsuarioProvider();

        if (isset($id)) {
            if (!empty($grupo_user_permi_usuario_model->find($id))) {
                $grupo_usuario_permiso_usuario = new GrupoUsuarioPermisoUsuarioEntity();
                $grupo_usuario_permiso_usuario->id = $id;
                $grupo_usuario_permiso_usuario->id_grupo_usuario = $datos['id_grupo_usuario'];
                $grupo_usuario_permiso_usuario->id_usuario = $datos['id_usuario'];
                $grupo_usuario_permiso_usuario->id_permiso = $datos['id_permiso'];
                $grupo_usuario_permiso_usuario->acc_crear = $datos['acc_crear'];
                $grupo_usuario_permiso_usuario->acc_lectura = $datos['acc_lectura'];
                $grupo_usuario_permiso_usuario->acc_actualizar = $datos['acc_actualizar'];
                $grupo_usuario_permiso_usuario->acc_eliminar = $datos['acc_eliminar'];

                if (!$grupo_user_permi_usuario_model->save($grupo_usuario_permiso_usuario)) {
                    throw new \Exception(json_encode($grupo_user_permi_usuario_model->errors()));
                }
                return $grupo_usuario_permiso_usuario;
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function eliminar(int $id_grupo_usuario, int $id_usuario, int $id_permiso)
    {
        $grupo_user_permi_usuario_model = $this->getGrupoUsuarioPermisoUsuarioProvider();

        if (isset($id_grupo_usuario) && isset($id_usuario) && isset($id_permiso)) {
            $existe = $grupo_user_permi_usuario_model->where(['id_grupo_usuario' => $id_grupo_usuario,'id_usuario' => $id_usuario,'id_permiso' => $id_permiso])->first();

            if ($existe) {
                $delete=$grupo_user_permi_usuario_model->where(['id_grupo_usuario' => $id_grupo_usuario,'id_usuario' => $id_usuario,'id_permiso' => $id_permiso])->delete();
                if (!$delete) {
                    throw new \Exception(json_encode($grupo_user_permi_usuario_model->errors()));
                }
            }
            return true;
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function obtenerGrupoUsuarioPermisoUsuario(int $grupoUsuarioId, int $usuarioId)
    {
        $db = \Config\Database::connect();
        
        // Obtener datos del grupo y usuario
        $sql_base = "
        SELECT 
            gu.id AS id_grupo_usuario,
            gu.grupo,
            u.id AS id_usuario,
            up.nombres,
            up.apellidos
        FROM grupo_usuario gu
        JOIN usuario u ON u.id = ?
        LEFT JOIN usuario_perfil up ON up.id_usuario = u.id
        WHERE gu.id = ?
        ";
        
        $query_base = $db->query($sql_base, [$usuarioId, $grupoUsuarioId]);
        $row = $query_base->getRow();
        
        if (!$row) {
            return null;
        }
        
        // Obtener SOLO los permisos del grupo + los permisos ya asignados al usuario
        // Esto evita confusión y muestra solo permisos relevantes al contexto del grupo
        $sql_permisos = "
        SELECT 
            p.id,
            p.permiso,
            p.alias,
            p.descripcion,
            COALESCE(guppu.acc_crear, gupr.acc_crear, 0) as acc_crear,
            COALESCE(guppu.acc_lectura, gupr.acc_lectura, 0) as acc_lectura,
            COALESCE(guppu.acc_actualizar, gupr.acc_actualizar, 0) as acc_actualizar,
            COALESCE(guppu.acc_eliminar, gupr.acc_eliminar, 0) as acc_eliminar,
            IF(guppu.id_permiso IS NOT NULL OR gupr.id_permiso IS NOT NULL, 1, 0) as checked
        FROM permiso p
        INNER JOIN (
            -- Permisos del grupo
            SELECT DISTINCT id_permiso
            FROM grupo_usuario_permiso
            WHERE id_grupo_usuario = ?
            AND deleted_at IS NULL
            UNION
            -- Permisos ya asignados al usuario (para no perderlos si no están en el grupo)
            SELECT DISTINCT id_permiso
            FROM grupo_usuario_permiso_usuario
            WHERE id_grupo_usuario = ?
            AND id_usuario = ?
            AND deleted_at IS NULL
        ) AS permisos_relevantes ON permisos_relevantes.id_permiso = p.id
        LEFT JOIN grupo_usuario_permiso gupr 
            ON gupr.id_permiso = p.id
            AND gupr.id_grupo_usuario = ?
            AND gupr.deleted_at IS NULL
        LEFT JOIN grupo_usuario_permiso_usuario guppu
            ON guppu.id_permiso = p.id
            AND guppu.id_grupo_usuario = ?
            AND guppu.id_usuario = ?
            AND guppu.deleted_at IS NULL
        WHERE p.deleted_at IS NULL
        ORDER BY p.id
        ";
        
        $query_permisos = $db->query($sql_permisos, [
            $grupoUsuarioId,  // Para UNION: permisos del grupo
            $grupoUsuarioId,  // Para UNION: permisos del usuario (grupo)
            $usuarioId,  // Para UNION: permisos del usuario (usuario)
            $grupoUsuarioId,  // Para LEFT JOIN grupo
            $grupoUsuarioId,  // Para LEFT JOIN usuario (grupo)
            $usuarioId  // Para LEFT JOIN usuario (usuario)
        ]);
        $permisos_rows = $query_permisos->getResultArray();
        
        // Convertir a array
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
            'id_grupo_usuario' => $row->id_grupo_usuario,
            'grupo' => $row->grupo,
            'id_usuario' => $row->id_usuario,
            'nombres' => $row->nombres,
            'apellidos' => $row->apellidos,
            'permisos' => $permisos
        ];
    }

    public function actualizarPorGrupoUsuarioIDUsuarioID(array $data){
        $permisosValidos = ['acc_lectura', 'acc_crear', 'acc_actualizar', 'acc_eliminar'];
        $idGrupoUsuario = $data['id_grupo_usuario'] ?? null;
        $idUsuario = $data['id_usuario'] ?? null;
        $idPermiso = $data['id_permiso'] ?? null;

        if(!$idGrupoUsuario || !$idUsuario) {
            throw new \Exception('ID de grupo de usuario y usuario son requeridos.');
        }
        $campos = $permisosValidos;
        $valores = array_map(fn($campo) => ($data[$campo] == 1) ? 1 : 0, $permisosValidos);

        $camposInsert = implode(', ', $campos);
        $placeholders = implode(', ', array_fill(0, count($campos), '?'));
        $camposUpdate = implode(', ', array_map(fn($c) => "$c = VALUES($c)", $campos));

        $sql = "INSERT INTO grupo_usuario_permiso_usuario (id_grupo_usuario, id_permiso, id_usuario, $camposInsert, created_at) VALUES (?, ?, ?, $placeholders, NOW()) 
        ON DUPLICATE KEY UPDATE $camposUpdate, updated_at = NOW()";

        $params = array_merge([
            $idGrupoUsuario,
            $idPermiso,
            $idUsuario
        ], $valores);

        try {
            $result = $this->db->query($sql, $params);
            if (!$result) {
                $error = $this->db->error();
                log_message('error', "GrupoUsuarioPermisoUsuarioUseCase::actualizarPorGrupoUsuarioIDUsuarioID - Error SQL: " . ($error['message'] ?? 'Error desconocido'));
                throw new \RuntimeException('Error al actualizar los permisos: ' . ($error['message'] ?? 'Error desconocido'));
            }
        } catch (\Throwable $e) {
            log_message('error', "GrupoUsuarioPermisoUsuarioUseCase::actualizarPorGrupoUsuarioIDUsuarioID - Excepción: " . $e->getMessage());
            throw new \Exception('Error al actualizar los permisos: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Permisos actualizados correctamente.'
        ];
    }
}
