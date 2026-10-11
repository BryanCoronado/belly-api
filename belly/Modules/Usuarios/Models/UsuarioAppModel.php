<?php

namespace Belly\Usuarios\Models;
use Belly\ModelPivot\ModelPivot;
use Belly\Usuarios\Entities\UsuarioAppEntity;

class UsuarioAppModel extends ModelPivot
{
    protected $table = 'usuario_app';
    protected $primaryKey = 'id_usuario,id_app';
    protected $useAutoIncrement = false;
    protected $returnType = UsuarioAppEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_usuario',
        'id_app',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id_usuario'        => 'int',
        'id_app' => 'int',
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
        'id_usuario'     => 'required',
        'id_app'     => 'required',
    ];
    protected $validationMessages = [
        'id_usuario' => [
            'required' => 'El usuario es requerido.',
        ],
        'id_app' => [
            'required' => 'El app es requerido.'
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
