<?php

namespace Belly\Conversacion\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Fila de reporte leads por asesor: mismo {@see total_conversaciones} repartido en varias dimensiones solo para visualización.
 *
 * @property int         $id_usuario
 * @property string|null $nombres
 * @property string|null $apellidos
 * @property int         $total_conversaciones
 * @property array       $para_graficos
 */
class ReporteLeadsPorAsesorItemEntity extends Entity
{
    protected $casts = [
        'id_usuario' => 'integer',
        'total_conversaciones' => 'integer',
    ];

    protected $datamap = [];

    /**
     * @param array<string, mixed> $para_graficos
     */
    public static function desdeAgregadosSql(
        object $filaSql,
        array $seriesEtiquetas,
        int $totalConversaciones
    ): self {
        $pct = static function (int $parte, int $total): float {
            return $total > 0 ? round(100 * $parte / $total, 2) : 0.0;
        };

        $seriesEstado = [
            [
                'codigo' => 'en_atencion',
                'nombre' => 'En atención',
                'id_estado' => 1,
                'cantidad' => (int) $filaSql->cnt_estado_1_en_atencion,
                'porcentaje' => $pct((int) $filaSql->cnt_estado_1_en_atencion, $totalConversaciones),
            ],
            [
                'codigo' => 'finalizado',
                'nombre' => 'Finalizado',
                'id_estado' => 2,
                'cantidad' => (int) $filaSql->cnt_estado_2_finalizado,
                'porcentaje' => $pct((int) $filaSql->cnt_estado_2_finalizado, $totalConversaciones),
            ],
            [
                'codigo' => 'sin_abrir',
                'nombre' => 'Sin abrir',
                'id_estado' => 3,
                'cantidad' => (int) $filaSql->cnt_estado_3_sin_abrir,
                'porcentaje' => $pct((int) $filaSql->cnt_estado_3_sin_abrir, $totalConversaciones),
            ],
            [
                'codigo' => 'otros_estados',
                'nombre' => 'Otros estados',
                'id_estado' => null,
                'cantidad' => (int) $filaSql->cnt_estado_otros,
                'porcentaje' => $pct((int) $filaSql->cnt_estado_otros, $totalConversaciones),
            ],
        ];

        $seriesTemp = [
            [
                'codigo' => 'frio',
                'nombre' => 'Frío',
                'cantidad' => (int) $filaSql->cnt_temp_frio,
                'porcentaje' => $pct((int) $filaSql->cnt_temp_frio, $totalConversaciones),
            ],
            [
                'codigo' => 'tibio',
                'nombre' => 'Tibio',
                'cantidad' => (int) $filaSql->cnt_temp_tibio,
                'porcentaje' => $pct((int) $filaSql->cnt_temp_tibio, $totalConversaciones),
            ],
            [
                'codigo' => 'caliente',
                'nombre' => 'Caliente',
                'cantidad' => (int) $filaSql->cnt_temp_caliente,
                'porcentaje' => $pct((int) $filaSql->cnt_temp_caliente, $totalConversaciones),
            ],
            [
                'codigo' => 'venta',
                'nombre' => 'Venta',
                'cantidad' => (int) $filaSql->cnt_temp_venta,
                'porcentaje' => $pct((int) $filaSql->cnt_temp_venta, $totalConversaciones),
            ],
        ];

        $seriesEtiquetaConPct = [];
        foreach ($seriesEtiquetas as $punto) {
            $c = (int) ($punto['cantidad'] ?? 0);
            $seriesEtiquetaConPct[] = [
                'id_etiqueta' => $punto['id_etiqueta'] ?? null,
                'nombre' => $punto['nombre'] ?? '',
                'cantidad' => $c,
                'porcentaje' => $pct($c, $totalConversaciones),
            ];
        }

        $paraGraficos = [
            'estado_conversacion' => [
                'dimension' => 'estado_conversacion',
                'titulo' => 'Distribución por estado (misma base que total_conversaciones)',
                'total_base' => $totalConversaciones,
                'series' => $seriesEstado,
            ],
            'temperatura' => [
                'dimension' => 'temperatura',
                'titulo' => 'Distribución por temperatura: frío, tibio, caliente, venta (misma base)',
                'total_base' => $totalConversaciones,
                'series' => $seriesTemp,
            ],
            'etiqueta' => [
                'dimension' => 'etiqueta',
                'titulo' => 'Distribución por etiqueta (misma base)',
                'total_base' => $totalConversaciones,
                'series' => $seriesEtiquetaConPct,
            ],
        ];

        return new self([
            'id_usuario' => (int) $filaSql->id_usuario,
            'nombres' => $filaSql->nombres,
            'apellidos' => $filaSql->apellidos,
            'total_conversaciones' => $totalConversaciones,
            'para_graficos' => $paraGraficos,
        ]);
    }
}
