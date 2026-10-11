<?php

namespace Belly\Relations;

class BelongsToMany extends Relation
{
    protected $pivotTable;
    protected $relatedKey;
    protected $pivotModel;
    
    public function __construct($parent, $related, $pivotModel, $foreignKey, $relatedKey, $localKey = 'id')
    {
        parent::__construct($parent, $related, $foreignKey, $localKey);
        $this->pivotModel = new $pivotModel();
        $this->relatedKey = $relatedKey;
    }
    
    public function getResults()
    {
        if (!isset($this->parent->{$this->localKey})) {
            return [];
        }
        
        $pivotResults = $this->pivotModel->where($this->foreignKey, $this->parent->{$this->localKey})
                                       ->where('deleted_at', null)
                                       ->find();

        if (empty($pivotResults)) {
            return [];
        }
        $relatedIds = $this->extractRelatedIds($pivotResults, $this->relatedKey);

        $relatedIds = array_filter($relatedIds, function($id) {
            return is_int($id) && $id > 0;
        });
        if (count($relatedIds) === 0) {
            return [];
        }
        return $this->related->whereIn('id', $relatedIds)
                           ->where('deleted_at', null)
                           ->findAll();
    }
    
    private function extractRelatedIds($results, $key, $ids = [])
    {
        if (empty($results)) {
            return $ids;
        }
        
        $result = array_shift($results);
        
        if (isset($result->{$key})) {
            $ids[] = $result->{$key};
        }
        
        return $this->extractRelatedIds($results, $key, $ids);
    }
}
