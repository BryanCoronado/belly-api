<?php
namespace Belly\Permisos\UseCases;
use Belly\Permisos\Config\PermisosConfig;
use CodeIgniter\Database\ConnectionInterface;
use Belly\Permisos\Entities\GrupoUsuarioPermisoEntity;
use Belly\Permisos\Models\GrupoUsuarioPermisoModel;

class GrupoUsuarioPermisoUseCase
{
    protected ?GrupoUsuarioPermisoModel $grupo_usuario_permiso_provider = NULL;
    protected PermisosConfig $config;
    protected ConnectionInterface $db;

    public function __construct(PermisosConfig $config)
    {
        $this->config = $config;
        $this->db = \Config\Database::connect();
    }

    public function getGrupoUsuarioPermisoProvider(): GrupoUsuarioPermisoModel
    {
        if ($this->grupo_usuario_permiso_provider !== null) {
            return $this->grupo_usuario_permiso_provider;
        }

        $className = $this->config->grupo_usuario_permiso_provider;
        $this->grupo_usuario_permiso_provider = new $className();

        return $this->grupo_usuario_permiso_provider;
    }

    public function obtenerGruposUsuariosPermisos($idGrupoUsuario)
    {
        $sql = "
        SELECT 
        g.id,
        g.grupo,
        g.descripcion,
        CONCAT('[', GROUP_CONCAT(
            JSON_OBJECT(
                'id', p.id,
                'permiso', p.permiso,
                'alias', p.alias,
                'descripcion', p.descripcion,
                'activo', p.activo,
                'accesos', IFNULL(
                    (
                        SELECT JSON_OBJECT(
                            'acc_lectura', IFNULL(gup.acc_lectura, false),
                            'acc_crear', IFNULL(gup.acc_crear, false),
                            'acc_actualizar', IFNULL(gup.acc_actualizar, false),
                            'acc_eliminar', IFNULL(gup.acc_eliminar, false)
                        )
                        FROM grupo_usuario_permiso gup
                        WHERE gup.id_permiso = p.id
                        AND gup.id_grupo_usuario = :idGrupoUsuario:
                        LIMIT 1
                    ), 
                    JSON_OBJECT('acc_lectura', false, 'acc_crear', false, 'acc_actualizar', false, 'acc_eliminar', false)
                )
            )
        ), ']') AS permisos
    FROM 
        grupo_permiso g
    JOIN 
        permiso p ON p.id_grupo_permiso = g.id
    GROUP BY 
        g.id, g.grupo, g.descripcion;";
        $this->db->query("SET SESSION group_concat_max_len = 1000000");

        $query = $this->db->query($sql, ['idGrupoUsuario' => $idGrupoUsuario]);
        return $query->getResult();
    }

    public function actualizarGruposUsuariosPermisos(array $datos): array
    {
        $column = $datos['column'];

        // Validación opcional de columna
        $permisosValidos = ['acc_lectura', 'acc_crear', 'acc_actualizar', 'acc_eliminar'];
        if (!in_array($column, $permisosValidos)) {
            throw new \Exception("Columna de permiso inválida: {$column}");
        }

        $sql = "
            INSERT INTO grupo_usuario_permiso 
                (id_grupo_usuario, id_permiso, {$column}, created_at)
            VALUES
                (:grupo_usuario_id:, :permiso_id:, :value:, NOW())
            ON DUPLICATE KEY UPDATE
                {$column} = VALUES({$column}),
                updated_at = NOW()
        ";

        $result = $this->db->query($sql, [
            'grupo_usuario_id' => $datos['grupo_usuario_id'],
            'permiso_id'       => $datos['permiso_id'],
            'value'            => $datos['value'],
        ]);

        if (!$result) {
            throw new \Exception('No se pudo insertar o actualizar el permiso del grupo de usuario.');
        }

        return [
            'grupo_usuario_id' => $datos['grupo_usuario_id'],
            'permiso_id'       => $datos['permiso_id'],
            $column             => $datos['value'],
        ];
    }
}
