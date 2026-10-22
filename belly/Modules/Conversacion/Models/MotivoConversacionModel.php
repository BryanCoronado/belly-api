<?php

namespace Belly\Conversacion\Models;
use Belly\Configuracion\Models\FiltraPorEmpresa;
use Belly\Conversacion\Entities\MotivoConversacionEntity;

use CodeIgniter\Model;

class MotivoConversacionModel extends Model {
    use FiltraPorEmpresa;

    protected $table = 'motivo_conversacion';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = MotivoConversacionEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa',
        'nombre',
        'color',
        'orden',
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
    // Validation
    protected $validationRules = [
        'nombre' => 'required|max_length[255]|min_length[1]',
    ];
    protected $validationMessages = [
        'nombre' => [
            'required' => 'El nombre del motivo es obligatorio.',
            'max_length' => 'El nombre no debe exceder los 255 caracteres.',
            'min_length' => 'El nombre debe tener al menos 1 carácter.',
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

