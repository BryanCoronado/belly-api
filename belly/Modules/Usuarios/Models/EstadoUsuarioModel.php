<?php

namespace Belly\Usuarios\Models;
use Belly\Usuarios\Entities\EstadoUsuarioEntity;

use CodeIgniter\Model;

class EstadoUsuarioModel extends Model
{

    protected $table = 'estado_usuario';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = EstadoUsuarioEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'nombre',
        'descripcion',
        'color',
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
        'nombre' => 'required|max_length[50]',
        'descripcion' => 'permit_empty|max_length[100]',
    ];
    protected $validationMessages = [
        'nombre' => [
            'required' => 'El nombre es requerido.',
            'max_length' => 'Se permiten maximo 50 carácteres.',
        ],
        'descripcion' => [
            'max_length' => 'Se permiten maximo 100 carácteres.',
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
