<?php
namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;
use Belly\Conversacion\Models\MotivoConversacionModel;
use Belly\Conversacion\Entities\MotivoConversacionEntity;

class ConversacionMotivoUseCase {
    protected ConversacionConfig $config;
    protected ?MotivoConversacionModel $motivo_conversacion_provider = NULL;

    public function __construct(ConversacionConfig $config)
    {
        $this->config = $config;
    }

    public function getMotivoConversacionProvider(): MotivoConversacionModel
    {
        if ($this->motivo_conversacion_provider !== null) {
            return $this->motivo_conversacion_provider;
        }

        $class_name = $this->config->motivo_conversacion_provider;
        $this->motivo_conversacion_provider = new $class_name();

        return $this->motivo_conversacion_provider;
    }

    public function crearMotivoConversacion(array $datos)
    {
        $motivo_model = $this->getMotivoConversacionProvider();

        $motivo_entity = new MotivoConversacionEntity();
        $motivo_entity->nombre = $datos['nombre'] ?? null;

        if ($motivo_model->save($motivo_entity)) {
            $motivo_entity->id = $motivo_model->insertID;
            
            // Obtener el motivo completo de la base de datos
            $motivo_completo = $motivo_model->find($motivo_entity->id);

            return $motivo_completo;
        } else {
            throw new \Exception(json_encode($motivo_model->errors()));
        }
    }

    public function editarMotivoConversacion(int $id, array $datos)
    {
        $motivo_model = $this->getMotivoConversacionProvider();
        $motivo_entity = $motivo_model->find($id);
        
        if (!$motivo_entity) {
            throw new \Exception('Motivo de conversación no encontrado');
        }

        $motivo_entity->id = $id;
        if (isset($datos['nombre'])) {
            $motivo_entity->nombre = $datos['nombre'];
        }

        if ($motivo_model->save($motivo_entity)) {
            // Obtener el motivo actualizado
            return $motivo_model->find($id);
        } else {
            throw new \Exception(json_encode($motivo_model->errors()));
        }
    }

    public function eliminarMotivoConversacion(int $id): array
    {
        $motivo_model = $this->getMotivoConversacionProvider();
        $motivo_entity = $motivo_model->find($id);
        
        if (!$motivo_entity) {
            throw new \Exception('Motivo de conversación no encontrado');
        }

        // Soft delete usando delete()
        if ($motivo_model->delete($id)) {
            return [
                'message' => 'Motivo de conversación eliminado correctamente',
                'id' => $id
            ];
        } else {
            throw new \Exception('Error al eliminar el motivo de conversación');
        }
    }

    public function listarMotivoConversacion(): array
    {
        $motivo_model = $this->getMotivoConversacionProvider();

        return $motivo_model
            ->where('deleted_at', null)
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    public function obtenerMotivoConversacion(int $id)
    {
        $motivo_model = $this->getMotivoConversacionProvider();
        $motivo = $motivo_model->find($id);
        
        if (!$motivo) {
            throw new \Exception('Motivo de conversación no encontrado');
        }

        return $motivo;
    }
}
