<?php
namespace Belly\Permisos\Models;
use Belly\ModelPivot\ModelPivot;
use Belly\Permisos\Entities\GrupoUsuarioPermisoEntity;


class GrupoUsuarioPermisoModel extends ModelPivot
{
    protected $table = 'grupo_usuario_permiso';
    protected $primaryKey = 'id_grupo_permiso,id_permiso';
    protected $useAutoIncrement = false;
    protected $returnType = GrupoUsuarioPermisoEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_grupo_usuario',
        'id_permiso',
        'acc_crear',
        'acc_lectura',
        'acc_actualizar',
        'acc_eliminar',
        'activo'
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id_grupo_usuario' => 'int',
        'id_permiso' => 'int',
        'activo' => 'int',
        'created_at' => 'datetime',
        'updated_at' => '?datetime',
        'deleted_at' => '?datetime',
    ];
    protected array $castHandlers = [];
    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    // Validation
    protected $validationRules = [
        'id_grupo_usuario'     => 'required|integer',
        'id_permiso'     => 'required|integer'
    ];
    protected $validationMessages = [
        'id_grupo_usuario' => [
            'required' => 'El ID del grupo de usuario es requerido.',
            'integer' => 'El ID del grupo de usuario debe ser un número entero.',
        ],
        'id_permiso' => [
            'required' => 'El ID del permiso es requerido.',
            'integer' => 'El ID del permiso debe ser un número entero.',
        ]
    ];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;
    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];
}
