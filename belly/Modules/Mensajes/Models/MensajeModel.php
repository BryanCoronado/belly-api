<?php

namespace Belly\Mensajes\Models;
use Belly\Mensajes\Entities\MensajeEntity;

use CodeIgniter\Model;

class MensajeModel extends Model {

    protected $table = 'mensaje';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = MensajeEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_conversacion',
        'id_estado',
        'mensaje',
        'contesta',
        'id_mensaje_whatsapp',
        'id_mensaje_responde',
        'cod_error',
        'media_type',
        'media_url',
        'media_path',
        'payload'
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_conversacion' => 'int',
        'id_estado' => 'int',
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
