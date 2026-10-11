<?php

class SocketHelper 
{
    private static $socketUrl = null;
    
    /**
     * Emitir mensaje (reemplaza el curl que tienes en MensajeUseCase)
     */
    public static function emitirMensaje($conversacionId, $mensaje, $idUsuario = null) 
    {
        $data = [
            'conversacion_id' => $conversacionId,
            'mensaje' => $mensaje
            
        ];
        
        if ($idUsuario !== null) {
            $data['id_usuario'] = $idUsuario;
        }
        
        return self::emitir('emitir-mensaje', $data);
    }
    
    /**
     * Emitir conversación (nueva)
     */
    public static function emitirConversacion($conversacionData) 
    {
        return self::emitir('emitir-conversacion', $conversacionData);
    }
    
    /**
     * Emitir actualización de conversación (estado, motivo, etc.)
     */
    public static function emitirActualizacionConversacion($conversacionData) 
    {
        return self::emitir('emitir-actualizar-conversacion', $conversacionData);
    }
    
    public static function emitirEstadoMensaje($mensajeId, $nuevoEstado, $datosMensaje = null) 
    {
        $data = [
            'tipo' => 'estado_mensaje_actualizado',
            'mensaje_id' => $mensajeId,
            'nuevo_estado' => $nuevoEstado,
            'datos_mensaje' => $datosMensaje
        ];
        return self::emitir('emitir-estado-mensaje', $data);
    }
    
    /**
     * Emitir actualización de contadores de mensajes no leídos
     */
    public static function emitirActualizacionContadores($conversacionId, $mensajesNoLeidos, $ultimoMensajeNoLeidoFecha, $idUsuario = null) 
    {
        $data = [
            'conversacion_id' => $conversacionId,
            'mensajes_no_leidos' => $mensajesNoLeidos,
            'ultimo_mensaje_no_leido_fecha' => $ultimoMensajeNoLeidoFecha
        ];
        
        if ($idUsuario !== null) {
            $data['id_usuario'] = $idUsuario;
        }
        
        return self::emitir('emitir-actualizar-contadores', $data);
    }
    
    /**
     * Método privado para hacer la petición HTTP
     */
    private static function emitir($endpoint, $data) 
    {
        try {
            if (self::$socketUrl === null) {
                // Preferir loopback si el socket corre en el mismo servidor (evita TLS/DNS públicos).
                $envUrl = getenv('SOCKET_URL');
                self::$socketUrl = ($envUrl !== false && trim((string) $envUrl) !== '')
                    ? trim((string) $envUrl)
                    : 'http://127.0.0.1:4000';
            }

            $url = rtrim(self::$socketUrl, '/') . '/' . ltrim($endpoint, '/');
            $jsonData = json_encode($data);
            
            log_message('debug', "[SOCKET HELPER] Intentando emitir: {$endpoint}");
            log_message('debug', "[SOCKET HELPER] URL: {$url}");
            log_message('debug', "[SOCKET HELPER] Data: {$jsonData}");
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // Fail-fast: si el socket está caído o falta el endpoint, no congelar el CRM.
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            log_message('debug', "[SOCKET HELPER] HTTP Code: {$httpCode}, Error: {$error}");
            log_message('debug', "[SOCKET HELPER] Response: " . substr($result, 0, 500));
            
            if ($httpCode !== 200) {
                log_message('error', "Socket error: HTTP $httpCode - URL: $url - Error: $error - Response: " . substr($result, 0, 200));
                return false;
            }
            
            log_message('info', "[SOCKET HELPER] Emitido exitosamente: {$endpoint}");
            return true;
        } catch (\Exception $e) {
            log_message('error', 'Socket exception: ' . $e->getMessage());
            return false;
        }
    }
}





//############################################################################################

if (!function_exists('emitir_mensaje_socket')) {
    function emitir_mensaje_socket($conversacionId, $mensaje, $idUsuario = null) {
        return SocketHelper::emitirMensaje($conversacionId, $mensaje, $idUsuario);
    }
}

if (!function_exists('emitir_conversacion_socket')) {
    function emitir_conversacion_socket($conversacionData) {
        return SocketHelper::emitirConversacion($conversacionData);
    }
}

if (!function_exists('emitir_actualizacion_conversacion_socket')) {
    function emitir_actualizacion_conversacion_socket($conversacionData) {
        return SocketHelper::emitirActualizacionConversacion($conversacionData);
    }
}

if (!function_exists('emitir_estado_mensaje_socket')) {
    function emitir_estado_mensaje_socket($mensajeId, $nuevoEstado, $datosMensaje = null) {
        return SocketHelper::emitirEstadoMensaje($mensajeId, $nuevoEstado, $datosMensaje);
    }
}

if (!function_exists('emitir_actualizacion_contadores_socket')) {
    function emitir_actualizacion_contadores_socket($conversacionId, $mensajesNoLeidos, $ultimoMensajeNoLeidoFecha, $idUsuario = null) {
        return SocketHelper::emitirActualizacionContadores($conversacionId, $mensajesNoLeidos, $ultimoMensajeNoLeidoFecha, $idUsuario);
    }
}

if (!function_exists('calcular_y_emitir_contadores_no_leidos')) {
    /**
     * Calcula los contadores de mensajes no leídos y los emite por socket
     */
    function calcular_y_emitir_contadores_no_leidos($id_conversacion) {
        try {
            $db = \Config\Database::connect();
            
            // Calcular mensajes no leídos
            $builder = $db->table('mensaje');
            $mensajesNoLeidos = $builder
                ->where('id_conversacion', $id_conversacion)
                ->where('contesta', 'cliente')
                ->where('id_estado', 3)
                ->where('deleted_at', null)
                ->countAllResults(false);
            
            // Obtener fecha del último mensaje no leído
            $ultimoMensaje = $builder
                ->select('created_at')
                ->where('id_conversacion', $id_conversacion)
                ->where('contesta', 'cliente')
                ->where('id_estado', 3)
                ->where('deleted_at', null)
                ->orderBy('created_at', 'DESC')
                ->limit(1)
                ->get()
                ->getRow();
            
            $ultimoMensajeNoLeidoFecha = $ultimoMensaje ? $ultimoMensaje->created_at : null;
            
            // Obtener id_usuario de la conversación
            $conversacion = model('ConversacionModel')->find($id_conversacion);
            $id_usuario = $conversacion ? $conversacion->id_usuario : null;
            
            // Emitir actualización
            emitir_actualizacion_contadores_socket(
                $id_conversacion,
                $mensajesNoLeidos,
                $ultimoMensajeNoLeidoFecha,
                $id_usuario
            );
        } catch (\Exception $e) {
            log_message('error', 'Error calculando contadores no leídos: ' . $e->getMessage());
        }
    }
}
