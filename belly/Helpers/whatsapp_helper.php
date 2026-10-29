<?php

if (!function_exists('procesarTipoMensajeWhatsappEntrante')) {
    function procesarTipoMensajeWhatsappEntrante(array $message, int $app_id, ?int $id_conversacion = null): array 
    {
        $tipo = $message['type'] ?? 'text';
        $mensaje = '';
        $media_id = null;
        $media_data = [
            'media_url' => null,
            'media_path' => null,
            'media_type' => null
        ];
        // Al tocar un botón o una opción de lista no se muestra la cita del mensaje del bot: se vería repetido.
        $id_mensaje_responde = ($tipo !== 'interactive' && isset($message['context']['id'])) ? $message['context']['id'] : null;
        $referral_mensaje = null;
        $referral_media_data = [
            'media_url' => null,
            'media_path' => null,
            'media_type' => null
        ];

        switch ($tipo) {
            case 'text':
                $mensaje = $message['text']['body'] ?? '[Sin texto]';
                break;

            case 'image':
                $caption = $message['image']['caption'] ?? '[Sin descripción]';
                $mensaje = "[Imagen] $caption";
                $media_id = $message['image']['id'] ?? null;
                if ($media_id && $id_conversacion) {
                    $media_result = media()->procesarMedia(
                        $media_id,
                        'image',
                        $app_id,
                        $id_conversacion,
                        $message['image']['url'] ?? null,
                        $message['image']['mime_type'] ?? null,
                    );
                    if ($media_result['success']) {
                        $media_data = $media_result;
                    }
                }
                break;

            case 'video':
                $caption = $message['video']['caption'] ?? '[Sin descripción]';
                $mensaje = "[Video] $caption";
                $media_id = $message['video']['id'] ?? null;
                if ($media_id && $id_conversacion) {
                    $media_result = media()->procesarMedia(
                        $media_id,
                        'video',
                        $app_id,
                        $id_conversacion,
                        $message['video']['url'] ?? null,
                        $message['video']['mime_type'] ?? null,
                    );
                    if ($media_result['success']) {
                        $media_data = $media_result;
                    }
                }
                break;

            case 'audio':
                $mensaje = "[Audio] " . ($message['audio']['caption'] ?? 'Mensaje de voz');
                $media_id = $message['audio']['id'] ?? null;
                if ($media_id && $id_conversacion) {
                    $media_result = media()->procesarMedia(
                        $media_id,
                        'audio',
                        $app_id,
                        $id_conversacion,
                        $message['audio']['url'] ?? null,
                        $message['audio']['mime_type'] ?? null,
                    );
                    if ($media_result['success']) {
                        $media_data = $media_result;
                    }
                }
                break;

            case 'document':
                $filename = $message['document']['filename'] ?? 'documento';
                $mensaje = "[Documento: $filename]";
                $media_id = $message['document']['id'] ?? null;
                if ($media_id && $id_conversacion) {
                    $media_result = media()->procesarMedia(
                        $media_id,
                        'document',
                        $app_id,
                        $id_conversacion,
                        $message['document']['url'] ?? null,
                        $message['document']['mime_type'] ?? null,
                    );
                    if ($media_result['success']) {
                        $media_data = $media_result;
                    }
                }
                break;

            case 'sticker':
                $mensaje = "[Sticker]";
                $media_id = $message['sticker']['id'] ?? null;
                if ($media_id && $id_conversacion) {
                    $media_result = media()->procesarMedia(
                        $media_id,
                        'sticker',
                        $app_id,
                        $id_conversacion,
                        $message['sticker']['url'] ?? null,
                        $message['sticker']['mime_type'] ?? null,
                    );
                    if ($media_result['success']) {
                        $media_data = $media_result;
                    }
                }
                break;

            case 'button':
                $btn = $message['button'] ?? [];
                $mensaje = trim((string) ($btn['text'] ?? $btn['payload'] ?? ''));
                if ($mensaje === '') {
                    $mensaje = '[Respuesta de botón]';
                }
                break;

            case 'interactive':
                $interactive = $message['interactive'] ?? [];
                $replyType = (string) ($interactive['type'] ?? '');
                if ($replyType === 'button_reply') {
                    $reply = $interactive['button_reply'] ?? [];
                    $mensaje = trim((string) ($reply['title'] ?? $reply['id'] ?? ''));
                } elseif ($replyType === 'list_reply') {
                    $reply = $interactive['list_reply'] ?? [];
                    $mensaje = trim((string) ($reply['title'] ?? $reply['id'] ?? ''));
                } else {
                    $mensaje = '';
                }
                if ($mensaje === '') {
                    $mensaje = '[Respuesta interactiva]';
                }
                break;

            case 'contacts':
                if (!empty($message['contacts']) && is_array($message['contacts'])) {
                    $contacto = $message['contacts'][0];
                    $nombre = $contacto['name']['formatted_name'] ?? '[Sin nombre]';
                    $telefono = $contacto['phones'][0]['phone'] ?? '[Sin teléfono]';
                    $tipo_tel = $contacto['phones'][0]['type'] ?? '';
                    $mensaje = "Contacto recibido: $nombre, Tel: $telefono ($tipo_tel)";
                    if (!empty($contacto['addresses'][0]['country'])) {
                        $mensaje .= ", País: " . $contacto['addresses'][0]['country'];
                    }
                } else {
                    $mensaje = "[Contacto recibido]";
                }
                break;

            case 'location':
                if (!empty($message['location'])) {
                    $lat = $message['location']['latitude'] ?? '';
                    $lng = $message['location']['longitude'] ?? '';
                    $name = trim((string) ($message['location']['name'] ?? ''));
                    $address = trim((string) ($message['location']['address'] ?? ''));

                    $partes = [];
                    if ($name !== '') {
                        $partes[] = $name;
                    }
                    if ($address !== '') {
                        $partes[] = $address;
                    }
                    $geo = ($lat !== '' && $lng !== '') ? "{$lat}, {$lng}" : '';
                    if ($partes !== []) {
                        $mensaje = 'Ubicación recibida: ' . implode(' · ', $partes);
                        if ($geo !== '') {
                            $mensaje .= ' (' . $geo . ')';
                        }
                    } elseif ($geo !== '') {
                        $mensaje = 'Ubicación recibida (' . $geo . ')';
                    } else {
                        $mensaje = '[Ubicación recibida]';
                    }

                    if ($lat !== '' && $lng !== '') {
                        $media_data['media_type'] = 'location';
                        $media_data['media_url'] = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
                    }
                } else {
                    $mensaje = "[Ubicación recibida]";
                }
                break;

            case 'system':
                $sysType = (string) ($message['system']['type'] ?? 'system');
                $sysBody = trim((string) ($message['system']['body'] ?? ''));
                $mensaje = $sysBody !== ''
                    ? '[Sistema WhatsApp] ' . $sysBody
                    : '[Sistema WhatsApp: ' . $sysType . ']';
                break;

            default:
                if ($tipo === 'unsupported') {
                    $mensaje = "[El mensaje no es soportado por Whatsapp]";
                } else {
                    $mensaje = "[Mensaje tipo: $tipo]";
                }

                break;

        }

        if (!empty($message['referral']) && is_array($message['referral'])) {
            $referral = $message['referral'];
            $partes_referral = ['[Publicidad]'];

            if (!empty($referral['headline'])) {
                $partes_referral[] = 'Título: ' . $referral['headline'];
            }

            if (!empty($referral['body'])) {
                $partes_referral[] = 'Texto: ' . $referral['body'];
            }

            if (!empty($referral['source_url'])) {
                $partes_referral[] = 'URL: ' . $referral['source_url'];
            }

            if (!empty($referral['media_type'])) {
                $partes_referral[] = 'Tipo de media: ' . $referral['media_type'];
            }

            if (!empty($referral['video_url'])) {
                $partes_referral[] = 'Video: ' . $referral['video_url'];
            }

            if (!empty($referral['welcome_message']['text'])) {
                $partes_referral[] = 'Mensaje de bienvenida: ' . $referral['welcome_message']['text'];
            }

            $referral_mensaje = implode("\n", $partes_referral);

            if (!empty($referral['thumbnail_url']) && $id_conversacion) {
                $thumbnail_result = media()->procesarReferralThumbnail(
                    $referral['thumbnail_url'],
                    $id_conversacion,
                    $message['id'] ?? null
                );

                if ($thumbnail_result['success']) {
                    $referral_media_data = $thumbnail_result;
                }
            }
        }

        log_message('debug', 'Mensaje generado para BD: ' . $mensaje);

        $tiposMediaWhatsapp = ['image', 'video', 'audio', 'document', 'sticker'];
        $mediaTypeGuardado = $media_data['media_type'] ?? null;
        if ($mediaTypeGuardado === null && in_array($tipo, $tiposMediaWhatsapp, true)) {
            $mediaTypeGuardado = $tipo;
        }

        return [
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'media_id' => $media_id,
            'media_url' => $media_data['media_url'],
            'media_path' => $media_data['media_path'],
            'media_type' => $mediaTypeGuardado,
            'id_mensaje_responde' => $id_mensaje_responde,
            'referral_mensaje' => $referral_mensaje,
            'referral_media_url' => $referral_media_data['media_url'],
            'referral_media_path' => $referral_media_data['media_path'],
            'referral_media_type' => $referral_media_data['media_type'],
        ];
    }
}



















if (! function_exists('construir_encabezado_destinatario_whatsapp')) {
    /**
     * Encabezado Graph API para envío saliente.
     * Teléfono E.164 → `to`. BSUID (PE.xxx) → `recipient` (Meta rechaza BSUID en `to`).
     *
     * @return array{messaging_product:string,recipient_type:string,to?:string,recipient?:string}
     */
    function construir_encabezado_destinatario_whatsapp(string $destino): array
    {
        $destino = trim($destino);
        $base = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
        ];

        if (function_exists('es_whatsapp_bsuid') && es_whatsapp_bsuid($destino)) {
            $base['recipient'] = $destino;

            return $base;
        }

        $base['to'] = $destino;

        return $base;
    }
}

if (! function_exists('construir_interactivo_whatsapp')) {
    /**
     * Arma el bloque "interactive" de la Cloud API.
     * Botones: hasta 3 (título ≤ 20). Lista: hasta 10 opciones (título ≤ 24, descripción ≤ 72).
     *
     * @param array{tipo?:string, texto?:string, botones?:array, boton?:string, opciones?:array} $datos
     * @return array{interactive:array, mensaje_bd:string}|null
     */
    function construir_interactivo_whatsapp(array $datos): ?array
    {
        $recorta = static fn (string $t, int $max): string => mb_substr(trim($t), 0, $max);
        $cuerpo = $recorta((string) ($datos['texto'] ?? ''), 1024);
        if ($cuerpo === '') {
            return null;
        }

        if (($datos['tipo'] ?? '') === 'botones') {
            $botones = [];
            foreach (array_slice((array) ($datos['botones'] ?? []), 0, 3) as $b) {
                $titulo = $recorta((string) ($b['titulo'] ?? ''), 20);
                if ($titulo === '' || empty($b['id'])) {
                    continue;
                }
                $botones[] = ['type' => 'reply', 'reply' => ['id' => (string) $b['id'], 'title' => $titulo]];
            }
            if ($botones === []) {
                return null;
            }

            return [
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => $cuerpo],
                    'action' => ['buttons' => $botones],
                ],
                'mensaje_bd' => $cuerpo,
            ];
        }

        if (($datos['tipo'] ?? '') === 'lista') {
            $filas = [];
            foreach (array_slice((array) ($datos['opciones'] ?? []), 0, 10) as $o) {
                $titulo = $recorta((string) ($o['titulo'] ?? ''), 24);
                if ($titulo === '' || empty($o['id'])) {
                    continue;
                }
                $fila = ['id' => (string) $o['id'], 'title' => $titulo];
                $descripcion = $recorta((string) ($o['descripcion'] ?? ''), 72);
                if ($descripcion !== '') {
                    $fila['description'] = $descripcion;
                }
                $filas[] = $fila;
            }
            if ($filas === []) {
                return null;
            }

            $textoBoton = $recorta((string) ($datos['boton'] ?? ''), 20);
            return [
                'interactive' => [
                    'type' => 'list',
                    'body' => ['text' => $cuerpo],
                    'action' => [
                        'button' => $textoBoton !== '' ? $textoBoton : 'Ver opciones',
                        'sections' => [['title' => 'Opciones', 'rows' => $filas]],
                    ],
                ],
                'mensaje_bd' => $cuerpo,
            ];
        }

        return null;
    }
}

if (!function_exists('procesarMensajeWhatsappSaliente')) {
    function procesarMensajeWhatsappSaliente(array $datos, string $destino): array
    {
        $tipo = $datos['media_type'] ?? 'text';
        $mensaje_bd = '';
        $payload = construir_encabezado_destinatario_whatsapp($destino);

        $addContext = function (&$payload, $datos) {
            if (!empty($datos['id_mensaje_responde'])) {
                $payload['context'] = [
                    'message_id' => $datos['id_mensaje_responde']
                ];
            }
        };

        // Botones o lista (solo los arma el bot automático, nunca llegan del cliente web).
        if (! empty($datos['interactivo']) && is_array($datos['interactivo'])) {
            $armado = construir_interactivo_whatsapp($datos['interactivo']);
            if ($armado !== null) {
                $payload['type'] = 'interactive';
                $payload['interactive'] = $armado['interactive'];
                $payload['mensaje_bd'] = $armado['mensaje_bd'];
                $addContext($payload, $datos);

                return $payload;
            }
        }

        switch ($tipo) {
            case 'text':
                $mensaje_bd = $datos['mensaje'] ?? '[Sin texto]';
                $payload['type'] = 'text';
                $payload['text'] = ['body' => $datos['mensaje']];
                $addContext($payload, $datos);
                break;

            case 'image':
                $mensaje_bd = (isset($datos['mensaje']) && trim((string) $datos['mensaje']) !== '')
                    ? trim((string) $datos['mensaje'])
                    : '[Sin texto]';
                $payload['type'] = 'image';
                if (! empty($datos['whatsapp_media_id'])) {
                    $payload['image'] = [
                        'id' => $datos['whatsapp_media_id'],
                    ];
                    if (trim((string) ($datos['mensaje'] ?? '')) !== '') {
                        $payload['image']['caption'] = $datos['mensaje'];
                    }
                } else {
                    $payload['image'] = [
                        'link' => $datos['media_url'],
                        'caption' => $datos['mensaje'] ?? '',
                    ];
                }
                $addContext($payload, $datos);
                break;

            case 'video':
                $mensaje_bd = (isset($datos['mensaje']) && trim((string) $datos['mensaje']) !== '')
                    ? trim((string) $datos['mensaje'])
                    : '[Sin texto]';
                $payload['type'] = 'video';
                if (! empty($datos['whatsapp_media_id'])) {
                    $payload['video'] = [
                        'id' => $datos['whatsapp_media_id'],
                    ];
                    if (trim((string) ($datos['mensaje'] ?? '')) !== '') {
                        $payload['video']['caption'] = $datos['mensaje'];
                    }
                } else {
                    $payload['video'] = [
                        'link' => $datos['media_url'],
                        'caption' => $datos['mensaje'] ?? '',
                    ];
                }
                $addContext($payload, $datos);
                break;

            case 'document':
                $mensaje_bd = (isset($datos['mensaje']) && trim((string) $datos['mensaje']) !== '')
                    ? trim((string) $datos['mensaje'])
                    : '[Sin texto]';
                $payload['type'] = 'document';
                if (! empty($datos['whatsapp_media_id'])) {
                    $payload['document'] = [
                        'id' => $datos['whatsapp_media_id'],
                    ];
                    if (! empty($datos['filename'])) {
                        $payload['document']['filename'] = $datos['filename'];
                    }
                } else {
                    $payload['document'] = [
                        'link' => $datos['media_url'],
                        'filename' => $datos['filename'] ?? 'documento',
                    ];
                }
                $addContext($payload, $datos);
                break;

            case 'audio':
                $mensaje_bd = (isset($datos['mensaje']) && trim((string) $datos['mensaje']) !== '')
                    ? trim((string) $datos['mensaje'])
                    : '[Audio]';
                $payload['type'] = 'audio';
                $payload['audio'] = ! empty($datos['whatsapp_media_id'])
                    ? ['id' => $datos['whatsapp_media_id']]
                    : ['link' => $datos['media_url']];
                $addContext($payload, $datos);
                break;

            case 'sticker':
                $mensaje_bd = '[Sticker]';
                $payload['type'] = 'sticker';
                $payload['sticker'] = ! empty($datos['whatsapp_media_id'])
                    ? ['id' => $datos['whatsapp_media_id']]
                    : ['link' => $datos['media_url']];
                $addContext($payload, $datos);
                break;

            case 'location':
                $lat = $datos['latitude'] ?? '';
                $lng = $datos['longitude'] ?? '';
                $name = trim((string) ($datos['name'] ?? ''));
                $address = trim((string) ($datos['address'] ?? ''));
                $partes = array_filter([$name, $address]);
                $geo = ($lat !== '' && $lng !== '') ? "{$lat}, {$lng}" : '';
                if ($partes !== []) {
                    $mensaje_bd = 'Se envió ubicación: ' . implode(' · ', $partes) . ($geo !== '' ? ' (' . $geo . ')' : '');
                } elseif ($geo !== '') {
                    $mensaje_bd = 'Se envió ubicación (' . $geo . ')';
                } else {
                    $mensaje_bd = 'Se envió ubicación';
                }
                $payload['type'] = 'location';
                $payload['location'] = [
                    'latitude' => (string) $lat,
                    'longitude' => (string) $lng,
                    'name' => $name,
                    'address' => $address,
                ];
                // WhatsApp no soporta context en location
                break;

            case 'contacts':
                $mensaje_bd = "Se envió contacto";
                $payload['type'] = 'contacts';
                $payload['contacts'] = $datos['contacts'];
                // WhatsApp no soporta context en contacts
                break;

            default:
                if ($tipo === 'unsupported') {
                    $mensaje_bd = "[El mensaje no es soportado por Whatsapp]";
                } else {
                    $mensaje_bd = "[Mensaje tipo: $tipo]";
                }
                break;
        }

        $payload['mensaje_bd'] = $mensaje_bd;

        return $payload;
    }
}

if (! function_exists('es_whatsapp_bsuid')) {
    /**
     * BSUID de usuario: {ISO3166-alpha2}.{alfanumérico} (ej. PE.123…, US.134…).
     * No confundir con parent BSUID (XX.ENT.…).
     */
    function es_whatsapp_bsuid(?string $valor): bool
    {
        $v = trim((string) $valor);
        if ($v === '' || strlen($v) > 140) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z]{2}\.[A-Za-z0-9]{1,128}$/', $v);
    }
}

if (! function_exists('es_whatsapp_parent_bsuid')) {
    /** Parent BSUID: {ISO}.{ENT}.{alfanumérico} (ej. US.ENT.118…). */
    function es_whatsapp_parent_bsuid(?string $valor): bool
    {
        $v = trim((string) $valor);
        if ($v === '' || strlen($v) > 160) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z]{2}\.ENT\.[A-Za-z0-9]{1,128}$/i', $v);
    }
}

if (! function_exists('normalizar_telefono_whatsapp')) {
    function normalizar_telefono_whatsapp(?string $valor): ?string
    {
        $v = trim((string) $valor);
        if ($v === '' || es_whatsapp_bsuid($v) || es_whatsapp_parent_bsuid($v)) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $v) ?? '';
        // E.164 sin '+': 8–15 dígitos (evita basura y BSUID mal parseados)
        if ($digitos === '' || strlen($digitos) < 8 || strlen($digitos) > 15) {
            return null;
        }

        return $digitos;
    }
}

if (! function_exists('extraer_identidad_whatsapp_entrante')) {
    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $value
     *
     * @return array{telefono:?string,wa_user_id:?string,wa_parent_user_id:?string,nombre:?string}
     */
    function extraer_identidad_whatsapp_entrante(array $message, array $value = []): array
    {
        $contact = [];
        if (isset($value['contacts'][0]) && is_array($value['contacts'][0])) {
            $contact = $value['contacts'][0];
        }

        $from = trim((string) ($message['from'] ?? ''));
        $waIdContact = trim((string) ($contact['wa_id'] ?? ''));
        $userIdContact = trim((string) ($contact['user_id'] ?? ''));
        $fromUserId = trim((string) ($message['from_user_id'] ?? ''));
        $parentUserId = trim((string) (
            $contact['parent_user_id']
            ?? $message['from_parent_user_id']
            ?? ''
        ));

        $telefono = normalizar_telefono_whatsapp($waIdContact)
            ?? normalizar_telefono_whatsapp($from);

        $waUserId = null;
        foreach ([$userIdContact, $fromUserId, $from] as $candidato) {
            if (es_whatsapp_bsuid($candidato)) {
                $waUserId = $candidato;
                break;
            }
        }

        $nombre = null;
        if (isset($contact['profile']['name'])) {
            $nombre = trim((string) $contact['profile']['name']) ?: null;
        }
        if ($nombre === null && isset($contact['profile']['username'])) {
            $u = trim((string) $contact['profile']['username']);
            $nombre = $u !== '' ? ltrim($u, '@') : null;
        }

        return [
            'telefono' => $telefono,
            'wa_user_id' => $waUserId,
            'wa_parent_user_id' => es_whatsapp_parent_bsuid($parentUserId) ? $parentUserId : null,
            'nombre' => $nombre,
        ];
    }
}

if (! function_exists('resolver_destino_whatsapp')) {
    function resolver_destino_whatsapp(?string $telefono, ?string $waUserId): string
    {
        $tel = normalizar_telefono_whatsapp($telefono);
        if ($tel !== null) {
            return $tel;
        }

        $bsuid = trim((string) $waUserId);
        // Graph acepta to = BSUID de usuario (no parent).
        if (es_whatsapp_bsuid($bsuid)) {
            return $bsuid;
        }

        return '';
    }
}