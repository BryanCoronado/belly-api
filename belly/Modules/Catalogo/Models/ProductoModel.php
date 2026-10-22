<?php

namespace Belly\Catalogo\Models;

use Belly\Catalogo\Entities\ProductoEntity;
use Belly\Configuracion\Models\FiltraPorEmpresa;
use CodeIgniter\Model;

class ProductoModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'producto';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ProductoEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa', 'id_familia', 'nombre', 'descripcion', 'codigo',
        'precio', 'moneda', 'unidad', 'imagen_url', 'detalles', 'activo',
    ];
    protected bool $allowEmptyInserts = false;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'id_empresa' => 'required|is_natural_no_zero',
        'nombre' => 'required|min_length[2]|max_length[150]',
        'codigo' => 'permit_empty|max_length[60]',
        'precio' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than[10000000000]',
        'moneda' => 'required|regex_match[/^[A-Z]{3}$/]',
        'unidad' => 'permit_empty|max_length[30]',
        'imagen_url' => 'permit_empty|max_length[500]',
    ];
    protected $validationMessages = [
        'nombre' => [
            'required' => 'Ponle un nombre al producto.',
            'min_length' => 'El nombre es muy corto.',
            'max_length' => 'El nombre no puede pasar de 150 letras.',
        ],
        'codigo' => [
            'max_length' => 'El código no puede pasar de 60 letras.',
        ],
        'precio' => [
            'decimal' => 'El precio debe ser un número.',
            'greater_than_equal_to' => 'El precio no puede ser negativo.',
            'less_than' => 'El precio es demasiado grande.',
        ],
        'moneda' => [
            'required' => 'Elige la moneda.',
            'regex_match' => 'La moneda no es válida.',
        ],
        'unidad' => [
            'max_length' => 'La unidad no puede pasar de 30 letras.',
        ],
    ];
}
