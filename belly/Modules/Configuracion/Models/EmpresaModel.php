<?php

namespace Belly\Configuracion\Models;

use Belly\Configuracion\Entities\EmpresaEntity;
use CodeIgniter\Model;

class EmpresaModel extends Model
{
    protected $table = 'empresa';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = EmpresaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'nombre',
        'correo',
        'id_aviso_ia',
        'slug',
        'plan',
        'id_plan',
        'estado',
        'modo_ahorro',
        'app_asesor_activa',
        'tipo_doc',
        'numero_doc',
        'razon_social',
        'estado_sunat',
        'condicion_sunat',
        'direccion_fiscal',
        'ubigeo',
        'representante_nombre',
        'representante_cargo',
        'representante_documento',
        'identidad_json',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'estado' => 'int',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
