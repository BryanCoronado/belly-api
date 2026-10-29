<?php

namespace Belly\Crm\Catalogo;

/**
 * Valores iniciales del embudo, tipos de cliente y motivos de cierre.
 * Los usa el seeder de demo y el alta automática la primera vez que una empresa abre el CRM.
 */
final class CatalogoCrm
{
    /** @return list<array{0:string,1:string,2:string}> */
    public static function etapas(): array
    {
        return [
            ['Nuevo', '#64748b', 'abierta'],
            ['Contactado', '#0ea5e9', 'abierta'],
            ['Interesado', '#8b5cf6', 'abierta'],
            ['Negociación', '#f59e0b', 'abierta'],
            ['Ganado', '#10b981', 'ganada'],
            ['Perdido', '#f43f5e', 'perdida'],
        ];
    }

    /** @return list<array{0:string,1:string}> */
    public static function tipos(): array
    {
        return [
            ['Prospecto', '#64748b'],
            ['Cliente', '#0ea5e9'],
            ['Frecuente', '#10b981'],
            ['VIP', '#f59e0b'],
        ];
    }

    /** @return list<array{0:string,1:string}> */
    public static function motivos(): array
    {
        return [
            ['Venta cerrada', '#10b981'],
            ['No le interesó', '#f43f5e'],
            ['Precio', '#f59e0b'],
            ['No respondió', '#64748b'],
            ['Volver a contactar', '#0ea5e9'],
            ['Gestionado por otro medio', '#8b5cf6'],
            ['Otro', '#94a3b8'],
        ];
    }
}
