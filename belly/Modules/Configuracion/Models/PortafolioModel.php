<?php

namespace Belly\Configuracion\Models;
use Belly\Configuracion\Entities\PortafolioEntity;

use CodeIgniter\Model;

class PortafolioModel extends Model {
    use FiltraPorEmpresa;

    protected $table = 'portafolio';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = PortafolioEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa',
        'nombre',
        'business_id',
        'estado',
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
