<?php

namespace Belly\Relations;

require_once __DIR__ . '/Relation.php';

class BelongsTo extends Relation
{
    protected $relatedEntity;
    protected $relatedModel;
    protected $foreignKey;
    protected $ownerKey;

    public function __construct($parent, $relatedEntity, $relatedModel, $foreignKey, $ownerKey = 'id')
    {
        parent::__construct($parent, $relatedEntity, $foreignKey, $ownerKey);
        $this->relatedEntity = $relatedEntity;
        $this->relatedModel = $relatedModel;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    /**
     * Obtiene la entidad relacionada (BelongsTo)
     */
    public function getResults()
    {
        $foreignKeyValue = $this->parent->{$this->foreignKey} ?? null;
        if ($foreignKeyValue === null) {
            return null;
        }
        $model = new $this->relatedModel();
        return $model->where($this->ownerKey, $foreignKeyValue)->first();
    }
}
