<?php

use Belly\Clientes\UseCases\ClienteUseCase;
use Belly\Usuarios\UseCases\AccesoUseCase;
use Belly\Usuarios\UseCases\AsesorUseCase;
use Belly\Catalogo\UseCases\FamiliaProductoUseCase;
use Belly\Catalogo\UseCases\ImagenProductoUseCase;
use Belly\Catalogo\UseCases\ProductoUseCase;
use Belly\AgenteIa\UseCases\AgenteIaUseCase;
use Belly\AgenteIa\UseCases\AtencionIaUseCase;
use Belly\Reparto\UseCases\AsignadorChatUseCase;
use Belly\Reparto\UseCases\GrupoAtencionUseCase;
use Belly\Reparto\UseCases\RepartoUseCase;
use Belly\Usuarios\UseCases\UsuarioUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioUsuarioUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioPermisoRolUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioPermisoUsuarioUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioRolUseCase;
use Belly\Usuarios\UseCases\UsuarioPermisoEspecialUseCase;

use Belly\Mensajes\UseCases\MensajeUseCase;
use Belly\Mensajes\UseCases\PlantillaMensajeUseCase;
use Belly\Mensajes\UseCases\PlantillaRapidaUseCase;
use Belly\Conversacion\UseCases\ConversacionUseCase;
use Belly\Conversacion\UseCases\ConversacionMotivoUseCase;
use Belly\Conversacion\UseCases\EtiquetaUseCase;
use Belly\Webhooks\UseCases\WebhookUseCase;
use Belly\Configuracion\UseCases\AppUseCase;
use Belly\Configuracion\UseCases\EmpresaUseCase;
use Belly\Configuracion\UseCases\PortafolioUseCase;
use Belly\Media\UseCases\MediaUseCase;
use Belly\Auth\UseCases\AuthUseCase;
use Belly\Permisos\UseCases\GrupoPermisoUseCase;
use Belly\Permisos\UseCases\PermisoUseCase;
use Belly\Permisos\UseCases\GrupoUsuarioPermisoUseCase;
use Belly\Modulos\UseCases\ModuloGrupoUseCase;
use Belly\Modulos\UseCases\ModulosUseCase;
use Belly\Conversacion\UseCases\ConversacionVentaUseCase;
use Belly\Clientes\UseCases\ListaNegraUseCase;

if (!function_exists('auth')) {
    function auth(): AuthUseCase
    {
        return service('AuthUseCase');
    }
}

if (!function_exists('id_empresa_sesion')) {
    function id_empresa_sesion(): ?int
    {
        $contexto = \Belly\Services\ContextoEmpresa::actual();
        if ($contexto !== null) {
            return $contexto;
        }

        static $enCurso = false;
        if ($enCurso) {
            return null;
        }

        $enCurso = true;
        try {
            $usuario = auth()->getUsuario();
            if ($usuario === null) {
                return null;
            }

            $id = (int) ($usuario->id_empresa ?? 0);

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            $enCurso = false;
        }
    }
}

if (!function_exists('clientes')) {
    function clientes(): ClienteUseCase
    {
        return service('ClienteUseCase');
    }
}

// MODULO USUARIOS
if (!function_exists('usuario')) {
    function usuario(): UsuarioUseCase
    {
        return service('UsuarioUseCase');
    }
}

if (!function_exists('asesores')) {
    function asesores(): AsesorUseCase
    {
        return service('AsesorUseCase');
    }
}

if (!function_exists('grupo_usuario')) {
    function grupo_usuario(): GrupoUsuarioUseCase
    {
        return service('GrupoUsuarioUseCase');
    }
}

if (!function_exists('grupo_usuario_usuario')) {
    function grupo_usuario_usuario(): GrupoUsuarioUsuarioUseCase
    {
        return service('GrupoUsuarioUsuarioUseCase');
    }
}

if (!function_exists('grupo_usuario_permiso_rol')) {
    function grupo_usuario_permiso_rol(): GrupoUsuarioPermisoRolUseCase
    {
        return service('GrupoUsuarioPermisoRolUseCase');
    }
}

if (!function_exists('grupo_usuario_permiso_usuario')) {
    function grupo_usuario_permiso_usuario(): GrupoUsuarioPermisoUsuarioUseCase
    {
        return service('GrupoUsuarioPermisoUsuarioUseCase');
    }
}

if (!function_exists('acceso')) {
    function acceso(): AccesoUseCase
    {
        return service('AccesoUseCase');
    }
}

if (!function_exists('grupo_usuario_rol')) {
    function grupo_usuario_rol(): GrupoUsuarioRolUseCase
    {
        return service('GrupoUsuarioRolUseCase');
    }
}

if (!function_exists('usuario_permiso_especial')) {
    function usuario_permiso_especial(): UsuarioPermisoEspecialUseCase
    {
        return service('UsuarioPermisoEspecialUseCase');
    }
}
// #####################################

// MODULO CATALOGO
if (!function_exists('catalogo_familias')) {
    function catalogo_familias(): FamiliaProductoUseCase
    {
        return service('FamiliaProductoUseCase');
    }
}

if (!function_exists('catalogo_productos')) {
    function catalogo_productos(): ProductoUseCase
    {
        return service('ProductoUseCase');
    }
}

if (!function_exists('catalogo_imagenes')) {
    function catalogo_imagenes(): ImagenProductoUseCase
    {
        return service('ImagenProductoUseCase');
    }
}
// #####################################

// MODULO REPARTO
if (!function_exists('reparto')) {
    function reparto(): RepartoUseCase
    {
        return service('RepartoUseCase');
    }
}

if (!function_exists('asignador_chats')) {
    function asignador_chats(): AsignadorChatUseCase
    {
        return service('AsignadorChatUseCase');
    }
}

if (!function_exists('grupos_atencion')) {
    function grupos_atencion(): GrupoAtencionUseCase
    {
        return service('GrupoAtencionUseCase');
    }
}
// #####################################

// MODULO AGENTE IA
if (!function_exists('agentes_ia')) {
    function agentes_ia(): AgenteIaUseCase
    {
        return service('AgenteIaUseCase');
    }
}

if (!function_exists('atencion_ia')) {
    function atencion_ia(): AtencionIaUseCase
    {
        return service('AtencionIaUseCase');
    }
}
// #####################################

// MODULO PERMISO
if (!function_exists('grupo_permiso')) {
    function grupo_permiso(): GrupoPermisoUseCase
    {
        return service('GrupoPermisoUseCase');
    }
}

if (!function_exists('permiso')) {
    function permiso(): PermisoUseCase
    {
        return service('PermisoUseCase');
    }
}

if (!function_exists('grupo_usuario_permiso')) {
    function grupo_usuario_permiso(): GrupoUsuarioPermisoUseCase
    {
        return service('GrupoUsuarioPermisoUseCase');
    }
}
// #####################################

// MODULO MODULOS
if (!function_exists('modulo')) {
    function modulo(): ModulosUseCase
    {
        return service('ModulosUseCase');
    }
}
if (!function_exists('modulo_grupo')) {
    function modulo_grupo(): ModuloGrupoUseCase
    {
        return service('ModuloGrupoUseCase');
    }
}

// #####################################

if (!function_exists('media')) {
    function media(): MediaUseCase
    {
        return service('MediaUseCase');
    }
}

if (!function_exists('mensaje')) {
    function mensaje(): MensajeUseCase
    {
        return service('MensajeUseCase');
    }
}

if (!function_exists('plantilla')) {
    function plantilla(): PlantillaMensajeUseCase
    {
        return service('PlantillaMensajeUseCase');
    }
}

if (!function_exists('plantilla_rapida')) {
    function plantilla_rapida(): PlantillaRapidaUseCase
    {
        return service('PlantillaRapidaUseCase');
    }
}

if (!function_exists('conversaciones')) {
    function conversaciones(): ConversacionUseCase
    {
        return service('ConversacionUseCase');
    }
}

if (!function_exists('motivo_conversacion')) {
    function motivo_conversacion(): ConversacionMotivoUseCase
    {
        return service('MotivoConversacionUseCase');
    }
}

if (!function_exists('etiquetas')) {
    function etiquetas(): EtiquetaUseCase
    {
        return service('EtiquetaUseCase');
    }
}

if (!function_exists('conversacion_venta')) {
    function conversacion_venta(): ConversacionVentaUseCase
    {
        return service('ConversacionVentaUseCase');
    }
}

if (!function_exists('webhook')) {
    function webhook(): WebhookUseCase
    {
        return service('WebhookUseCase');
    }
}

if (!function_exists('empresa')) {
    function empresa(): EmpresaUseCase
    {
        return service('EmpresaUseCase');
    }
}

if (!function_exists('app')) {
    function app(): AppUseCase
    {
        return service('AppUseCase');
    }
}

if (!function_exists('flujos')) {
    function flujos(): \Belly\Flujos\UseCases\FlujoUseCase
    {
        return service('FlujoUseCase');
    }
}

if (!function_exists('bot_flujos')) {
    function bot_flujos(): \Belly\Flujos\UseCases\FlujoBotUseCase
    {
        return service('FlujoBotUseCase');
    }
}

if (!function_exists('conexion_whatsapp')) {
    function conexion_whatsapp(): \Belly\Configuracion\UseCases\ConexionWhatsappUseCase
    {
        return service('ConexionWhatsappUseCase');
    }
}

if (!function_exists('portafolio')) {
    function portafolio(): PortafolioUseCase
    {
        return service('PortafolioUseCase');
    }
}

if (!function_exists('crm_ajustes')) {
    function crm_ajustes(): \Belly\Crm\UseCases\AjustesCrmUseCase
    {
        return service('AjustesCrmUseCase');
    }
}

if (!function_exists('crm_clientes')) {
    function crm_clientes(): \Belly\Crm\UseCases\CarteraClientesUseCase
    {
        return service('CarteraClientesUseCase');
    }
}

if (!function_exists('crm_alta')) {
    function crm_alta(): \Belly\Crm\UseCases\AltaClientesUseCase
    {
        return service('AltaClientesUseCase');
    }
}

if (!function_exists('crm_envios')) {
    function crm_envios(): \Belly\Crm\UseCases\EnvioMasivoUseCase
    {
        return service('EnvioMasivoUseCase');
    }
}

if (!function_exists('crm_dashboard')) {
    function crm_dashboard(): \Belly\Crm\UseCases\DashboardCrmUseCase
    {
        return service('DashboardCrmUseCase');
    }
}

if (!function_exists('lista_negra')) {
    function lista_negra(): ListaNegraUseCase
    {
        return service('ListaNegraUseCase');
    }
}

// Funciones helper para validación
if (!function_exists('pretty_phone_number')) {
    /**
     * Valida y formatea un número de teléfono
     * 
     * @param string $telefono
     * @return array ['success' => bool, 'telefono' => string|null, 'message' => string|null]
     */
    function pretty_phone_number($telefono)
    {
        if (empty($telefono)) {
            return [
                'success' => false,
                'telefono' => null,
                'message' => 'El teléfono es obligatorio'
            ];
        }

        // Limpiar el teléfono: eliminar espacios, guiones, paréntesis, etc.
        $telefono_limpio = preg_replace('/[^0-9+]/', '', $telefono);
        
        // Si empieza con +, removerlo
        if (strpos($telefono_limpio, '+') === 0) {
            $telefono_limpio = substr($telefono_limpio, 1);
        }
        
        // Si no empieza con código de país 51, agregarlo para números peruanos
        if (strpos($telefono_limpio, '51') !== 0) {
            // Si tiene 9 dígitos (número peruano sin código de país), agregar 51
            if (strlen($telefono_limpio) == 9) {
                $telefono_limpio = '51' . $telefono_limpio;
            }
        }
        
        // Validar que tenga 11 dígitos (51 + 9 dígitos)
        if (strlen($telefono_limpio) != 11) {
            return [
                'success' => false,
                'telefono' => null,
                'message' => 'El teléfono debe tener 9 dígitos (se guardará con código de país 51)'
            ];
        }
        
        // Devolver el número con código de país 51 (11 dígitos)
        return [
            'success' => true,
            'telefono' => $telefono_limpio,
            'message' => null
        ];
    }
}

if (!function_exists('pretty_placa')) {
    /**
     * Valida y formatea una placa de vehículo (formato peruano)
     * 
     * @param string $placa
     * @return string|null
     */
    function pretty_placa($placa)
    {
        if (empty($placa)) {
            return null;
        }
        
        // Limpiar y convertir a mayúsculas
        $placa_limpia = strtoupper(trim($placa));
        
        // Eliminar espacios, guiones y cualquier carácter especial
        $placa_limpia = preg_replace('/[^A-Z0-9]/', '', $placa_limpia);
        
        // Si después de limpiar está vacía, retornar null
        if (empty($placa_limpia)) {
            return null;
        }
        
        // Validar formato peruano: 3 letras seguidas de 3-4 números (ej: ABC123 o ABC1234)
        if (preg_match('/^[A-Z]{3}[0-9]{3,4}$/', $placa_limpia)) {
            return $placa_limpia;
        }
        
        // Si no coincide con el formato pero tiene contenido, retornar la placa limpia de todos modos
        // (puede ser un formato no estándar pero válido)
        return $placa_limpia;
    }
}

if (!function_exists('mb_url_title')) {
    /**
     * Convierte una cadena a formato URL amigable (similar a url_title de CodeIgniter pero con soporte multibyte)
     * 
     * @param string $str
     * @param string $separator
     * @param bool $lowercase
     * @return string
     */
    function mb_url_title($str, $separator = '-', $lowercase = true)
    {
        if (empty($str)) {
            return '';
        }
        
        if ($lowercase) {
            $str = mb_strtolower($str, 'UTF-8');
        }
        
        // Reemplazar caracteres especiales
        $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
        
        // Reemplazar espacios y múltiples guiones por el separador
        $str = preg_replace('/[\s-]+/', $separator, $str);
        
        // Eliminar separadores al inicio y final
        return trim($str, $separator);
    }
}
