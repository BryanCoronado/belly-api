<?php

namespace Belly\AgenteIa\UseCases;

use Belly\AgenteIa\Domain\Indicaciones;
use Belly\AgenteIa\Domain\ModeloLenguaje;
use Belly\AgenteIa\Services\AgenteRepositorio;
use Belly\AgenteIa\Services\ClienteOpenAI;
use Belly\AgenteIa\Services\RespondedorIa;

/**
 * El asistente de IA atendiendo chats de WhatsApp. Contesta hasta que el cliente pide una persona,
 * se llega al máximo de respuestas, la IA decide pedir ayuda o algo falla; entonces el chat sigue
 * por la salida «Pasar a una persona» del reparto.
 * Todo se llama desde el webhook, por eso recibe la empresa de forma explícita.
 */
class AtencionIaUseCase
{
    /** Evita que dos asistentes se pasen el chat entre ellos sin fin. */
    private const MAX_CADENA = 3;

    private AgenteRepositorio $repositorio;
    private ModeloLenguaje $modelo;

    public function __construct(?AgenteRepositorio $repositorio = null, ?ModeloLenguaje $modelo = null)
    {
        $this->repositorio = $repositorio ?? new AgenteRepositorio();
        $this->modelo = $modelo ?? new ClienteOpenAI();
    }

    /** Usuario con el que figura el asistente como dueño del chat. */
    public function usuarioAsistente(int $idEmpresa): int
    {
        return $this->repositorio->usuarioAsistente($idEmpresa);
    }

    public function atendiendo(int $idConversacion): bool
    {
        return $this->repositorio->sesionActiva($idConversacion) !== null;
    }

    /** El reparto eligió a un asistente: empieza a atender y contesta el mensaje que acaba de llegar. */
    public function iniciar(int $idEmpresa, int $idConversacion, int $idAgente, ?string $nodo, string $texto, int $cadena = 0): void
    {
        $this->repositorio->terminarSesiones($idConversacion, 'terminada');
        $this->repositorio->crearSesion($idEmpresa, $idConversacion, $idAgente, $nodo);
        $this->alRecibirMensaje($idEmpresa, $idConversacion, $texto, $cadena);
    }

    /** @return bool true si el asistente se hizo cargo del mensaje (el bot de flujos no debe contestar) */
    public function alRecibirMensaje(int $idEmpresa, int $idConversacion, string $texto, int $cadena = 0): bool
    {
        $sesion = $this->repositorio->sesionActiva($idConversacion);
        if ($sesion === null) {
            return false;
        }

        $agente = $this->repositorio->agente($idEmpresa, (int) $sesion['id_agente']);
        if ($agente === null || ! $agente['activo'] || $this->repositorio->conexion($idEmpresa) === null) {
            $this->traspasar($idEmpresa, $sesion, null, 'El asistente está apagado o sin conexión con OpenAI', $texto, $cadena);

            return true;
        }

        if (Indicaciones::pideTraspaso($texto, $agente['palabras_traspaso'])) {
            $this->traspasar($idEmpresa, $sesion, $agente, 'El cliente pidió hablar con una persona', $texto, $cadena);

            return true;
        }

        if ((int) $sesion['respuestas'] >= $agente['max_respuestas']) {
            $this->traspasar($idEmpresa, $sesion, $agente, 'El asistente llegó a su máximo de respuestas', $texto, $cadena);

            return true;
        }

        try {
            $respuesta = (new RespondedorIa($this->repositorio, $this->modelo))->responder(
                $idEmpresa,
                $agente,
                $this->charla($idConversacion),
                $this->repositorio->nombreCliente($idConversacion)
            );
        } catch (\Throwable $e) {
            log_message('error', '[IA] conv=' . $idConversacion . ': ' . $e->getMessage());
            $this->traspasar($idEmpresa, $sesion, $agente, 'El asistente no pudo responder: ' . mb_substr($e->getMessage(), 0, 120), $texto, $cadena);

            return true;
        }

        $enviado = $respuesta['texto'] !== null && $this->enviar($idConversacion, $respuesta['texto']);
        $this->repositorio->actualizarSesion((int) $sesion['id'], ['respuestas' => (int) $sesion['respuestas'] + ($enviado ? 1 : 0)]);

        if ($respuesta['traspaso'] !== null) {
            $this->traspasar($idEmpresa, $sesion, $enviado ? null : $agente, $respuesta['traspaso'], $texto, $cadena);
        }

        return true;
    }

    /** Una persona escribió en el chat: el asistente deja de contestar. */
    public function pausarPorAsesor(int $idConversacion): void
    {
        if ($idConversacion > 0) {
            $this->repositorio->terminarSesiones($idConversacion, 'pausada', 'Una persona tomó el chat');
        }
    }

    // ───────────── internos ─────────────

    /**
     * Avisa al cliente (si el agente tiene mensaje de traspaso) y sigue el reparto por la salida «humano».
     *
     * @param array<string, mixed> $sesion
     * @param array<string, mixed>|null $agente null = no enviar aviso al cliente
     */
    private function traspasar(int $idEmpresa, array $sesion, ?array $agente, string $motivo, string $texto, int $cadena): void
    {
        $idConversacion = (int) $sesion['id_conversacion'];
        if ($agente !== null && $agente['mensaje_traspaso'] !== '') {
            $this->enviar($idConversacion, $agente['mensaje_traspaso']);
        }
        $this->repositorio->terminarSesiones($idConversacion, 'derivada', mb_substr($motivo, 0, 200));

        $conversacion = conversaciones()->getConversacionProvider()->find($idConversacion);
        if ($conversacion === null) {
            return;
        }

        $asesorPrevio = conversaciones()->obtenerUltimoAsesorHumanoCliente((int) $conversacion->id_cliente, (int) $conversacion->id_app, $idConversacion);
        $decision = asignador_chats()->decidir($idEmpresa, [
            'recurrente' => $asesorPrevio !== null,
            'asesor_previo' => $asesorPrevio,
            'anuncio' => ($conversacion->registro ?? null) === 'anuncio_meta',
            'texto' => $texto,
        ], $sesion['nodo_reparto'] !== null ? (string) $sesion['nodo_reparto'] : null, 'humano');

        if ($decision['tipo'] === 'agente' && $decision['id_agente'] !== (int) $sesion['id_agente'] && $cadena < self::MAX_CADENA) {
            $this->iniciar($idEmpresa, $idConversacion, (int) $decision['id_agente'], $decision['nodo'], $texto, $cadena + 1);

            return;
        }

        $destino = $decision['tipo'] === 'asesor' ? $decision['id_usuario'] : asignador_chats()->respaldo($idEmpresa);
        if ($destino === null) {
            log_message('warning', '[IA] conv=' . $idConversacion . ' sin persona disponible para recibir el chat; queda con el asistente.');

            return;
        }

        try {
            conversaciones()->transferirConversacion($idConversacion, $destino, null, null);
        } catch (\Throwable $e) {
            log_message('error', '[IA] No se pudo pasar la conversación ' . $idConversacion . ' a ' . $destino . ': ' . $e->getMessage());
        }
    }

    /** @return list<array{rol: string, texto: string}> */
    private function charla(int $idConversacion): array
    {
        $lineas = [];
        foreach ($this->repositorio->historial($idConversacion, Indicaciones::MAX_HISTORIAL) as $m) {
            $texto = trim((string) $m['mensaje']);
            $tipo = (string) ($m['media_type'] ?? 'text');
            if ($tipo !== 'text' && $tipo !== '') {
                $texto = trim('[Envió ' . $this->nombreAdjunto($tipo) . '] ' . $texto);
            }
            if ($texto !== '') {
                $lineas[] = ['rol' => $m['contesta'] === 'cliente' ? 'cliente' : 'asistente', 'texto' => $texto];
            }
        }

        return $lineas;
    }

    private function nombreAdjunto(string $tipo): string
    {
        return match ($tipo) {
            'image' => 'una imagen',
            'audio' => 'un audio',
            'video' => 'un video',
            'document' => 'un documento',
            'location' => 'una ubicación',
            'sticker' => 'un sticker',
            default => 'un adjunto',
        };
    }

    private function enviar(int $idConversacion, string $texto): bool
    {
        $resultado = mensaje()->crearMensajeYEnviarWhatsapp([
            'id_conversacion' => $idConversacion,
            'contesta' => 'bot',
            'media_type' => 'text',
            'mensaje' => $texto,
        ]);
        if (! ($resultado['success'] ?? false)) {
            log_message('error', '[IA] No se pudo enviar la respuesta conv=' . $idConversacion . ': ' . ($resultado['error'] ?? 'sin detalle'));

            return false;
        }

        return true;
    }
}
