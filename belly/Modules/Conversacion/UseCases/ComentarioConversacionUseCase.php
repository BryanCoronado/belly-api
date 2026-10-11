<?php
namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;
use Belly\Conversacion\Models\ComentarioConversacionModel;
use Belly\Conversacion\Entities\ComentarioConversacionEntity;

class ComentarioConversacionUseCase 
{
    protected ConversacionConfig $config;
    protected ?ComentarioConversacionModel $comentario_conversacion_provider = null;

    public function __construct(ConversacionConfig $config)
    {
        $this->config = $config;
    }

    public function getComentarioConversacionProvider(): ComentarioConversacionModel
    {
        if ($this->comentario_conversacion_provider !== null) {
            return $this->comentario_conversacion_provider;
        }

        $class_name = $this->config->comentario_conversacion_provider;
        $this->comentario_conversacion_provider = new $class_name();

        return $this->comentario_conversacion_provider;
    }

    public function crearComentarioConversacion(array $datos)
    {
        $comentario_model = $this->getComentarioConversacionProvider();

        $comentario_entity = new ComentarioConversacionEntity();
        $comentario_entity->id_conversacion = $datos['id_conversacion'] ?? null;
        $comentario_entity->id_usuario = $datos['id_usuario'] ?? null;
        $comentario_entity->comentario = $datos['comentario'] ?? null;

        if ($comentario_model->save($comentario_entity)) {
            $comentario_entity->id = $comentario_model->insertID;
            
            // Obtener el comentario completo de la base de datos
            $comentario_completo = $comentario_model->find($comentario_entity->id);

            return $comentario_completo;
        } else {
            throw new \Exception(json_encode($comentario_model->errors()));
        }
    }

    public function editarComentarioConversacion(int $id, array $datos)
    {
        $comentario_model = $this->getComentarioConversacionProvider();
        $comentario_entity = $comentario_model->find($id);
        
        if (!$comentario_entity) {
            throw new \Exception('Comentario de conversación no encontrado');
        }

        $comentario_entity->id = $id;
        if (isset($datos['comentario'])) {
            $comentario_entity->comentario = $datos['comentario'];
        }

        if ($comentario_model->save($comentario_entity)) {
            return $comentario_model->find($id);
        } else {
            throw new \Exception(json_encode($comentario_model->errors()));
        }
    }

    public function eliminarComentarioConversacion(int $id): array
    {
        $comentario_model = $this->getComentarioConversacionProvider();
        $comentario_entity = $comentario_model->find($id);
        
        if (!$comentario_entity) {
            throw new \Exception('Comentario de conversación no encontrado');
        }

        if ($comentario_model->delete($id)) {
            return [
                'message' => 'Comentario de conversación eliminado correctamente',
                'id' => $id
            ];
        } else {
            throw new \Exception('Error al eliminar el comentario de conversación');
        }
    }

    public function listarComentarioConversacion(int $id_conversacion): array
    {
        $comentarios = $this->getComentarioConversacionProvider()
            ->where('id_conversacion', $id_conversacion)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return array_map(function($comentario) {
            $usuario_data = usuario()->getById($comentario->id_usuario);
            $usuario_info = is_array($usuario_data) && isset($usuario_data['errors']) 
                ? ['id' => $comentario->id_usuario, 'usuario' => null, 'nombres' => null, 'apellidos' => null]
                : ['id' => $usuario_data->id_usuario ?? $comentario->id_usuario, 'usuario' => $usuario_data->usuario ?? null, 'nombres' => $usuario_data->nombres ?? null, 'apellidos' => $usuario_data->apellidos ?? null];
            
            return [
                'id' => $comentario->id,
                'id_conversacion' => $comentario->id_conversacion,
                'id_usuario' => $comentario->id_usuario,
                'comentario' => $comentario->comentario,
                'created_at' => $comentario->created_at,
                'updated_at' => $comentario->updated_at,
                'deleted_at' => $comentario->deleted_at,
                'usuario' => $usuario_info
            ];
        }, $comentarios);
    }

    public function obtenerComentarioConversacion(int $id)
    {
        $comentario_model = $this->getComentarioConversacionProvider();
        $comentario = $comentario_model->find($id);
        
        if (!$comentario) {
            throw new \Exception('Comentario de conversación no encontrado');
        }

        return $comentario;
    }
}
