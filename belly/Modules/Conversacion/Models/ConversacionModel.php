<?php

namespace Belly\Conversacion\Models;
use Belly\Conversacion\Entities\ConversacionEntity;
use Belly\Configuracion\Models\FiltraPorEmpresa;

use CodeIgniter\Model;

class ConversacionModel extends Model {
    use FiltraPorEmpresa;

    protected $table = 'conversacion';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ConversacionEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa',
        'id_cliente',
        'id_asignado_por',
        'id_usuario',
        'id_app',
        'id_estado',
        'id_motivo',
        'id_etiqueta',
        'origen',
        'temperatura',
        'registro',
        'servicio',
        'asignado_at',
        'source_id',
        'campaign_id',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
        'id_cliente' => 'int',
        'id_asignado_por' => '?int',
        'id_usuario' => 'int',
        'id_app' => 'int',
        'id_motivo' => '?int',
        'id_etiqueta' => '?int',
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
