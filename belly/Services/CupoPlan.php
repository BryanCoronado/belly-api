<?php

namespace Belly\Services;

use Belly\Configuracion\Entities\PlanEntity;
use Belly\Configuracion\Models\PlanModel;

/**
 * Límites del plan de una empresa. 0 = sin tope.
 */
class CupoPlan
{
    /** @return array<string, mixed> */
    public function deEmpresa(?int $idEmpresa = null): array
    {
        $id = $idEmpresa ?? (int) (id_empresa_sesion() ?? 0);
        $plan = $this->planDe($id);

        return $this->presentar($plan);
    }

    /** @return array{plan: array<string, mixed>, uso: array<string, int>} */
    public function resumen(?int $idEmpresa = null): array
    {
        $id = $idEmpresa ?? (int) (id_empresa_sesion() ?? 0);

        return [
            'plan' => $this->deEmpresa($id),
            'uso' => $this->uso($id),
        ];
    }

    /** @return array<string, int> */
    public function uso(int $idEmpresa): array
    {
        if ($idEmpresa <= 0) {
            return $this->usoVacio();
        }

        $db = db_connect();
        $mes = date('Y-m-01 00:00:00');

        $personas = (int) $db->table('usuario')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->countAllResults();
        $equipos = (int) $db->table('grupo_usuario')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->countAllResults();
        $flujos = $db->tableExists('flujo')
            ? (int) $db->table('flujo')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->countAllResults()
            : 0;
        $conversaciones = $db->tableExists('conversacion')
            ? (int) $db->table('conversacion')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->where('created_at >=', $mes)->countAllResults()
            : 0;
        $envios = $db->tableExists('envio_masivo')
            ? (int) $db->table('envio_masivo')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->where('created_at >=', $mes)->countAllResults()
            : 0;
        $numeros = $db->tableExists('app')
            ? (int) $db->table('app')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->where('phone_number_id !=', '')->countAllResults()
            : 0;

        return [
            'conversaciones' => $conversaciones,
            'equipos' => $equipos,
            'personas' => $personas,
            'flujos' => $flujos,
            'envios' => $envios,
            'numeros' => $numeros,
        ];
    }

    public function exigirPersonas(?int $idEmpresa = null): void
    {
        $this->exigirTope($idEmpresa, 'personas', 'max_personas', 'Este plan no admite más personas. Sube de plan o quita a alguien.');
    }

    public function exigirEquipo(?int $idEmpresa = null): void
    {
        $this->exigirTope($idEmpresa, 'equipos', 'max_equipos', 'Este plan no admite más equipos.');
    }

    public function exigirMiembrosEquipo(int $cantidad, ?int $idEmpresa = null): void
    {
        $plan = $this->deEmpresa($idEmpresa);
        $tope = (int) $plan['max_personas_equipo'];
        if ($tope > 0 && $cantidad > $tope) {
            throw new \DomainException('Este plan admite hasta ' . $tope . ' personas por equipo.');
        }
    }

    public function exigirFlujos(?int $idEmpresa = null): void
    {
        $this->exigirTope($idEmpresa, 'flujos', 'max_flujos', 'Este plan no admite más flujos.');
    }

    public function exigirConversacion(?int $idEmpresa = null): void
    {
        $this->exigirTope($idEmpresa, 'conversaciones', 'max_conversaciones', 'Llegaste al cupo de conversaciones de este mes.');
    }

    public function exigirEnvio(?int $idEmpresa = null): void
    {
        $this->exigirTope($idEmpresa, 'envios', 'max_envios', 'Este plan no admite más envíos este mes.');
    }

    public function exigirNumero(?int $idEmpresa = null): void
    {
        $this->exigirTope($idEmpresa, 'numeros', 'max_numeros', 'Este plan no admite más números de WhatsApp.');
    }

    public function exigirAgenteIa(?int $idEmpresa = null): void
    {
        $plan = $this->deEmpresa($idEmpresa);
        if (! $plan['incluye_agente_ia']) {
            throw new \DomainException('El asistente IA no está en este plan.');
        }
    }

    private function exigirTope(?int $idEmpresa, string $campoUso, string $campoMax, string $mensaje): void
    {
        $id = $idEmpresa ?? (int) (id_empresa_sesion() ?? 0);
        $plan = $this->deEmpresa($id);
        $tope = (int) $plan[$campoMax];
        if ($tope <= 0) {
            return;
        }
        if ($this->uso($id)[$campoUso] >= $tope) {
            throw new \DomainException($mensaje);
        }
    }

    private function planDe(int $idEmpresa): ?PlanEntity
    {
        if ($idEmpresa <= 0 || ! db_connect()->tableExists('plan')) {
            return null;
        }

        $fila = db_connect()->table('empresa')->select('id_plan')->where('id', $idEmpresa)->get()->getRowArray();
        $idPlan = (int) ($fila['id_plan'] ?? 0);
        if ($idPlan <= 0) {
            return (new PlanModel())->where('clave', 'gratis')->first();
        }

        $plan = (new PlanModel())->find($idPlan);

        return $plan instanceof PlanEntity ? $plan : null;
    }

    /** @return array<string, mixed> */
    private function presentar(?PlanEntity $plan): array
    {
        if ($plan === null) {
            return [
                'id' => 0,
                'clave' => 'gratis',
                'nombre' => 'Gratis',
                'descripcion' => '',
                'precio_mensual' => 0,
                'moneda' => 'PEN',
                'max_conversaciones' => 300,
                'max_equipos' => 1,
                'max_personas_equipo' => 2,
                'max_personas' => 3,
                'max_flujos' => 1,
                'max_envios' => 0,
                'max_numeros' => 1,
                'incluye_agente_ia' => false,
                'incluye' => self::incluye(false),
            ];
        }

        $ia = (int) $plan->incluye_agente_ia === 1;

        return [
            'id' => (int) $plan->id,
            'clave' => (string) $plan->clave,
            'nombre' => (string) $plan->nombre,
            'descripcion' => (string) ($plan->descripcion ?? ''),
            'precio_mensual' => (float) $plan->precio_mensual,
            'moneda' => (string) ($plan->moneda ?: 'PEN'),
            'max_conversaciones' => (int) $plan->max_conversaciones,
            'max_equipos' => (int) $plan->max_equipos,
            'max_personas_equipo' => (int) $plan->max_personas_equipo,
            'max_personas' => (int) $plan->max_personas,
            'max_flujos' => (int) $plan->max_flujos,
            'max_envios' => (int) $plan->max_envios,
            'max_numeros' => (int) $plan->max_numeros,
            'incluye_agente_ia' => $ia,
            'incluye' => self::incluye($ia),
        ];
    }

    /** @return list<string> */
    public static function incluye(bool $ia): array
    {
        $lista = [
            'Mi Catálogo: tienda web, banners, carrito y QR',
            'WhatsApp en una sola bandeja',
            'Equipo, chats y clientes',
            'Flujos automáticos',
        ];
        if ($ia) {
            $lista[] = 'Asistente IA';
        }

        return $lista;
    }

    /** @return array<string, int> */
    private function usoVacio(): array
    {
        return [
            'conversaciones' => 0,
            'equipos' => 0,
            'personas' => 0,
            'flujos' => 0,
            'envios' => 0,
            'numeros' => 0,
        ];
    }
}
