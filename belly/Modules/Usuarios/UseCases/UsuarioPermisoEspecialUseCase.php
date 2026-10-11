<?php

namespace Belly\Usuarios\UseCases;

use CodeIgniter\Database\ConnectionInterface;
use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Entities\UsuarioPermisoEspecialEntity;
use Belly\Usuarios\Models\UsuarioModel;
use Belly\Usuarios\Models\UsuarioPermisoEspecialModel;

class UsuarioPermisoEspecialUseCase
{
    protected ?UsuarioPermisoEspecialModel $usuario_permiso_especial_provider = NULL;
    protected UsuarioConfig $config;
    protected ConnectionInterface $db;

    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
        $this->db = \Config\Database::connect();
    }

    public function getUsuarioPermisoEspecialProvider(): UsuarioPermisoEspecialModel
    {
        if ($this->usuario_permiso_especial_provider !== null) {
            return $this->usuario_permiso_especial_provider;
        }

        $className = $this->config->usuario_permiso_especial_provider;
        $this->usuario_permiso_especial_provider = new $className();

        return $this->usuario_permiso_especial_provider;
    }

    // public function listar()
    // {
    //     $user_permi_esp_model = $this->getUsuarioPermisoEspecialProvider();

    //     return $user_permi_esp_model->select(['id', 'id_permmiso', 'id_usuario'])->paginate(10);
    // }

    public function crear(array $datos)
    {
        $user_permi_esp_model = $this->getUsuarioPermisoEspecialProvider();

        // Validar claves requeridas
        $datos['acc_lectura'] = $datos['acc_lectura'] ?? 0;
        $datos['acc_crear'] = $datos['acc_crear'] ?? 0;
        $datos['acc_actualizar'] = $datos['acc_actualizar'] ?? 0;
        $datos['acc_eliminar'] = $datos['acc_eliminar'] ?? 0;

        $user_permi_esp_entity = new UsuarioPermisoEspecialEntity();
        $user_permi_esp_entity->id_usuario = $datos['id_usuario'];
        $user_permi_esp_entity->id_permiso = $datos['id_permiso'];
        $user_permi_esp_entity->acc_crear = $datos['acc_crear'];
        $user_permi_esp_entity->acc_lectura = $datos['acc_lectura'];
        $user_permi_esp_entity->acc_actualizar = $datos['acc_actualizar'];
        $user_permi_esp_entity->acc_eliminar = $datos['acc_eliminar'];

        if (!$user_permi_esp_model->save($user_permi_esp_entity)) {
            throw new \Exception(json_encode($user_permi_esp_model->errors()));
        } else {
            $user_permi_esp_entity->id = $user_permi_esp_model->insertID;
            return $user_permi_esp_entity;
        }
    }

    public function editar(array $data)
    {
        $permisosValidos = ['acc_lectura', 'acc_crear', 'acc_actualizar', 'acc_eliminar'];
        $idUsuario = $data['id_usuario'] ?? null;
        $idPermiso = $data['id_permiso'] ?? null;

        if (!$idUsuario || !$idPermiso) {
            throw new \InvalidArgumentException('Faltan datos obligatorios.');
        }

        $campos = $permisosValidos;
        $valores = array_map(fn($campo) => ($data[$campo] == 1) ? 1 : 0, $permisosValidos);

        $camposInsert = implode(', ', $campos);
        $placeholders = implode(', ', array_fill(0, count($campos), '?'));
        $camposUpdate = implode(', ', array_map(fn($c) => "$c = VALUES($c)", $campos));

        $sql = "INSERT INTO usuario_permiso_especial (id_usuario, id_permiso, $camposInsert, created_at) VALUES (?, ?, $placeholders, NOW()) 
        ON DUPLICATE KEY UPDATE $camposUpdate, updated_at = NOW()";

        $params = array_merge([
            $idUsuario,
            $idPermiso
        ], $valores);

        try {
            $result = $this->db->query($sql, $params);
            if (!$result) {
                $error = $this->db->error();
                throw new \RuntimeException('Error al actualizar los permisos: ' . ($error['message'] ?? 'Error desconocido'));
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Excepción al actualizar permisos: ' . $e->getMessage(), 0, $e);
        }

        return [
            'success' => true,
            'message' => 'Permisos actualizados correctamente.'
        ];
    }

    public function eliminar(int $id)
    {
        $user_permi_esp_model = $this->getUsuarioPermisoEspecialProvider();

        if (isset($id) && $id !== null) {
            $existe_grupo = $user_permi_esp_model->find($id);

            if (!empty($existe_grupo)) {
                if (!$user_permi_esp_model->delete($id)) {
                    throw new \Exception(lang('UsuariosL.noDeletePermisoEspecial'));
                }

                return 'se eliminó correctamente';
            } else {
                throw new \Exception(lang('UsuariosL.noIdPermisoEspecial'));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function obtenerpermisoEspecialPorUsuario(int $id_usuario)
    {
        $db = \Config\Database::connect();
        
        // Primero obtener el nombre del usuario
        $sqlUsuario = "SELECT CONCAT(usuario_perfil.nombres, ' ', usuario_perfil.apellidos) AS usuario
            FROM usuario
            JOIN usuario_perfil ON usuario_perfil.id_usuario = usuario.id
            WHERE usuario.id = :id_usuario:
            AND usuario_perfil.id_tipo_documento = 1
            LIMIT 1";
        
        $queryUsuario = $db->query($sqlUsuario, ['id_usuario' => $id_usuario]);
        $rowUsuario = $queryUsuario->getRow();
        
        if (!$rowUsuario) {
            return null;
        }
        
        // Luego obtener solo los permisos especiales del usuario
        $sql = "SELECT
            GROUP_CONCAT(
                JSON_OBJECT(
                    'id', permiso.id,
                    'permiso', permiso.permiso,
                    'alias', permiso.alias,
                    'descripcion', permiso.descripcion,
                    'acc_crear', usuario_permiso_especial.acc_crear,
                    'acc_lectura', usuario_permiso_especial.acc_lectura,
                    'acc_actualizar', usuario_permiso_especial.acc_actualizar,
                    'acc_eliminar', usuario_permiso_especial.acc_eliminar
                )
                SEPARATOR ','
            ) AS permisos_json
            FROM usuario_permiso_especial
            JOIN permiso ON permiso.id = usuario_permiso_especial.id_permiso
            WHERE usuario_permiso_especial.id_usuario = :id_usuario:";

        $query = $db->query($sql, ['id_usuario' => $id_usuario]);

        if (!$query) {
            $error = $db->error();
            throw new \RuntimeException('Error en la consulta SQL: ' . $error['message']);
        }

        $row = $query->getRow();
        $permisosJson = $row ? $row->permisos_json : null;
        
        // Si hay permisos, parsear el JSON, si no, devolver array vacío
        if ($permisosJson && $permisosJson !== 'NULL') {
            $permisos = json_decode('[' . $permisosJson . ']', true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Si hay error al parsear, devolver array vacío
                $permisos = [];
            }
        } else {
            $permisos = [];
        }
        
        return [
            'usuario' => $rowUsuario->usuario,
            'permisos' => $permisos ?: []
        ];
    }
}
