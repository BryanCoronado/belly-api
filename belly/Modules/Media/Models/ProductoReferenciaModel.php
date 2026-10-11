<?php

namespace Belly\Media\Models;
use Belly\Media\Entities\ProductoReferenciaEntity;

use CodeIgniter\Model;

class ProductoReferenciaModel extends Model {

    protected $table = 'producto_referencia';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ProductoReferenciaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_producto',
        'titulo',
        'alt',
        'orden',
        'activo',
        'filename',
        'path',
        'url',
        'original_name',
        'mime_type',
        'size',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_producto' => 'int',
        'orden' => 'int',
        'size' => 'int',
        'activo' => 'int',
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
        'id_producto' => 'permit_empty|integer',
        'titulo' => 'permit_empty|max_length[255]',
        'alt' => 'permit_empty|max_length[255]',
        'orden' => 'permit_empty|integer',
        'activo' => 'required|integer|in_list[0,1]',
        'filename' => 'required|max_length[255]',
        'path' => 'required|max_length[500]',
        'url' => 'required|max_length[1000]',
        'mime_type' => 'required|max_length[100]',
        'size' => 'required|integer|greater_than[0]',
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