<?php

namespace Belly\Usuarios\Models;

use Belly\ModelPivot\ModelPivot;
use Belly\Usuarios\Entities\GrupoUsuarioPermisoRolEntity;

class GrupoUsuarioPermisoRolModel extends ModelPivot
{
    protected $table = 'grupo_usuario_permiso_rol';
    protected $primaryKey = 'id_grupo_usuario,id_permiso,id_grupo_usuario_rol';
    protected $returnType = GrupoUsuarioPermisoRolEntity::class;
    protected $useAutoIncrement = false;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
        protected $allowedFields = [            
            'id_grupo_usuario',
            'id_permiso',
            'id_grupo_usuario_rol',
            'acc_crear',
            'acc_lectura',
            'acc_actualizar',
            'acc_eliminar',
            'activo'
        ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
        protected array $casts = [
            'id_grupo_usuario'  => 'int',
            'id_permiso'        => 'int',
            'id_grupo_usuario_rol' => 'int',
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
        'id_grupo_usuario'       => 'required|numeric',
        'id_permiso'     => 'required',
        'id_grupo_usuario_rol'     => 'required',
    ];
    protected $validationMessages = [
        'id_grupo_usuario' => [
            'required' => 'El grupo es requerido.',
            'numeric' => 'Se admiten solo enteros.',
        ],
        'id_usuario' => [
            'required' => 'El usuario es requerido.',
        ],
        'id_permiso' => [
            'required' => 'El permiso es requerido.'
        ],
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
