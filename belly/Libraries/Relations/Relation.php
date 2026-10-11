<?php

namespace Belly\Relations;

abstract class Relation
{
    protected $parent;
    protected $related;
    protected $foreignKey;
    protected $localKey;
    protected $query;
    
    public function __construct($parent, $related, $foreignKey, $localKey = 'id')
    {
        $this->parent = $parent;
        $this->related = new $related();
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }
    
    abstract public function getResults();
    
    public function where($column, $value, $operator = '=')
    {
        $this->related->where($column, $value, $operator);
        return $this;
    }
    
    public function orderBy($column, $direction = 'ASC')
    {
        $this->related->orderBy($column, $direction);
        return $this;
    }
    
    public function limit($limit)
    {
        $this->related->limit($limit);
        return $this;
    }
}
