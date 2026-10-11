<?php

namespace Belly\Conversacion\Models;
use Belly\Conversacion\Entities\ConversacionCampaniaMetaEntity;

use CodeIgniter\Model;

class ConversacionCampaniaMetaModel extends Model {

    protected $table = 'conversacion_campania_meta';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ConversacionCampaniaMetaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_conversacion',
        'source_id',
        'campaign_id',
        'id_app',
        'nombre_campania',
        'codigo',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_conversacion' => 'int',
        'id_app' => 'int',
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
