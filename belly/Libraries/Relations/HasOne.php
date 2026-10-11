<?php

namespace Belly\Relations;

class HasOne extends Relation
{
    public function getResults()
    {
        if (!isset($this->parent->{$this->localKey})) {
            return null;
        }
        
        return $this->related->where($this->foreignKey, $this->parent->{$this->localKey})
                           ->where('deleted_at', null)
                           ->first();
    }
}
