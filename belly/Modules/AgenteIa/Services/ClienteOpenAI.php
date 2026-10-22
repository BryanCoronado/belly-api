<?php

namespace Belly\AgenteIa\Services;

use Belly\AgenteIa\Domain\ModeloLenguaje;
use DomainException;

/** Habla con la API de OpenAI (chat completions) y traduce sus errores a mensajes entendibles. */
class ClienteOpenAI implements ModeloLenguaje
{
    private const URL = 'https://api.openai.com/v1';
    /** WhatsApp (Meta) reintenta el aviso si tardamos mucho en contestarle; mejor pasar a una persona que esperar. */
    private const SEGUNDOS = 20;
    private const MAX_TOKENS = 600;
    private const FUNCION_TRASPASO = 'pasar_a_asesor';

    public function responder(string $apiKey, string $modelo, array $mensajes, float $temperatura): array
    {
        $respuesta = $this->pedir('POST', '/chat/completions', $apiKey, [
            'model' => $modelo,
            'messages' => $mensajes,
            'temperature' => $temperatura,
            'max_tokens' => self::MAX_TOKENS,
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => self::FUNCION_TRASPASO,
                    'description' => 'Pasa la conversación a una persona del equipo.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'motivo' => ['type' => 'string', 'description' => 'Por qué se pasa, en pocas palabras.'],
                            'mensaje_cliente' => ['type' => 'string', 'description' => 'Mensaje corto para avisar al cliente que lo atenderá una persona.'],
                        ],
                        'required' => ['motivo'],
                    ],
                ],
            ]],
            'tool_choice' => 'auto',
        ]);

        $mensaje = $respuesta['choices'][0]['message'] ?? [];
        foreach ((array) ($mensaje['tool_calls'] ?? []) as $llamada) {
            if (($llamada['function']['name'] ?? '') === self::FUNCION_TRASPASO) {
                $argumentos = json_decode((string) ($llamada['function']['arguments'] ?? '{}'), true) ?: [];

                return [
                    'texto' => trim((string) ($argumentos['mensaje_cliente'] ?? '')) ?: (trim((string) ($mensaje['content'] ?? '')) ?: null),
                    'traspaso' => trim((string) ($argumentos['motivo'] ?? 'El asistente pidió ayuda')) ?: 'El asistente pidió ayuda',
                ];
            }
        }

        $texto = trim((string) ($mensaje['content'] ?? ''));

        return ['texto' => $texto !== '' ? $texto : null, 'traspaso' => null];
    }

    public function verificar(string $apiKey): void
    {
        $this->pedir('GET', '/models', $apiKey);
    }

    /** @return array<string, mixed> */
    private function pedir(string $metodo, string $ruta, string $apiKey, ?array $cuerpo = null): array
    {
        $opciones = [
            'headers' => ['Authorization' => 'Bearer ' . $apiKey, 'Content-Type' => 'application/json'],
            'http_errors' => false,
            'timeout' => self::SEGUNDOS,
            'connect_timeout' => 10,
        ];
        if ($cuerpo !== null) {
            $opciones['body'] = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
        }

        try {
            $http = \Config\Services::curlrequest([], null, null, false)->request($metodo, self::URL . $ruta, $opciones);
        } catch (\Throwable $e) {
            log_message('error', '[IA] Sin conexión con OpenAI: ' . $e->getMessage());
            throw new DomainException('No pudimos conectarnos con OpenAI. Revisa la conexión a internet del servidor.');
        }

        $estado = $http->getStatusCode();
        $datos = json_decode((string) $http->getBody(), true);
        if ($estado >= 200 && $estado < 300 && is_array($datos)) {
            return $datos;
        }

        $codigo = (string) ($datos['error']['code'] ?? '');
        log_message('error', '[IA] OpenAI respondió ' . $estado . ' ' . $codigo . ': ' . mb_substr((string) ($datos['error']['message'] ?? ''), 0, 200));

        throw new DomainException(match (true) {
            $estado === 401 => 'La llave de OpenAI no es válida. Revisa que la copiaste completa.',
            $codigo === 'insufficient_quota' => 'Tu cuenta de OpenAI no tiene saldo. Recárgala en platform.openai.com.',
            $estado === 429 => 'OpenAI está recibiendo demasiadas consultas. Intenta en un momento.',
            $estado === 404 || $codigo === 'model_not_found' => 'El modelo elegido no está disponible en tu cuenta de OpenAI. Elige otro.',
            default => 'OpenAI no pudo responder ahora. Intenta de nuevo en unos minutos.',
        });
    }
}
