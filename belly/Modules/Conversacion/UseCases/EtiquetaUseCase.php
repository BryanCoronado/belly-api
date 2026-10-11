<?php

namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;
use Belly\Conversacion\Entities\EtiquetaEntity;
use Belly\Conversacion\Models\EtiquetaModel;

class EtiquetaUseCase
{
    protected ConversacionConfig $config;
    protected ?EtiquetaModel $etiqueta_provider = null;

    public function __construct(ConversacionConfig $config)
    {
        $this->config = $config;
    }

    public function getEtiquetaProvider(): EtiquetaModel
    {
        if ($this->etiqueta_provider !== null) {
            return $this->etiqueta_provider;
        }

        $class_name = $this->config->etiqueta_provider;
        $this->etiqueta_provider = new $class_name();

        return $this->etiqueta_provider;
    }

    /** @return object[] */
    public function listarEtiquetas(): array
    {
        return $this->getEtiquetaProvider()
            ->where('deleted_at', null)
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): EtiquetaEntity
    {
        $nombre = $this->nombre($datos['nombre'] ?? null);
        $modelo = new EtiquetaModel();
        if ($modelo->where('nombre', $nombre)->first() !== null) {
            throw new \Exception('Ya existe una etiqueta con ese nombre.');
        }

        $etiqueta = new EtiquetaEntity();
        $etiqueta->id_empresa = $this->idEmpresa();
        $etiqueta->nombre = $nombre;
        $etiqueta->descripcion = $this->textoOpcional($datos['descripcion'] ?? null, 100);
        $etiqueta->color = $this->textoOpcional($datos['color'] ?? null, 100);

        if (! $modelo->save($etiqueta)) {
            throw new \Exception(json_encode($modelo->errors()));
        }

        $etiqueta->id = (int) $modelo->insertID;

        return $etiqueta;
    }

    /** @param array<string, mixed> $datos */
    public function editar(int $id, array $datos): EtiquetaEntity
    {
        $modelo = new EtiquetaModel();
        $actual = $modelo->find($id);
        if ($actual === null) {
            throw new \Exception('Etiqueta no encontrada');
        }

        if (array_key_exists('nombre', $datos)) {
            $nombre = $this->nombre($datos['nombre']);
            $ocupada = (new EtiquetaModel())->where('nombre', $nombre)->where('id !=', $id)->first();
            if ($ocupada !== null) {
                throw new \Exception('Ya existe una etiqueta con ese nombre.');
            }
            $actual->nombre = $nombre;
        }
        if (array_key_exists('descripcion', $datos)) {
            $actual->descripcion = $this->textoOpcional($datos['descripcion'], 100);
        }
        if (array_key_exists('color', $datos)) {
            $actual->color = $this->textoOpcional($datos['color'], 100);
        }

        if (! $modelo->save($actual)) {
            throw new \Exception(json_encode($modelo->errors()));
        }

        return $actual;
    }

    public function eliminar(int $id): void
    {
        $modelo = $this->getEtiquetaProvider();
        if ($modelo->find($id) === null) {
            throw new \Exception('Etiqueta no encontrada');
        }
        if (! $modelo->delete($id)) {
            throw new \Exception('No se pudo eliminar la etiqueta.');
        }
    }

    private function idEmpresa(): int
    {
        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);
        if ($id <= 0) {
            throw new \Exception('La empresa de la sesión es obligatoria.');
        }

        return $id;
    }

    private function nombre(mixed $valor): string
    {
        $nombre = trim((string) $valor);
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new \Exception('El nombre de la etiqueta es obligatorio y admite hasta 100 caracteres.');
        }

        return $nombre;
    }

    private function textoOpcional(mixed $valor, int $maximo): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        if (mb_strlen($texto) > $maximo) {
            throw new \Exception('El texto supera los ' . $maximo . ' caracteres.');
        }

        return $texto;
    }
}
