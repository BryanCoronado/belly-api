<?php

namespace Belly\Relations;

class HasMany extends Relation
{
    public function getResults()
    {
        if (!isset($this->parent->{$this->localKey})) {
            return [];
        }
        
        return $this->related->where($this->foreignKey, $this->parent->{$this->localKey})
                           ->where('deleted_at', null)
                           ->findAll();
    }
}
