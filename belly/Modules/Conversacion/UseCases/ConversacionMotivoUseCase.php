<?php
namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;
use Belly\Conversacion\Models\MotivoConversacionModel;

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
        $lista = crm_ajustes()->guardarMotivo(null, $datos);

        return $lista[array_key_last($lista)] ?? $lista;
    }

    public function editarMotivoConversacion(int $id, array $datos)
    {
        $lista = crm_ajustes()->guardarMotivo($id, $datos);
        foreach ($lista as $motivo) {
            if ((int) $motivo['id'] === $id) {
                return $motivo;
            }
        }

        return $lista;
    }

    public function eliminarMotivoConversacion(int $id): array
    {
        crm_ajustes()->eliminarMotivo($id);

        return [
            'message' => 'Motivo de conversación eliminado correctamente',
            'id' => $id,
        ];
    }

    public function listarMotivoConversacion(): array
    {
        return crm_ajustes()->motivos();
    }

    public function obtenerMotivoConversacion(int $id)
    {
        foreach (crm_ajustes()->motivos() as $motivo) {
            if ((int) $motivo['id'] === $id) {
                return $motivo;
            }
        }

        throw new \Exception('Motivo de conversación no encontrado');
    }
}
