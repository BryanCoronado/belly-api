<?php

namespace Belly\Flujos\Services;

use Belly\Flujos\Domain\AccionesConversacion;
use Belly\Flujos\Domain\Asignacion;
use Belly\Flujos\Domain\TiposNodo;
use Belly\Usuarios\Services\ConsultaAsesores;

/**
 * Aplica en el CRM lo que decide el bot: pasar el chat a un asesor y marcar el interés del contacto.
 * Reutiliza la transferencia de conversaciones que ya usa el equipo, así el asesor recibe el aviso en tiempo real.
 */
class AccionesCrm implements AccionesConversacion
{
    public function asignar(int $idConversacion, array $pedido): ?string
    {
        $conversacion = conversaciones()->getConversacionProvider()->find($idConversacion);
        if ($conversacion === null) {
            return null;
        }

        $idActual = (int) $conversacion->id_usuario;
        $destino = $this->elegirDestino($pedido);

        if ($destino !== null && $destino !== $idActual) {
            try {
                conversaciones()->transferirConversacion($idConversacion, $destino, null, null);
                $idActual = $destino;
            } catch (\Throwable $e) {
                log_message('error', '[BOT] No se pudo pasar la conversación ' . $idConversacion . ' al asesor ' . $destino . ': ' . $e->getMessage());
            }
        }

        return $this->nombreDe($idActual);
    }

    public function calificar(int $idConversacion, string $temperatura): void
    {
        if (in_array($temperatura, TiposNodo::TEMPERATURAS, true)) {
            conversaciones()->aplicarTemperaturaConversacion($idConversacion, $temperatura, true);
        }
    }

    // ───────────────────────── internos ─────────────────────────

    /** @return int|null null = no cambiar de asesor */
    private function elegirDestino(array $pedido): ?int
    {
        $elegido = (int) ($pedido['asesor_id'] ?? 0);
        if (($pedido['modo'] ?? null) !== Asignacion::ELEGIR || $elegido <= 0) {
            return null;
        }
        if (! $this->esAsesorDisponible($elegido)) {
            log_message('warning', '[BOT] El asesor ' . $elegido . ' no está disponible; el chat sigue con quien ya lo tenía.');

            return null;
        }

        return $elegido;
    }

    private function esAsesorDisponible(int $idUsuario): bool
    {
        helper('vip2system');
        $idEmpresa = (int) (id_empresa_sesion() ?? 0);
        if ($idEmpresa <= 0) {
            return false;
        }

        $sql = 'SELECT 1 FROM usuario u WHERE ' . ConsultaAsesores::condicion() . ' AND u.id = ? LIMIT 1';

        return db_connect()->query($sql, [$idEmpresa, $idUsuario])->getRowArray() !== null;
    }

    /** Primer nombre del asesor, para decirle al cliente quién lo atiende. */
    private function nombreDe(int $idUsuario): ?string
    {
        if ($idUsuario <= 0) {
            return null;
        }

        $fila = db_connect()->table('usuario u')
            ->select('u.usuario, p.nombres')
            ->join('usuario_perfil p', 'p.id_usuario = u.id AND p.deleted_at IS NULL', 'left')
            ->where('u.id', $idUsuario)
            ->get()->getRowArray();
        if (! $fila) {
            return null;
        }

        $nombre = trim((string) explode(' ', trim((string) ($fila['nombres'] ?? '')))[0]);

        return $nombre !== '' ? $nombre : (trim((string) ($fila['usuario'] ?? '')) ?: null);
    }
}
