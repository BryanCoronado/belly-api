<?php

namespace Belly\Media\Models;
use Belly\Media\Entities\GrupoGaleriaEntity;

use CodeIgniter\Model;

class GrupoGaleriaModel extends Model {

    protected $table = 'grupo_galeria';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = GrupoGaleriaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'nombre',
        'descripcion',
        'activo',

    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
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
        'nombre' => 'required|max_length[255]',
        'descripcion' => 'required|max_length[50]',
        'activo' => 'required|integer|in_list[0,1]',
    ];
    protected $validationMessages = [
        'nombre' => [
            'required' => 'El nombre es requerido.',
            'max_length' => 'Se permiten maximo 255 carácteres.',
        ],
        'descripcion' => [
            'required' => 'La descripción es requerida.',
            'max_length' => 'Se permiten maximo 50 carácteres.',
        ],
        'activo' => [
            'required' => 'El activo es requerido.',
            'integer' => 'El activo debe ser un número entero.',
            'in_list' => 'El activo debe ser 0 o 1.',
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

