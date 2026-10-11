<?php

namespace Belly\Conversacion\Models;
use Belly\Conversacion\Entities\ComentarioConversacionEntity;

use CodeIgniter\Model;

class ComentarioConversacionModel extends Model {

    protected $table = 'comentario_conversacion';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ComentarioConversacionEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_conversacion',
        'id_usuario',
        'comentario',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_conversacion' => 'int',
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
    protected $validationRules = [
        'id_conversacion' => 'required|integer|is_natural',
        'id_usuario' => 'required|integer|is_natural',
        'comentario' => 'required|min_length[1]',
    ];
    protected $validationMessages = [
        'id_conversacion' => [
            'required' => 'El ID de conversación es obligatorio.',
            'integer' => 'El ID de conversación debe ser un número entero.',
            'is_natural' => 'El ID de conversación debe ser un número positivo.',
        ],
        'id_usuario' => [
            'required' => 'El ID de usuario es obligatorio.',
            'integer' => 'El ID de usuario debe ser un número entero.',
            'is_natural' => 'El ID de usuario debe ser un número positivo.',
        ],
        'comentario' => [
            'required' => 'El comentario es obligatorio.',
            'min_length' => 'El comentario debe tener al menos 1 carácter.',
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

