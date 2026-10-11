<?php

namespace Belly\Media\Models;
use Belly\Media\Entities\ProductoEntity;

use CodeIgniter\Model;

class ProductoModel extends Model {

    protected $table = 'producto';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ProductoEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_grupo_producto',
        'nombre',
        'descripcion',
        'slug',
        'descripcion_web',
        'mostrar_web',
        'seo_title',
        'seo_description',
        'tipo_calculo',
        'prioridad',
        'sinonimos',
        'activo',
        'especificaciones_tecnicas',
        'especificaciones_web',
        'caracteristicas_web',
        'subcategoria',
        'subcategoria_orden',
        'oferta',
        'oferta_texto',
        'mensaje_whatsapp',
        'colores',
        'atributos',
        'relacionados',
        'color',
        'filename',
        'path',
        'url',
        'original_name',
        'mime_type',
        'size',
        'ficha_filename',
        'ficha_path',
        'ficha_url',
        'ficha_mime_type',
        'ficha_size',
        'hero_path',
        'hero_url',
        'hero_path_mobile',
        'hero_url_mobile',
        'diagrama_path',
        'diagrama_url',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_grupo_producto' => 'int',
        'size' => 'int',
        'activo' => 'int',
        'mostrar_web' => 'int',
        'oferta' => 'int',
        'subcategoria_orden' => '?int',
        'ficha_size' => '?int',
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
        'id_grupo_producto' => 'permit_empty|integer',
        'nombre' => 'required|max_length[255]',
        'descripcion' => 'required|max_length[500]',
        'slug' => 'permit_empty|max_length[160]',
        'descripcion_web' => 'permit_empty',
        'mostrar_web' => 'permit_empty|integer|in_list[0,1]',
        'seo_title' => 'permit_empty|max_length[160]',
        'seo_description' => 'permit_empty|max_length[320]',
        'tipo_calculo' => 'required|in_list[metro,fijo,unidad]',
        'activo' => 'required|integer|in_list[0,1]',
        'especificaciones_tecnicas' => 'required|max_length[500]',
        'especificaciones_web' => 'permit_empty',
        'caracteristicas_web' => 'permit_empty',
        'subcategoria' => 'permit_empty|max_length[120]',
        'subcategoria_orden' => 'permit_empty|integer',
        'oferta' => 'permit_empty|integer|in_list[0,1]',
        'oferta_texto' => 'permit_empty|max_length[80]',
        'mensaje_whatsapp' => 'permit_empty',
        'color' => 'permit_empty|max_length[20]',
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

