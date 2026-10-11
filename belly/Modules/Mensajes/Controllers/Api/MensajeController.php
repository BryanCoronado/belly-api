<?php

namespace Belly\Mensajes\Controllers\Api;
use App\Controllers\BaseController;

class MensajeController extends BaseController
{
    public function enviar()
    {
        try {
            $request = $this->request;
            $requestId = uniqid('msg_', true);

            // Puede venir como JSON o como POST normal (FormData)
            $bodyJson = [];
            try {
                $bodyJson = $request->getJSON(true) ?? [];
            } catch (\Throwable $e) {
                $bodyJson = []; // si no es JSON, ignorar
            }

            $idConversacion = $request->getPost('id_conversacion')
                ?? ($bodyJson['id_conversacion'] ?? null);

            if (!$idConversacion) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'id_conversacion es requerido',
                    'error' => 'id_conversacion es requerido',
                    'request_id' => $requestId,
                ])->setStatusCode(400);
            }

            $file = $request->getFile('file');
            $datos = [
                'id_conversacion' => $idConversacion,
            ];

            if ($file && method_exists($file, 'isValid') && $file->isValid()) {
                // ===== CASO CÁMARA / ARCHIVO (FormData con file) =====
                // Usar el mismo flujo de subida que el módulo Media (SFTP)
                $resultadoMedia = media()->subirArchivo($file, $idConversacion);

                if (!$resultadoMedia['success']) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => $resultadoMedia['error'] ?? 'Error al subir el archivo a SFTP',
                        'error'   => $resultadoMedia['error'] ?? 'Error al subir el archivo a SFTP',
                        'request_id' => $requestId,
                    ])->setStatusCode(400);
                }

                $datos['media_type'] = $resultadoMedia['media_type'] ?? 'image';
                $datos['media_url']  = $resultadoMedia['media_url'] ?? null;
                $datos['media_path'] = $resultadoMedia['media_path'] ?? null;

                // Opcional: caption
                $mensajePost = $request->getPost('mensaje') ?? ($bodyJson['mensaje'] ?? null);
                if (is_string($mensajePost)) {
                    $mensajePost = trim($mensajePost);
                }
                if (!empty($mensajePost)) {
                    $nombreOriginal = trim((string) $file->getClientName());

                    // Algunos clientes envían automáticamente el nombre del archivo como "mensaje".
                    // En ese caso, lo ignoramos para no mandar el caption con ".jpg/.png/...".
                    if (strcasecmp((string) $mensajePost, $nombreOriginal) !== 0) {
                        $datos['mensaje'] = $mensajePost;
                    }
                }

                foreach (['canal_envio', 'id_conversacion_vista', 'id_mensaje_responde', 'media_type'] as $campoExtra) {
                    $valor = $request->getPost($campoExtra) ?? ($bodyJson[$campoExtra] ?? null);
                    if ($valor !== null && $valor !== '') {
                        $datos[$campoExtra] = $valor;
                    }
                }
            } else {
                // Si vino archivo pero con error, devolver detalle explícito
                if ($file && method_exists($file, 'getError') && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                    $detalle = method_exists($file, 'getErrorString') ? $file->getErrorString() : 'Error de carga de archivo';
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => "Archivo inválido: {$detalle}",
                        'error' => "Archivo inválido: {$detalle}",
                        'request_id' => $requestId,
                    ])->setStatusCode(400);
                }

                // ===== CASO NORMAL (JSON o x-www-form-urlencoded) =====
                $bodyPost = $request->getPost() ?? [];
                $payload  = !empty($bodyJson) ? $bodyJson : $bodyPost;

                $datos = array_merge($datos, $payload);
            }

            $datos['request_id'] = $requestId;

            $canalEnvio = strtolower(trim((string) ($datos['canal_envio'] ?? '')));
            if ($canalEnvio !== '' && $canalEnvio !== 'whatsapp') {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Solo se pueden enviar mensajes por WhatsApp.',
                    'error' => 'canal_no_soportado',
                    'request_id' => $requestId,
                ])->setStatusCode(400);
            }

            $previewMensaje = mb_substr((string) ($datos['mensaje'] ?? ''), 0, 120);
            $canalLog = 'whatsapp';
            log_message('debug', '[MSG ENVIAR] request_id=' . $requestId
                . ' id_conversacion=' . ($datos['id_conversacion'] ?? 'null')
                . ' id_vista=' . ($idConversacionVista ?: 'null')
                . ' canal_envio=' . $canalLog
                . ' media_type=' . ($datos['media_type'] ?? 'text')
                . ' mensaje_preview=' . json_encode($previewMensaje, JSON_UNESCAPED_UNICODE));

            // Usar el helper mensaje(), igual que en otros métodos
            $resultado = mensaje()->crearMensajeYEnviarWhatsapp($datos);

            if (!($resultado['success'] ?? false)) {
                $error = $resultado['error'] ?? 'No se pudo enviar el mensaje.';
                log_message('error', '[MSG ENVIAR] Falló request_id=' . $requestId
                    . ' id_conversacion=' . ($datos['id_conversacion'] ?? 'null')
                    . ' canal=' . $canalLog
                    . ' error=' . $error);
                return $this->response->setJSON([
                    'success' => false,
                    'message' => $error,
                    'error' => $error,
                    'request_id' => $requestId,
                ])->setStatusCode(400);
            }

            $resultado['request_id'] = $requestId;
            $resultado['canal_envio'] = $canalLog;
            $resultado['id_conversacion_enviada'] = (int) ($datos['id_conversacion'] ?? 0);
            log_message('debug', '[MSG ENVIAR] OK request_id=' . $requestId
                . ' id_conversacion=' . ($datos['id_conversacion'] ?? 'null')
                . ' canal=' . $canalLog
                . ' id_mensaje=' . ($resultado['data']['id_mensaje_whatsapp'] ?? $resultado['data']['id'] ?? 'null'));
            return $this->response->setJSON($resultado);
        } catch (\Throwable $e) {
            log_message('error', "MensajeController::enviar [{$requestId}] - " . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error interno al enviar mensaje.',
                'error'   => $e->getMessage(),
                'request_id' => $requestId ?? null,
            ])->setStatusCode(500);
        }
    }

    public function listarPorConversacion($id_conversacion)
    {
        try {
            $idConversacion = (int) $id_conversacion;
            $mensajes = mensaje()->obtenerMensajesPorConversacion($idConversacion);
            $contextoApoyoRecurrente = conversaciones()->construirContextoApoyoRecurrente($idConversacion);

            return $this->response->setJSON([
                'success' => true,
                'mensajes' => $mensajes,
                'contexto_apoyo_recurrente' => $contextoApoyoRecurrente,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }
    
    public function subirArchivo()
    {
        try {
            $file = $this->request->getFile('file');
            $id_conversacion = $this->request->getPost('id_conversacion');
            
            log_message('debug', 'ID Conversación recibido: ' . ($id_conversacion ?? 'NULL'));
            
            if (!$file) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'No se recibió el archivo'
                ])->setStatusCode(400);
            }

            // ✅ Llamar al MediaUseCase
            $resultado = media()->subirArchivo($file, $id_conversacion);
            
            log_message('debug', 'Resultado del MediaUseCase: ' . json_encode($resultado));

            if (!$resultado['success']) {
                log_message('error', 'Error en MediaUseCase: ' . $resultado['error']);
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error']
                ])->setStatusCode(400);
            }

            // ✅ Retornar el resultado exitoso
            return $this->response->setJSON([
                'success' => true,
                'url' => $resultado['media_url'],
                'media_path' => $resultado['media_path'],
                'media_type' => $resultado['media_type']
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Excepción en subirArchivo: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    public function enviarPlantilla()
    {
        try {
            $data = $this->request->getJSON(true);

            if (empty($data)) {
                $data = [
                    'id_conversacion' => $this->request->getPost('id_conversacion'),
                    'template_id' => $this->request->getPost('template_id'),
                    'template_name' => $this->request->getPost('template_name'),
                    'language' => $this->request->getPost('language'),
                    'parameters' => $this->request->getPost('parameters'),
                ];
            }

            if (empty($data['id_conversacion'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El campo id_conversacion es obligatorio'
                ])->setStatusCode(400);
            }

            if (empty($data['template_id']) || empty($data['template_name'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'Los campos template_id y template_name son obligatorios'
                ])->setStatusCode(400);
            }

            $resultado = mensaje()->enviarPlantillaWhatsapp($data);

            if (!$resultado['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error']
                ])->setStatusCode(400);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado['data']
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }
}
