<?php

namespace Belly\Mensajes\UseCases;

class StickerUseCase
{
    /**
     * Tienda de stickers de Giphy (WhatsApp no publica la suya).
     *
     * @return array{lista: list<array{id:string, titulo:string, preview:string, url:string}>, aviso:?string}
     */
    public function listar(string $busqueda, int $offset = 0): array
    {
        $clave = trim((string) env('GIPHY_CLAVE', ''));
        if ($clave === '') {
            return [
                'lista' => [],
                'aviso' => 'Falta GIPHY_CLAVE en el .env. Pide una gratis en developers.giphy.com (API Key) y reinicia PHP.',
            ];
        }

        $params = [
            'api_key' => $clave,
            'limit' => 24,
            'offset' => max(0, $offset),
            'rating' => 'g',
            'bundle' => 'messaging_non_clips',
            'lang' => 'es',
        ];

        $ruta = $busqueda !== ''
            ? 'https://api.giphy.com/v1/stickers/search?' . http_build_query($params + ['q' => $busqueda])
            : 'https://api.giphy.com/v1/stickers/trending?' . http_build_query($params);

        $ch = curl_init($ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $crudo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = is_string($crudo) ? json_decode($crudo, true) : null;
        if ($codigo !== 200 || ! is_array($json)) {
            return ['lista' => [], 'aviso' => 'Giphy no respondió. Revisa la clave.'];
        }

        $lista = [];
        foreach ($json['data'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $imagenes = $item['images'] ?? [];
            $url = $imagenes['original']['webp']
                ?? $imagenes['fixed_width']['webp']
                ?? $imagenes['original']['url']
                ?? '';
            $preview = $imagenes['fixed_width_small']['webp']
                ?? $imagenes['preview_webp']['url']
                ?? $url;
            if ($url === '') {
                continue;
            }
            $lista[] = [
                'id' => (string) ($item['id'] ?? ''),
                'titulo' => (string) ($item['title'] ?? 'Sticker'),
                'preview' => (string) $preview,
                'url' => (string) $url,
            ];
        }

        return ['lista' => $lista, 'aviso' => null];
    }
}
