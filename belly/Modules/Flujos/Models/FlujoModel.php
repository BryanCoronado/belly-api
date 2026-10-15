<?php

namespace Belly\Flujos\Models;

use Belly\Configuracion\Models\FiltraPorEmpresa;
use Belly\Flujos\Entities\FlujoEntity;
use CodeIgniter\Model;

class FlujoModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'flujo';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = FlujoEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = ['id_empresa', 'nombre', 'descripcion', 'activo', 'definicion'];
    protected bool $allowEmptyInserts = false;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'id_empresa' => 'required|is_natural_no_zero',
        'nombre' => 'required|min_length[2]|max_length[120]',
        'descripcion' => 'permit_empty|max_length[255]',
    ];
    protected $validationMessages = [
        'nombre' => [
            'required' => 'Ponle un nombre al flujo.',
            'min_length' => 'El nombre es muy corto.',
            'max_length' => 'El nombre no puede pasar de 120 letras.',
        ],
    ];
}
