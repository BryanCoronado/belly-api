<?php

namespace Belly\Entities;

use CodeIgniter\Entity\Entity;

class BaseEntity extends Entity
{
    /**
     * Carga las relaciones especificadas para esta entidad
     * 
     * @param array|string $relations
     * @return $this
     */
    public function load($relations)
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }
        
        return $this->loadRelations($relations, $this);
    }
    
    /**
     * Carga las relaciones de forma recursiva
     * 
     * @param array $relations
     * @param mixed $entity
     * @return mixed
     */
    private function loadRelations($relations, $entity)
    {
        if (empty($relations)) {
            return $entity;
        }
        
        $relation = array_shift($relations);
        
        if (method_exists($entity, $relation)) {
            $entity->{$relation} = $entity->{$relation}()->getResults();
        }
        
        return $this->loadRelations($relations, $entity);
    }
    
    /**
     * Relación Uno a Uno
     */
    protected function hasOne($related, $foreignKey, $localKey = 'id')
    {
        return new \Belly\Relations\HasOne($this, $related, $foreignKey, $localKey);
    }

    /**
     * Relación Uno a Muchos
     */
    protected function hasMany($related, $foreignKey, $localKey = 'id')
    {
        return new \Belly\Relations\HasMany($this, $related, $foreignKey, $localKey);
    }
    
    /**
     * Relación Muchos a Muchos
     */
    protected function belongsToMany($related, $pivotModel, $foreignKey, $relatedKey, $localKey = 'id')
    {
        return new \Belly\Relations\BelongsToMany($this, $related, $pivotModel, $foreignKey, $relatedKey, $localKey);
    }

    /**
     * Relación de pertenencia (BelongsTo)
     */
    protected function belongsTo($relatedEntity, $relatedModel, $foreignKey, $ownerKey = 'id')
    {
        return new \Belly\Relations\BelongsTo($this, $relatedEntity, $relatedModel, $foreignKey, $ownerKey);
    }
}
