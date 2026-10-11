<?php
namespace Belly\Clientes\UseCases;

use Belly\Clientes\Config\ClientesConfig;
use Belly\Clientes\Models\ClienteModel;
use Belly\Clientes\Entities\ClienteEntity;
use Belly\Clientes\Models\ClientePerfilModel;
use Belly\Conversacion\Models\ConversacionModel;
use Belly\Mensajes\Models\MensajeModel;
use Belly\Clientes\Entities\ClientePerfilEntity;

class ClienteMetricaUseCase {
    protected ClientesConfig $config;
    protected ?ClienteModel $cliente_provider = NULL;
    protected ?ClientePerfilModel $cliente_perfil_provider = NULL;
    protected ?ConversacionModel $conversacion_provider = NULL;
    protected ?MensajeModel $mensaje_provider = NULL;


    const ROL_JEFE_CC              = 8;
    const ROL_JEFE_CS              = 9;
    const ROL_JEFE_CC_POSTVENTA    = 12;

    public function __construct(ClientesConfig $config)
    {
        $this->config = $config;
    }

    public function getClienteProvider(): ClienteModel
    {
        if ($this->cliente_provider !== null) {
            return $this->cliente_provider;
        }

        $class_name = $this->config->cliente_provider;
        $this->cliente_provider = new $class_name();

        return $this->cliente_provider;
    }

    public function getClientePerfilProvider(): ClientePerfilModel
    {
        if ($this->cliente_perfil_provider !== null) {
            return $this->cliente_perfil_provider;
        }

        $class_name = $this->config->cliente_perfil_provider;
        $this->cliente_perfil_provider = new $class_name();

        return $this->cliente_perfil_provider;
    }


    public function getConversacionProvider(): ConversacionModel
    {
        if ($this->conversacion_provider !== null) {
            return $this->conversacion_provider;
        }

        $class_name = $this->config->conversacion_provider;
        $this->conversacion_provider = new $class_name();

        return $this->conversacion_provider;
    }

    public function getMensajeProvider(): MensajeModel
    {
        if ($this->mensaje_provider !== null) {
            return $this->mensaje_provider;
        }

        $class_name = $this->config->mensaje_provider;
        $this->mensaje_provider = new $class_name();

        return $this->mensaje_provider;
    }



    public function obtenerMetricasAtencion($fecha_inicio = null, $fecha_fin = null): array
    {
        $fecha_inicio = $fecha_inicio ?? date('Y-m-d 00:00:00');
        $fecha_fin = $fecha_fin ?? date('Y-m-d 23:59:59');
        $hoy = date('Y-m-d');

        // Crear nuevas instancias para evitar conflictos de estado en el query builder
        $conversacion_class = $this->config->conversacion_provider;
        $mensaje_class = $this->config->mensaje_provider;

        // Total de conversaciones (todas)
        $total_conversaciones = (new $conversacion_class())
            ->countAllResults(false);

        // Conversaciones activas (estado 1)
        $conversaciones_activas = (new $conversacion_class())
            ->where('id_estado', 1)
            ->countAllResults(false);
        
        // Conversaciones finalizadas (estado 2)
        $conversaciones_finalizadas = (new $conversacion_class())
            ->where('id_estado', 2)
            ->countAllResults(false);

        // Conversaciones finalizadas hoy
        $conversaciones_finalizadas_hoy = (new $conversacion_class())
            ->where('id_estado', 2)
            ->where('DATE(updated_at)', $hoy)
            ->countAllResults(false);
        
        // Mensajes sin leer
        $mensajes_sin_leer = (new $mensaje_class())
            ->where('contesta', 'cliente')
            ->whereIn('id_estado', [1, 3])
            ->countAllResults(false);
        
        // Total conversaciones en período
        $total_conversaciones_periodo = (new $conversacion_class())
            ->where('created_at >=', $fecha_inicio)
            ->where('created_at <=', $fecha_fin)
            ->countAllResults(false);
        
        // Total mensajes en período
        $total_mensajes_periodo = (new $mensaje_class())
            ->where('created_at >=', $fecha_inicio)
            ->where('created_at <=', $fecha_fin)
            ->countAllResults(false);

        // Conversaciones por usuario/asesor
        $conversaciones_raw = (new $conversacion_class())
            ->select('
                conversacion.id_usuario,
                usuario.usuario,
                usuario_perfil.nombres,
                usuario_perfil.apellidos,
                COUNT(CASE WHEN conversacion.id_estado = 1 THEN 1 END) as activas,
                COUNT(CASE WHEN conversacion.id_estado = 2 THEN 1 END) as finalizadas,
                COUNT(conversacion.id) as total
            ')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'left')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->groupBy('conversacion.id_usuario')
            ->orderBy('total', 'DESC')
            ->findAll();

        // Formatear y convertir valores numéricos
        $conversaciones_por_usuario = [];
        foreach ($conversaciones_raw as $item) {
            $conversaciones_por_usuario[] = [
                'id_usuario' => (int) $item->id_usuario,
                'usuario' => $item->usuario ?? '',
                'nombres' => $item->nombres ?? '',
                'apellidos' => $item->apellidos ?? '',
                'nombre_completo' => trim(($item->nombres ?? '') . ' ' . ($item->apellidos ?? '')),
                'activas' => (int) $item->activas,
                'finalizadas' => (int) $item->finalizadas,
                'total' => (int) $item->total,
            ];
        }

        return [
            'resumen' => [
                'total_conversaciones' => (int) $total_conversaciones,
                'conversaciones_activas' => (int) $conversaciones_activas,
                'conversaciones_finalizadas' => (int) $conversaciones_finalizadas,
                'conversaciones_finalizadas_hoy' => (int) $conversaciones_finalizadas_hoy,
                'mensajes_sin_leer' => (int) $mensajes_sin_leer,
            ],
            'periodo' => [
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'total_conversaciones' => (int) $total_conversaciones_periodo,
                'total_mensajes' => (int) $total_mensajes_periodo,
            ],
            'por_usuario' => $conversaciones_por_usuario,
        ];
    }















    
}
