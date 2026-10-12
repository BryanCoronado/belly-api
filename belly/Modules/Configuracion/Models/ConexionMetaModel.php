<?php

namespace Belly\Configuracion\Models;

use Belly\Configuracion\Entities\ConexionMetaEntity;
use CodeIgniter\Model;

class ConexionMetaModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'conexion_meta';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ConexionMetaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa',
        'meta_app_id',
        'app_secret',
        'webhook_clave',
        'verify_token',
        'webhook_verificado_at',
        'ultimo_evento_at',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'id_empresa' => 'required|is_natural_no_zero',
        'meta_app_id' => 'permit_empty|numeric|max_length[50]',
        'webhook_clave' => 'required|alpha_numeric|max_length[64]',
        'verify_token' => 'required|alpha_numeric|max_length[64]',
    ];
    protected $validationMessages = [
        'meta_app_id' => [
            'numeric' => 'El identificador de la app solo lleva números.',
        ],
    ];
}
