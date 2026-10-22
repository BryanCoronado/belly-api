<?php

namespace Belly\Reparto\Domain;

/** Lo que el motor de reparto necesita consultar de la empresa (asesores, grupos, agentes). */
interface DirectorioReparto
{
    /** ¿Ese asesor puede recibir chats ahora? */
    public function asesorDisponible(int $idUsuario): bool;

    /**
     * Asesores disponibles de un grupo, en el orden del grupo (null = todos los asesores).
     *
     * @return list<array{id:int, hoy:int, abiertas:int, ultima:?string}>
     */
    public function candidatos(?int $idGrupo): array;

    /** ¿Es una persona activa de la empresa (asesor o administrador)? */
    public function personaActiva(int $idUsuario): bool;

    /** ¿El agente existe, está encendido y la empresa tiene la conexión con la IA lista? */
    public function agenteDisponible(int $idAgente): bool;

    /** Quién recibe el chat si nadie más puede: la persona preferida si está activa o el administrador principal. */
    public function respaldo(?int $preferido): ?int;

    /**
     * Grupos tipo turno con su horario (para el paso «Según turnos del equipo»).
     *
     * @return list<array{id: int, nombre: string, dias: list<int>, desde: string, hasta: string}>
     */
    public function gruposTurno(): array;
}
