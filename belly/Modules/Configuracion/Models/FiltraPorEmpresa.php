<?php

namespace Belly\Configuracion\Models;

/**
 * Limita find/listados del panel a la empresa del usuario logueado.
 * El webhook no tiene sesión, así que esas consultas no se filtran aquí
 * y deben enviar id_empresa de forma explícita.
 */
trait FiltraPorEmpresa
{
    public function builder(?string $table = null)
    {
        $builder = parent::builder($table);

        if ($table !== null && $table !== '' && $table !== $this->table) {
            return $builder;
        }

        helper('vip2system');
        $idEmpresa = id_empresa_sesion();
        if ($idEmpresa === null) {
            return $builder;
        }

        $builder->where($this->table . '.id_empresa', $idEmpresa);

        return $builder;
    }
}
