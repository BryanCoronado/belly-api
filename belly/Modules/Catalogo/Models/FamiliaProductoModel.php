<?php

namespace Belly\Catalogo\Models;

use Belly\Catalogo\Entities\FamiliaProductoEntity;
use Belly\Configuracion\Models\FiltraPorEmpresa;
use CodeIgniter\Model;

class FamiliaProductoModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'familia_producto';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = FamiliaProductoEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = ['id_empresa', 'nombre', 'descripcion', 'orden', 'activo'];
    protected bool $allowEmptyInserts = false;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'id_empresa' => 'required|is_natural_no_zero',
        'nombre' => 'required|min_length[2]|max_length[80]',
        'descripcion' => 'permit_empty|max_length[255]',
    ];
    protected $validationMessages = [
        'nombre' => [
            'required' => 'Ponle un nombre a la familia.',
            'min_length' => 'El nombre es muy corto.',
            'max_length' => 'El nombre no puede pasar de 80 letras.',
        ],
        'descripcion' => [
            'max_length' => 'La descripción no puede pasar de 255 letras.',
        ],
    ];
}
