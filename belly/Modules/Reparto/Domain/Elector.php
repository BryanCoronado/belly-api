<?php

namespace Belly\Reparto\Domain;

/**
 * Elige a quién le toca un chat entre varios asesores, según la estrategia. Sin dependencias del framework.
 *
 * Cada candidato: {id, hoy: chats recibidos hoy, abiertas: chats abiertos ahora, ultima: fecha de su último chat recibido o null}.
 * Con la estrategia «prioridad» importa el orden en que llegan los candidatos.
 */
final class Elector
{
    /**
     * @param list<array{id:int, hoy:int, abiertas:int, ultima:?string}> $candidatos
     * @param int|null $limiteChats nadie con tantos chats abiertos (o más) recibe uno nuevo
     */
    public static function elegir(string $estrategia, array $candidatos, ?int $limiteChats = null): ?int
    {
        if ($limiteChats !== null && $limiteChats > 0) {
            $candidatos = array_values(array_filter($candidatos, static fn (array $c): bool => $c['abiertas'] < $limiteChats));
        }
        if ($candidatos === []) {
            return null;
        }

        if ($estrategia === Estrategia::PRIORIDAD) {
            return $candidatos[0]['id'];
        }

        usort($candidatos, static function (array $a, array $b) use ($estrategia): int {
            $criterios = match ($estrategia) {
                Estrategia::MENOS_CARGA => [$a['abiertas'] <=> $b['abiertas'], $a['hoy'] <=> $b['hoy'], strcmp((string) $a['ultima'], (string) $b['ultima'])],
                Estrategia::ROTATIVO => [strcmp((string) $a['ultima'], (string) $b['ultima']), $a['hoy'] <=> $b['hoy']],
                default => [$a['hoy'] <=> $b['hoy'], strcmp((string) $a['ultima'], (string) $b['ultima'])],
            };
            foreach ($criterios as $orden) {
                if ($orden !== 0) {
                    return $orden;
                }
            }

            return $a['id'] <=> $b['id'];
        });

        return $candidatos[0]['id'];
    }
}
