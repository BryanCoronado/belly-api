<?php

namespace Belly\Catalogo\Controllers;

use App\Controllers\BaseController;
use Belly\Config\Plataforma;

class CatalogoSeoController extends BaseController
{
    public function pagina(string $slug, $idProducto = null)
    {
        try {
            $tarjeta = catalogo_vitrina()->tarjeta($slug, $idProducto !== null && $idProducto !== '' ? (int) $idProducto : null);
        } catch (\DomainException $e) {
            return $this->response->setStatusCode(404)->setBody($this->htmlError($e->getMessage()));
        } catch (\Throwable $e) {
            log_message('error', '[CATALOGO] ' . $e->getMessage());

            return $this->response->setStatusCode(400)->setBody($this->htmlError('No se pudo abrir el catálogo.'));
        }

        $front = rtrim(config(Plataforma::class)->urlTienda(), '/');
        $destino = $front !== '' ? $tarjeta['url'] : '';

        return $this->response
            ->setHeader('Content-Type', 'text/html; charset=UTF-8')
            ->setBody($this->htmlTarjeta($tarjeta, $destino));
    }

    /** @param array<string, mixed> $tarjeta */
    private function htmlTarjeta(array $tarjeta, string $destino): string
    {
        $titulo = esc((string) $tarjeta['titulo']);
        $descripcion = esc((string) $tarjeta['descripcion']);
        $imagen = esc((string) ($tarjeta['imagen_url'] ?? ''));
        $logo = esc((string) ($tarjeta['logo_url'] ?? $tarjeta['imagen_url'] ?? ''));
        $url = esc((string) $tarjeta['url']);
        $color = esc((string) $tarjeta['color']);
        $ir = $destino !== '' ? esc($destino) : $url;

        $json = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => $tarjeta['titulo'],
            'description' => $tarjeta['descripcion'],
            'url' => $tarjeta['url'],
            'image' => $tarjeta['imagen_url'],
            'logo' => $tarjeta['logo_url'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '<!doctype html><html lang="es"><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $titulo . '</title>'
            . '<meta name="description" content="' . $descripcion . '">'
            . '<meta name="theme-color" content="' . $color . '">'
            . ($logo !== '' ? '<link rel="icon" href="' . $logo . '"><link rel="apple-touch-icon" href="' . $logo . '">' : '')
            . '<meta property="og:type" content="website">'
            . '<meta property="og:locale" content="es_PE">'
            . '<meta property="og:title" content="' . $titulo . '">'
            . '<meta property="og:description" content="' . $descripcion . '">'
            . ($imagen !== '' ? '<meta property="og:image" content="' . $imagen . '">' : '')
            . '<meta property="og:url" content="' . $url . '">'
            . '<meta name="twitter:card" content="summary_large_image">'
            . '<meta name="twitter:title" content="' . $titulo . '">'
            . '<meta name="twitter:description" content="' . $descripcion . '">'
            . ($imagen !== '' ? '<meta name="twitter:image" content="' . $imagen . '">' : '')
            . '<link rel="canonical" href="' . $url . '">'
            . '<script type="application/ld+json">' . $json . '</script>'
            . '</head><body style="font-family:system-ui,sans-serif;margin:0;background:#F7F6F2;color:#0B1026">'
            . '<main style="max-width:28rem;margin:4rem auto;padding:1.5rem;text-align:center">'
            . ($logo !== '' ? '<img src="' . $logo . '" alt="" width="72" height="72" style="border-radius:16px;object-fit:cover">' : '')
            . '<h1 style="font-size:1.5rem">' . $titulo . '</h1>'
            . '<p>' . $descripcion . '</p>'
            . '<p><a href="' . $ir . '">Entrar a la tienda</a></p>'
            . '</main>'
            . ($destino !== '' ? '<script>location.replace(' . json_encode($destino) . ')</script>' : '')
            . '</body></html>';
    }

    private function htmlError(string $mensaje): string
    {
        $texto = esc($mensaje);

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' . $texto . '</title></head>'
            . '<body style="font-family:system-ui,sans-serif;padding:3rem;text-align:center"><p>' . $texto . '</p></body></html>';
    }
}
