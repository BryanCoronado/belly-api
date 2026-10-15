<?php

namespace Belly\Flujos\Services;

use Belly\Flujos\Domain\AccionesConversacion;
use Belly\Flujos\Domain\Asignacion;
use Belly\Flujos\Domain\TiposNodo;

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
        $destino = $this->elegirDestino($pedido, $idActual);

        if ($destino !== null && $destino !== $idActual) {
            try {
                conversaciones()->transferirConversacion($idConversacion, $destino, null, null, false);
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
    private function elegirDestino(array $pedido, int $idActual): ?int
    {
        $modo = (string) ($pedido['modo'] ?? Asignacion::MANTENER);
        if ($modo === Asignacion::MANTENER) {
            return null;
        }

        $limitarA = Asignacion::ids($pedido['equipo'] ?? []);
        if ($modo === Asignacion::ELEGIR) {
            $elegido = (int) ($pedido['asesor_id'] ?? 0);
            if ($elegido <= 0) {
                return null;
            }
            $limitarA = [$elegido];
        }

        $candidatos = $this->candidatos($limitarA);
        if ($candidatos === []) {
            log_message('warning', '[BOT] No hay asesores disponibles para repartir; el chat sigue con quien ya lo tenía.');

            return null;
        }

        if ($modo === Asignacion::ELEGIR) {
            return $candidatos[0]['id'];
        }

        usort($candidatos, static function (array $a, array $b) use ($modo, $idActual): int {
            $criterios = $modo === Asignacion::MENOS_CARGA
                ? [$a['abiertas'] <=> $b['abiertas'], $a['hoy'] <=> $b['hoy']]
                : [$a['hoy'] <=> $b['hoy'], strcmp((string) $a['ultima'], (string) $b['ultima'])];
            foreach ($criterios as $orden) {
                if ($orden !== 0) {
                    return $orden;
                }
            }

            // Si empatan, se prefiere a quien ya tiene el chat para no moverlo sin necesidad.
            return [$b['id'] === $idActual, $a['id']] <=> [$a['id'] === $idActual, $b['id']];
        });

        return $candidatos[0]['id'];
    }

    /**
     * Asesores de la empresa que pueden recibir el chat, con su carga de hoy.
     *
     * @param list<int> $limitarA vacío = todos los asesores de la empresa
     * @return list<array{id:int, hoy:int, abiertas:int, ultima:?string}>
     */
    private function candidatos(array $limitarA): array
    {
        helper('vip2system');
        $idEmpresa = (int) (id_empresa_sesion() ?? 0);
        if ($idEmpresa <= 0) {
            return [];
        }

        $filtro = '';
        $params = [$idEmpresa];
        if ($limitarA !== []) {
            $filtro = ' AND u.id IN (' . implode(',', array_fill(0, count($limitarA), '?')) . ')';
            array_push($params, ...$limitarA);
        }

        $sql = 'SELECT u.id,
                    (SELECT COUNT(*) FROM conversacion c WHERE c.id_usuario = u.id AND c.deleted_at IS NULL AND DATE(c.asignado_at) = CURDATE()) AS hoy,
                    (SELECT COUNT(*) FROM conversacion c WHERE c.id_usuario = u.id AND c.deleted_at IS NULL AND c.id_estado IN (1, 3)) AS abiertas,
                    (SELECT MAX(c.asignado_at) FROM conversacion c WHERE c.id_usuario = u.id AND c.deleted_at IS NULL AND DATE(c.asignado_at) = CURDATE()) AS ultima
                FROM usuario u
                WHERE ' . ConsultaAsesores::condicion() . $filtro;

        return array_map(static fn (array $f): array => [
            'id' => (int) $f['id'],
            'hoy' => (int) $f['hoy'],
            'abiertas' => (int) $f['abiertas'],
            'ultima' => $f['ultima'] !== null ? (string) $f['ultima'] : null,
        ], db_connect()->query($sql, $params)->getResultArray());
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
