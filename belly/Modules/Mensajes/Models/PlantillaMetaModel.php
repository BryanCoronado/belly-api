<?php

namespace Belly\Mensajes\Models;
use Belly\Mensajes\Entities\PlantillaMetaEntity;
use Belly\Configuracion\Models\FiltraPorEmpresa;

use CodeIgniter\Model;

class PlantillaMetaModel extends Model {
    use FiltraPorEmpresa;

    protected $table = 'plantilla_meta';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = PlantillaMetaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa',
        'template_id',
        'id_app',
        'id_estado',
        'nombre',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
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

