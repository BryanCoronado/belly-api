<?php

namespace Belly\Usuarios\Models;

use CodeIgniter\Model;
use Belly\Usuarios\Entities\GrupoUsuarioRolEntity;

class GrupoUsuarioRolModel extends Model
{
    protected $table = 'grupo_usuario_rol';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = GrupoUsuarioRolEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'rol',
        'alias',
        'descripcion'
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
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
        'rol'       => 'required|max_length[45]',
        'alias'     => 'required|max_length[45]',
        'descripcion'  => 'permit_empty|max_length[150]',
    ];
    protected $validationMessages = [
        'rol' => [
            'required' => 'El rol es requerido.',
            'max_length' => 'Se permiten maximo 45 carácteres.',
        ],
        'alias' => [
            'required' => 'El alias es requerido.',
            'max_length' => 'Se permiten maximo 45 carácteres.',
        ],
        'descripcion' => [
            'max_length' => 'Se permiten maximo 150 carácteres.',
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