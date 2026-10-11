<?php

namespace Belly\Conversacion\Models;
use Belly\Conversacion\Entities\CitaConversacionEntity;

use CodeIgniter\Model;

class CitaConversacionModel extends Model {

    protected $table = 'cita_conversacion';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = CitaConversacionEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_conversacion',
        'id_cita_erp',
        'cita_estado',
        'fecha_agendacion',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_conversacion' => 'int',
        'id_cita_erp' => '?int',
        'cita_estado' => '?int',
    ];
    protected array $castHandlers = [];
    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    // Validation
    protected $validationRules = [];
    protected $validationMessages = [];
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
