<?php

namespace Belly\Media\Models;
use Belly\Media\Entities\ProductoPrecioEntity;

use CodeIgniter\Model;

class ProductoPrecioModel extends Model {

    protected $table = 'producto_precios';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ProductoPrecioEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_producto',
        'espesor',
        'largo',
        'desarrollo',
        'relleno',
        'tipo',
        'color',
        'moneda',
        'precio',
        'prioridad',
    ];
    protected bool $allowEmptyInserts = true;
    protected bool $updateOnlyChanged = false;
    protected array $casts = [
        'id' => 'int',
        'id_producto' => 'int',
        'created_at' => 'datetime',
        'updated_at' => '?datetime',
        'deleted_at' => '?datetime',
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
    ];
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

