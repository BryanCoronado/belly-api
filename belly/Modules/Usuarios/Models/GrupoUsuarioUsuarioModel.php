<?php

namespace Belly\Usuarios\Models;

use CodeIgniter\Model;
use Belly\Usuarios\Entities\GrupoUsuarioUsuarioEntity;

class GrupoUsuarioUsuarioModel extends Model
{
    protected $table = 'grupo_usuario_usuario';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = GrupoUsuarioUsuarioEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_grupo_usuario',
        'id_usuario',
        'id_grupo_usuario_rol'
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_grupo_usuario'  => 'int',
        'id_usuario'        => 'int',
        'id_grupo_usuario_rol' => 'int',
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
        'id_usuario'  => 'required|numeric',
        'id_grupo_usuario_rol'  => 'required|numeric',
    ];
    protected $validationMessages = [
        'id_grupo_usuario' => [
            'required' => 'El grupo es requerido.',
            'numeric' => 'Se admiten solo enteros.',
        ],
        'id_usuario' => [
            'required' => 'El usuario es requerido.',
            'numeric' => 'Se admiten solo enteros.',
        ],
        'id_grupo_usuario_rol' => [
            'required' => 'El grupo de rol es requerido.',
            'numeric' => 'Se admiten solo enteros.',
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
