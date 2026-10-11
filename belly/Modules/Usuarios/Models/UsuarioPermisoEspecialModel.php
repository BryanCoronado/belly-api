<?php

namespace Belly\Usuarios\Models;

use Belly\ModelPivot\ModelPivot;
use Belly\Usuarios\Entities\UsuarioPermisoEspecialEntity;

class UsuarioPermisoEspecialModel extends ModelPivot
{

    protected $table = 'usuario_permiso_especial';
    protected $primaryKey = 'id_usuario,id_permiso';
    protected $useAutoIncrement = false;
    protected $returnType = UsuarioPermisoEspecialEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_usuario',
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
        'id_usuario' => 'int',
        'id_permiso' => 'int',
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
        'id_usuario'     => 'required',
        'id_permiso'     => 'required',
    ];
    protected $validationMessages = [
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
