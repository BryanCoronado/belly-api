<?php

namespace Belly\Configuracion\Models;

use Belly\Configuracion\Entities\PlanEntity;
use CodeIgniter\Model;

class PlanModel extends Model
{
    protected $table = 'plan';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = PlanEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'clave',
        'nombre',
        'descripcion',
        'precio_mensual',
        'moneda',
        'max_conversaciones',
        'max_equipos',
        'max_personas_equipo',
        'max_personas',
        'max_flujos',
        'max_envios',
        'max_numeros',
        'incluye_agente_ia',
        'activo',
        'orden',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
