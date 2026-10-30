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

        $ahora = (new \DateTimeImmutable('now', $zona))->format(DATE_ATOM);
        $meta = $this->desdeMeta(
            (string) $app->whatsapp_business_account_id,
            (string) $app->access_token,
            $inicio,
            $fin,
        );

        $cerrado = $this->cerrarResumen($meta + [
            'numero' => (string) ($app->telefono ?? ''),
            'nombre' => (string) ($app->nombre ?? ''),
            'aviso' => 'Meta publica este resumen con retraso (a veces horas). Belly no suma por su cuenta: si borramos chats, el cupo no cambia.',
            'fuente' => 'meta',
            'actualizado_at' => $ahora,
            'modo_ahorro' => empresa()->modoAhorro(),
        ], $inicio);

        $this->guardarCache($cerrado);

        return $cerrado;
    }

    /**
     * En modo ahorro, si el cupo de Meta ya se acabó, el bot y la IA no mandan más.
     * Si Meta no responde, no frenamos: mejor un mensaje de más que un CRM mudo.
     */
    public function automaticoDebeCallar(?int $idEmpresa = null): bool
    {
        if (! empresa()->modoAhorro($idEmpresa)) {
            return false;
        }

        try {
            $resumen = $this->resumenCacheado();

            return (int) ($resumen['restantes'] ?? 1) <= 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function resumenCacheado(): array
    {
        $clave = $this->claveCache();
        $guardado = cache()->get($clave);
        if (is_array($guardado) && isset($guardado['restantes'])) {
            return $guardado;
        }

        return $this->resumen();
    }

    /** @param array<string, mixed> $resumen */
    private function guardarCache(array $resumen): void
    {
        cache()->save($this->claveCache(), $resumen, 300);
    }

    private function claveCache(): string
    {
        return 'wa_cupo_' . (int) (id_empresa_sesion() ?? 0);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function cerrarResumen(array $datos, \DateTimeImmutable $inicio): array
    {
        $datos['usados_gratis'] = min(self::CUPO, (int) ($datos['usados_gratis'] ?? 0));
        $datos['restantes'] = max(0, self::CUPO - (int) $datos['usados_gratis']);
        $datos['dias'] = $this->completarDias($inicio, is_array($datos['dias'] ?? null) ? $datos['dias'] : []);

        return $datos;
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
     * @param list<array{fecha:string,gratis:int,cobrados:int,costo:float}> $dias
     * @return list<array{fecha:string,gratis:int,cobrados:int,costo:float}>
     */
    private function completarDias(\DateTimeImmutable $inicio, array $dias): array
    {
        $porFecha = [];
        foreach ($dias as $dia) {
            $porFecha[$dia['fecha']] = $dia;
        }
        $hoy = new \DateTimeImmutable('today', $inicio->getTimezone());
        $cursor = $inicio;
        $completo = [];
        while ($cursor <= $hoy) {
            $clave = $cursor->format('Y-m-d');
            $completo[] = $porFecha[$clave] ?? ['fecha' => $clave, 'gratis' => 0, 'cobrados' => 0, 'costo' => 0.0];
            $cursor = $cursor->modify('+1 day');
        }

        return $completo;
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
