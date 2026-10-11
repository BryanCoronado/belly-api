<?php

namespace Belly\Mensajes\Models;
use Belly\Mensajes\Entities\PlantillaRapidaEntity;

use CodeIgniter\Model;

class PlantillaRapidaModel extends Model {

    protected $table = 'plantilla_rapida';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = PlantillaRapidaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'contenido',
        'id_usuario',
        'nombre',
        'imagen_path',
        'imagen_url',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_usuario' => 'int',
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

