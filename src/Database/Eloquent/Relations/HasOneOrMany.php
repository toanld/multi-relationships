<?php

namespace Toanld\Relationship\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

trait HasOneOrMany
{
    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $foreignKey = $this->getForeignKeyName();
            $parentKeyValue = $this->getParentKey();
            //If the foreign key is an array (multi-column relationship), we adjust the query.
            if (is_array($this->foreignKey)) {
                $allParentKeyValuesAreNull = array_unique($parentKeyValue) === [null];

                foreach ($this->foreignKey as $index => $key) {
                    $tmp = explode('.', $key);
                    $key = end($tmp);
                    $fullKey = $this->getRelated()
                            ->getTable().'.'.$key;
                    $this->query->where($fullKey, '=', $parentKeyValue[$index]);

                    if ($allParentKeyValuesAreNull) {
                        $this->query->whereNotNull($fullKey);
                    }
                }
            } else {
                parent::addConstraints();
            }
        }
    }


    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {

        $whereIn = $this->whereInMethod($this->parent, $this->localKey);
        //dd($this->formatWhereValues($this->getKeys($models, $this->localKey)));
        $this->getRelationQuery()->{$whereIn}(
            $this->foreignKey, $this->formatWhereValues($this->getKeys($models, $this->localKey))
        );
    }

    public function formatWhereValues($values){
        $newValues = [];
        if(is_array($values)){
            foreach ($values as $v1){
                if(is_array($v1)){
                    foreach ($v1 as $v2){
                        $valueJson = null;
                        if(!is_array($v2)){
                            $value = intval($v2);
                            if($value <= 0){
                                $valueJson = json_decode($v2,true);
                            }else {
                                $newValues[$value] = $value;
                            }
                        }
                        if(is_array($valueJson)){
                            foreach ($valueJson as $v3){
                                $value = intval($v3);
                                if($value > 0) $newValues[$value] = $value;
                            }
                        }
                    }
                }else{
                    $valueJson = null;
                    $value = intval($v1);
                    if($value <= 0){
                        $valueJson = json_decode($v1,true);
                    }else {
                        $newValues[$value] = $value;
                    }
                    if(is_array($valueJson)){
                        foreach ($valueJson as $v3){
                            $value = intval($v3);
                            if($value > 0) $newValues[$value] = $value;
                        }
                    }
                }
            }
        }
        if(!empty($newValues)) {
            $newValues = array_values($newValues);
            return $newValues;
        }else{
            return $values;
        }

    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string|array                        $key
     *
     * @return string
     *
     * 5.6 - no method \Toanld\Relationships\Database\Eloquent\Relations\HasOneOrMany::whereInMethod
     * added in this commit (5.7.17) https://github.com/illuminate/database/commit/9af300d1c50c9ec526823c1e6548daa3949bf9a9
     */
    protected function whereInMethod(Model $model, $key)
    {
        if (!is_array($key)) {
            return parent::whereInMethod($model, $key);
        }

        $where = collect($key)->filter(function ($key) use ($model) {
            return $model->getKeyName() === last(explode('.', $key))
                && in_array($model->getKeyType(), ['int', 'integer']);
        });

        return $where->count() === count($key) ? 'whereIntegerInRaw' : 'whereIn';
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        if (is_array($this->localKey)) { //Check for multi-columns relationship
            return array_map(function ($k) {
                return $this->parent->getTable().'.'.$k;
            }, $this->localKey);
        } else {
            return $this->parent->getTable().'.'.$this->localKey;
        }
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        $key = $this->getQualifiedForeignKeyName();

        if (is_array($key)) { //Check for multi-columns relationship
            return array_map(function ($k) {
                $segments = explode('.', $k);

                return $segments[count($segments) - 1];
            }, $key);
        } else {
            $segments = explode('.', $key);

            return $segments[count($segments) - 1];
        }
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function save(Model $model)
    {
        $foreignKey = $this->getForeignKeyName();
        $parentKeyValue = $this->getParentKey();

        if (is_array($foreignKey)) { //Check for multi-columns relationship
            foreach ($foreignKey as $index => $key) {
                $model->setAttribute($key, $parentKeyValue[$index]);
            }
        } else {
            $model->setAttribute($foreignKey, $parentKeyValue);
        }

        return $model->save() ? $model : false;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param array $attributes
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $attributes = [])
    {
        return tap($this->related->newInstance($attributes), function ($instance) {
            $foreignKey = $this->getForeignKeyName();
            $parentKeyValue = $this->getParentKey();
            if (is_array($foreignKey)) { //Check for multi-columns relationship
                foreach ($foreignKey as $index => $key) {
                    $instance->setAttribute($key, $parentKeyValue[$index]);
                }
            } else {
                $instance->setAttribute($foreignKey, $parentKeyValue);
            }

            $instance->save();
        });
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Builder $parentQuery
     * @param array|mixed                           $columns
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQueryForSelfRelation(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query->from($query->getModel()
                ->getTable().' as '.$hash = $this->getRelationCountHash());

        $query->getModel()
            ->setTable($hash);
        return $query->select($columns)
            ->whereColumn(
                $this->getQualifiedParentKeyName(),
                '=',
                is_array($this->getForeignKeyName()) ? //Check for multi-columns relationship
                    array_map(function ($k) use ($hash) {
                        return $hash.'.'.$k;
                    }, $this->getForeignKeyName()) : $hash.'.'.$this->getForeignKeyName()
            );
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param array                                    $models
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @param string                                   $relation
     * @param string                                   $type
     *
     * @return array
     */
    protected function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        $dictionary = $this->buildDictionary($results);
        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            //If the foreign key is an array, we know it's a multi-column relationship
            //And we join the values to construct the dictionary key
            $dictKey = null;
            $arrListValue = [];
            $key = $this->formatWhereValues($key);
            //dd($key);
            if(is_array($key)){
                foreach ($key as $value){
                    $value = intval($value);
                    if($value > 0 && isset($dictionary[$value]) && is_null($dictKey)) $dictKey = intval($value);
                    if(isset($dictionary[$value])) $arrListValue[$value] = $this->getRelationValue($dictionary, $value, $type);
                }
            }else{
                $dictKey = $key;
            }
            //dd($dictKey);
            if(!empty($dictKey)) {
                if (isset($dictionary[$dictKey])) {
                    $model->setRelation($relation, $this->getRelationValue($dictionary, $dictKey, $type));
                }
            }
            //dd($arrListValue);
            $model->setRelation($relation . "_list", $arrListValue);
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param \Illuminate\Database\Eloquent\Collection $results
     *
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];

        $foreign = $this->getForeignKeyName();

        // First we will create a dictionary of models keyed by the foreign key of the
        // relationship as this will allow us to quickly access all of the related
        // models without having to do nested looping which will be quite slow.
        foreach ($results as $result) {
            //If the foreign key is an array, we know it's a multi-column relationship...
            if (is_array($foreign)) {
                $dictKeyValues = array_map(function ($k) use ($result) {
                    return $result->{$k};
                }, $foreign);
                //... so we join the values to construct the dictionary key
                $dictionary[implode('-', $dictKeyValues)][] = $result;
            } else {
                $dictionary[$result->{$foreign}][] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * Set the foreign ID for creating a related model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return void
     */
    protected function setForeignAttributesForCreate(Model $model)
    {
        $foreignKey = $this->getForeignKeyName();
        $parentKeyValue = $this->getParentKey();
        if (is_array($foreignKey)) { //Check for multi-columns relationship
            foreach ($foreignKey as $index => $key) {
                $model->setAttribute($key, $parentKeyValue[$index]);
            }
        } else {
            parent::setForeignAttributesForCreate($model);
        }
    }

    /**
     * Add join query constraints for one of many relationships.
     *
     * @param \Illuminate\Database\Eloquent\JoinClause $join
     *
     * @return void
     */
    public function addOneOfManyJoinSubQueryConstraints(JoinClause $join)
    {
        if (is_array($this->foreignKey)) {
            foreach ($this->foreignKey as $key) {
                $join->on($this->qualifySubSelectColumn($key), '=', $this->qualifyRelatedColumn($key));
            }
        } else {
            parent::addOneOfManyJoinSubQueryConstraints($join);
        }
    }
}