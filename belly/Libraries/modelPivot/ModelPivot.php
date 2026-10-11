<?php

namespace Belly\ModelPivot;

use CodeIgniter\Model;
use Config\Feature;

class ModelPivot extends Model {

    protected $foreignKey = [];
    protected $pivotFields = [];

    protected function getForeignKeys(): array
    {
        return array_keys($this->foreignKey);
    }

    protected function getAllowedFields(): array
    {
        $foreignKeys = array_keys($this->foreignKey);
        $allowedFields = array_merge($foreignKeys, $this->pivotFields);
        
        // También incluir los campos definidos en $allowedFields del modelo
        if (!empty($this->allowedFields)) {
            $allowedFields = array_merge($allowedFields, $this->allowedFields);
        }
        
        return array_unique($allowedFields);
    }

    public function savePivot($data)
    {
        if (is_object($data)) {
            $data = $this->entityToArray($data);
        } elseif (!is_array($data)) {
            throw new \InvalidArgumentException('Los datos deben ser un array o una entidad');
        }

        if (empty($this->foreignKey)) {
            throw new \InvalidArgumentException('Las claves foráneas deben estar definidas en $foreignKey');
        }

        $pivotKeys = $this->getForeignKeys();
        
        foreach ($pivotKeys as $key) {
            if (!isset($data[$key])) {
                throw new \InvalidArgumentException("La clave pivot '$key' es requerida");
            }
        }

        if ($this->pivotExists($data)) {
            return $this->updatePivot($data);
        } else {
            return $this->insertPivot($data);
        }
    }

    protected function pivotExists(array $data): bool
    {
        $builder = $this->builder();
        
        $pivotKeys = $this->getForeignKeys();
        
        foreach ($pivotKeys as $key) {
            $builder->where($key, $data[$key]);
        }

        if ($this->useSoftDeletes) {
            $builder->where($this->deletedField, null);
        }

        return $builder->countAllResults() > 0;
    }

    protected function insertPivot(array $data)
    {
        $allowedData = $this->filterPivotData($data);

        if ($this->useTimestamps) {
            $now = $this->setDate();
            
            if ($this->createdField !== '') {
                $allowedData[$this->createdField] = $now;
            }
            
            if ($this->updatedField !== '') {
                $allowedData[$this->updatedField] = $now;
            }
        }

        $builder = $this->builder();
        
        $originalCasts = $this->casts;
        $this->casts = [];
        
        $result = $builder->insert($allowedData);
        
        $this->casts = $originalCasts;
        
        return $result;
    }

    protected function updatePivot(array $data)
    {
        $allowedData = $this->filterPivotData($data, true);
        
        if ($this->useTimestamps && $this->updatedField !== '') {
            $allowedData[$this->updatedField] = $this->setDate();
        }

        $builder = $this->builder();
        $pivotKeys = $this->getForeignKeys();
        
        foreach ($pivotKeys as $key) {
            $builder->where($key, $data[$key]);
        }

        if ($this->useSoftDeletes) {
            $builder->where($this->deletedField, null);
        }

        $originalCasts = $this->casts;
        $this->casts = [];
        
        $result = $builder->update($allowedData);
        
        $this->casts = $originalCasts;
        
        return $result;
    }

    public function findPivot(array $pivotData)
    {
        if (empty($this->foreignKey)) {
            throw new \InvalidArgumentException('Las claves foráneas deben estar definidas en $foreignKey');
        }

        $builder = $this->builder();
        $pivotKeys = $this->getForeignKeys();
        
        foreach ($pivotKeys as $key) {
            if (!isset($pivotData[$key])) {
                throw new \InvalidArgumentException("La clave pivot '$key' es requerida");
            }
            $builder->where($key, $pivotData[$key]);
        }

        if ($this->useSoftDeletes) {
            $builder->where($this->deletedField, null);
        }

        $result = $builder->get()->getResult($this->tempReturnType);
        
        if (!empty($result)) {
            $result = $this->doFindForeignKey($this->useCasts(), $this->tempReturnType, $result);
            return $result[0] ?? null;
        }

        return null;
    }

    protected function filterPivotData(array $data, bool $isUpdate = false): array
    {
        $pivotKeys = $this->getForeignKeys();
        $allowedFields = $this->getAllowedFields();
        
        if ($isUpdate) {
            $allowedFields = array_diff($allowedFields, $pivotKeys);
        }

        $filteredData = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $filteredData[$key] = $value;
            }
        }

        return $filteredData;
    }

    public function deletePivot(array $pivotData, bool $purge = false): bool
    {
        if (empty($this->foreignKey)) {
            throw new \InvalidArgumentException('Las claves foráneas deben estar definidas en $foreignKey');
        }

        $builder = $this->builder();
        $pivotKeys = $this->getForeignKeys();
        
        foreach ($pivotKeys as $key) {
            if (!isset($pivotData[$key])) {
                throw new \InvalidArgumentException("La clave pivot '$key' es requerida para eliminar");
            }
            $builder->where($key, $pivotData[$key]);
        }

        if ($this->useSoftDeletes && !$purge) {
            $updateData = [$this->deletedField => $this->setDate()];
            
            if ($this->useTimestamps && $this->updatedField !== '') {
                $updateData[$this->updatedField] = $this->setDate();
            }
            
            // Desactivar temporalmente los casts para evitar problemas con timestamps
            $originalCasts = $this->casts;
            $this->casts = [];
            
            $result = $builder->update($updateData);
            
            // Restaurar los casts originales
            $this->casts = $originalCasts;
            
            return $result;
        } else {
            return $builder->delete();
        }
    }

    protected function doFindAll(?int $limit = null, int $offset = 0) {
        $limitZeroAsAll = config(Feature::class)->limitZeroAsAll ?? true;
        if ($limitZeroAsAll) {
            $limit ??= 0;
        }

        $builder = $this->builder();

        $useCast = $this->useCasts();
        if ($useCast) {
            $returnType = $this->tempReturnType;
            $this->asArray();
        }

        if ($this->tempUseSoftDeletes) {
            $builder->where($this->table . '.' . $this->deletedField, null);
        }

        $results = $builder->limit($limit, $offset)
                ->get()
                ->getResult($this->tempReturnType);

        return $this->doFindForeignKey($useCast, $returnType, $results);
    }

    protected function doFindForeignKey(bool $useCast, string $returnType, ?array $results = []) {
        if (isset($this->foreignKey) && !empty($this->foreignKey)) {
            foreach ($this->foreignKey as $key => $foreign) {
                $ids = array_unique(array_column($results, $key));

                $model = new $foreign['model']();
                $this->foreignKey[$key]['results'] = $model->find($ids);
            }
        }

        if ($useCast) {
            foreach ($results as $i => $row) {
                if (is_array($row)) {
                    $results[$i] = $this->convertToReturnType($row, $returnType);
                } else {
                    $results[$i] = $row;
                }

                //$foreign['table'] es obligatorio
                foreach ($this->foreignKey as $key => $foreign) {
                    $table = $foreign['table'];
                    $find = $results[$i]->$key;

                    $data = array_filter($foreign['results'], function ($entity) use ($find) {
                        return $entity->id === $find;
                    });

                    $results[$i]->$table = array_values($data)[0] ?? null;
                }
            }

            $this->tempReturnType = $returnType;
        }

        return $results;
    }
    
    protected function entityToArray($entity): array
    {
        if (method_exists($entity, 'toArray')) {
            return $entity->toArray();
        }
        
        if (is_object($entity)) {
            $reflection = new \ReflectionClass($entity);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            
            $data = [];
            foreach ($properties as $property) {
                $data[$property->getName()] = $property->getValue($entity);
            }
            
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                $methodName = $method->getName();
                if (strpos($methodName, 'get') === 0 && strlen($methodName) > 3) {
                    $propertyName = lcfirst(substr($methodName, 3));
                    if (!isset($data[$propertyName])) {
                        try {
                            $data[$propertyName] = $method->invoke($entity);
                        } catch (\Exception $e) {
                            // Ignorar propiedades que no se pueden acceder
                        }
                    }
                }
            }
            
            return $data;
        }
        
        throw new \InvalidArgumentException('No se pudo convertir la entidad a array');
    }
    
    /** RELACIONES TIPO LARAVEL :D ! */
    
    protected function hasOne($related, $foreignKey, $localKey = 'id')
    {
        return new \Belly\Relations\HasOne($this, $related, $foreignKey, $localKey);
    }

    protected function hasMany($related, $foreignKey, $localKey = 'id')
    {
        return new \Belly\Relations\HasMany($this, $related, $foreignKey, $localKey);
    }
    
    protected function belongsToMany($related, $pivotModel, $foreignKey, $relatedKey, $localKey = 'id')
    {
        return new \Belly\Relations\BelongsToMany($this, $related, $pivotModel, $foreignKey, $relatedKey, $localKey);
    }
    
    public function load($relations)
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }
        
        // $this se refiere a la entidad, no al modelo
        return $this->loadRelations($relations, $this);
    }
    
    private function loadRelations($relations, $model)
    {
        if (empty($relations)) {
            return $model;
        }
        
        $relation = array_shift($relations);
        
        if (method_exists($model, $relation)) {
            $model->{$relation} = $model->{$relation}()->getResults();
        }
        
        return $this->loadRelations($relations, $model);
    }
    
    public function loadCollection($collection, $relations)
    {
        if (empty($collection)) {
            return $collection;
        }
        
        return $this->loadCollectionRecursive($collection, $relations, 0);
    }
    
    private function loadCollectionRecursive($collection, $relations, $index)
    {
        if ($index >= count($collection)) {
            return $collection;
        }
        
        $model = $collection[$index];
        
        if (is_string($relations)) {
            $relations = [$relations];
        }
        
        foreach ($relations as $relation) {
            if (method_exists($model, $relation)) {
                $model->{$relation} = $model->{$relation}()->getResults();
            }
        }
        
        return $this->loadCollectionRecursive($collection, $relations, $index + 1);
    }

}
