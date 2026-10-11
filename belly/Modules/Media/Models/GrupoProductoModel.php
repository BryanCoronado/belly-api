<?php

namespace Belly\Media\Models;
use Belly\Media\Entities\GrupoProductoEntity;

use CodeIgniter\Model;

class GrupoProductoModel extends Model {

    protected $table = 'grupo_producto';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = GrupoProductoEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'nombre',
        'descripcion',
        'slug',
        'descripcion_web',
        'mostrar_web',
        'seo_title',
        'seo_description',
        'mensaje_whatsapp',
        'sinonimos',
        'subcategorias',
        'prioridad',
        'activo',
        'color',
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
        'prioridad' => '?int',
        'activo' => 'int',
        'mostrar_web' => 'int',
        'size' => '?int',
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
        'nombre' => 'required|max_length[255]',
        'descripcion' => 'required|max_length[50]',
        'slug' => 'permit_empty|max_length[160]',
        'descripcion_web' => 'permit_empty',
        'mostrar_web' => 'permit_empty|integer|in_list[0,1]',
        'seo_title' => 'permit_empty|max_length[160]',
        'seo_description' => 'permit_empty|max_length[320]',
        'mensaje_whatsapp' => 'permit_empty',
        'prioridad' => 'permit_empty|integer',
        'activo' => 'required|integer|in_list[0,1]',
        'color' => 'required|max_length[50]',
    ];
    protected $validationMessages = [
        'nombre' => [
            'required' => 'El nombre es requerido.',
            'max_length' => 'Se permiten maximo 255 carácteres.',
        ],
        'descripcion' => [
            'required' => 'La descripción es requerida.',
            'max_length' => 'Se permiten maximo 50 carácteres.',
        ],
        'activo' => [
            'required' => 'El activo es requerido.',
            'integer' => 'El activo debe ser un número entero.',
            'in_list' => 'El activo debe ser 0 o 1.',
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

