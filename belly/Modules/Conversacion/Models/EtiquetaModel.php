<?php

namespace Belly\Conversacion\Models;

use CodeIgniter\Model;
use Belly\Conversacion\Entities\EtiquetaEntity;

class EtiquetaModel extends Model
{
    protected $table = 'etiqueta';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = EtiquetaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'nombre',
        'descripcion',
        'color',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [];
    protected $validationMessages = [];
    protected $skipValidation = false;
}
