<?php

namespace Belly\Clientes\Models;
use Belly\Clientes\Entities\ListaNegraEntity;

use CodeIgniter\Model;

class ListaNegraModel extends Model {

    protected $table = 'lista_negra';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ListaNegraEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'telefono',
        'motivo'
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
    ];
    protected array $castHandlers = [];
    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'telefono'     => 'required|max_length[11]|min_length[11]|is_unique[lista_negra.telefono,id,{id}]',
        'motivo'     => 'permit_empty|max_length[255]',
        'id'           => 'permit_empty|integer',
    ];
    protected $validationMessages = [
        'telefono'        => [
            'required'      => 'El campo teléfono es obligatorio.',
            'max_length'    => 'El campo teléfono debe tener exactamente 11 caracteres (51 + 9 dígitos).',
            'min_length'    => 'El campo teléfono debe tener exactamente 11 caracteres (51 + 9 dígitos).',
            'is_unique'     => 'El número de teléfono ya está registrado.',
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
