<?php

namespace Belly\Configuracion\UseCases;

use Belly\Services\MetaGraph;

/**
 * Cupo de 1.000 mensajes de servicio y lo que Meta ya cobró este mes.
 */
class CostosWhatsappUseCase
{
    private const CUPO = 1000;

    /** Tarifas de lista Meta para Perú (oct 2026), si analytics no trae costo. */
    private const TARIFA_USD = [
        'MARKETING' => 0.0703,
        'UTILITY' => 0.0300,
        'AUTHENTICATION' => 0.0300,
        'AUTHENTICATION_INTERNATIONAL' => 0.0300,
        'SERVICE' => 0.0300,
    ];

    public function __construct(private MetaGraph $meta)
    {
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        $app = conexion_whatsapp()->appVerificada();
        if ($app === null || ! $app->tieneToken()) {
            throw new \DomainException('Conectá WhatsApp para ver los costos de Meta.');
        }

        $zona = new \DateTimeZone((string) (config('App')->appTimezone ?? 'America/Lima'));
        $inicio = new \DateTimeImmutable('first day of this month 00:00:00', $zona);
        $fin = (new \DateTimeImmutable('now', $zona))->modify('+1 day')->setTime(0, 0);

        $local = $this->resumenLocal($inicio, $fin, $app);
        try {
            $meta = $this->desdeMeta(
                (string) $app->whatsapp_business_account_id,
                (string) $app->access_token,
                $inicio,
                $fin,
            );
        } catch (\Throwable $e) {
            log_message('warning', '[COSTOS WA] Meta analytics: ' . $e->getMessage());

            return $local + [
                'aviso' => 'Meta no entregó el detalle. Esto es lo enviado desde Belly este mes (aproximado).',
                'fuente' => 'local',
            ];
        }

        return $meta + [
            'numero' => (string) ($app->telefono ?: $local['numero']),
            'nombre' => (string) ($app->nombre ?? ''),
            'aviso' => null,
            'fuente' => 'meta',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function desdeMeta(string $wabaId, string $token, \DateTimeImmutable $inicio, \DateTimeImmutable $fin): array
    {
        $crudo = $this->meta->analyticsPrecios($wabaId, $token, $inicio->getTimestamp(), $fin->getTimestamp(), 'DAILY');
        $puntos = $crudo['pricing_analytics']['data'][0]['data_points'] ?? [];
        if (! is_array($puntos)) {
            $puntos = [];
        }

        $gratis = 0;
        $cobrados = 0;
        $costo = 0.0;
        $porCategoria = [];
        $porDia = [];

        foreach ($puntos as $punto) {
            if (! is_array($punto)) {
                continue;
            }
            $volumen = (int) ($punto['volume'] ?? $punto['delivered'] ?? 0);
            $precio = (float) ($punto['cost'] ?? 0);
            $tipo = strtoupper((string) ($punto['pricing_type'] ?? ''));
            $categoria = strtoupper((string) ($punto['pricing_category'] ?? 'SERVICE'));
            $fecha = $this->fechaPunto($punto);

            if ($tipo === 'FREE_CUSTOMER_SERVICE' || $tipo === 'FREE_ENTRY_POINT') {
                $gratis += $volumen;
            } elseif ($tipo === 'REGULAR') {
                $cobrados += $volumen;
                if ($precio <= 0) {
                    $precio = $volumen * (self::TARIFA_USD[$categoria] ?? 0.03);
                }
                $costo += $precio;
            }

            $clave = $categoria . ':' . $tipo;
            if (! isset($porCategoria[$clave])) {
                $porCategoria[$clave] = [
                    'categoria' => $this->etiquetaCategoria($categoria),
                    'tipo' => $this->etiquetaTipo($tipo),
                    'volumen' => 0,
                    'costo' => 0.0,
                ];
            }
            $porCategoria[$clave]['volumen'] += $volumen;
            $porCategoria[$clave]['costo'] += $precio;

            if (! isset($porDia[$fecha])) {
                $porDia[$fecha] = ['fecha' => $fecha, 'gratis' => 0, 'cobrados' => 0, 'costo' => 0.0];
            }
            if ($tipo === 'REGULAR') {
                $porDia[$fecha]['cobrados'] += $volumen;
                $porDia[$fecha]['costo'] += $precio;
            } else {
                $porDia[$fecha]['gratis'] += $volumen;
            }
        }

        $usadosGratis = min(self::CUPO, $gratis);
        ksort($porDia);

        return [
            'mes' => $inicio->format('Y-m'),
            'cupo' => self::CUPO,
            'usados_gratis' => $usadosGratis,
            'restantes' => max(0, self::CUPO - $usadosGratis),
            'cobrados' => $cobrados,
            'costo_usd' => round($costo, 4),
            'categorias' => array_values($porCategoria),
            'dias' => array_values($porDia),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenLocal(\DateTimeImmutable $inicio, \DateTimeImmutable $fin, $app): array
    {
        $db = db_connect();
        $idEmpresa = (int) ($app->id_empresa ?? 0);
        $enviados = 0;
        if ($idEmpresa > 0 && $db->tableExists('mensaje')) {
            $enviados = (int) $db->table('mensaje')
                ->where('id_empresa', $idEmpresa)
                ->whereIn('contesta', ['usuario', 'bot'])
                ->whereIn('id_estado', [2, 3, 4])
                ->where('created_at >=', $inicio->format('Y-m-d H:i:s'))
                ->where('created_at <', $fin->format('Y-m-d H:i:s'))
                ->where('deleted_at', null)
                ->countAllResults();
        }

        $usadosGratis = min(self::CUPO, $enviados);
        $cobrados = max(0, $enviados - self::CUPO);

        return [
            'numero' => (string) ($app->telefono ?? ''),
            'nombre' => (string) ($app->nombre ?? ''),
            'mes' => $inicio->format('Y-m'),
            'cupo' => self::CUPO,
            'usados_gratis' => $usadosGratis,
            'restantes' => max(0, self::CUPO - $usadosGratis),
            'cobrados' => $cobrados,
            'costo_usd' => round($cobrados * self::TARIFA_USD['SERVICE'], 4),
            'categorias' => [],
            'dias' => [],
        ];
    }

    /** @param array<string, mixed> $punto */
    private function fechaPunto(array $punto): string
    {
        $ts = (int) ($punto['start'] ?? 0);
        if ($ts > 0) {
            return date('Y-m-d', $ts);
        }

        return date('Y-m-d');
    }

    private function etiquetaCategoria(string $categoria): string
    {
        return match ($categoria) {
            'MARKETING' => 'Marketing',
            'UTILITY' => 'Utilidad',
            'AUTHENTICATION', 'AUTHENTICATION_INTERNATIONAL' => 'Autenticación',
            'SERVICE' => 'Servicio (chat)',
            default => $categoria !== '' ? $categoria : 'Otro',
        };
    }

    private function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            'FREE_CUSTOMER_SERVICE' => 'Cupo gratis',
            'FREE_ENTRY_POINT' => 'Anuncio (gratis)',
            'REGULAR' => 'Cobrado',
            default => $tipo !== '' ? $tipo : '—',
        };
    }
}
