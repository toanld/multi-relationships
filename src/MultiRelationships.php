<?php

namespace Toanld\Relationship;

use Illuminate\Support\Str;
use Toanld\Relationship\Database\Eloquent\Concerns\HasRelationships;
use Toanld\Relationship\Database\Query\Builder as QueryBuilder;
trait MultiRelationships
{
    use HasRelationships;
    public function getAttribute($key)
    {
        if (is_array($key)) { //Check for multi-columns relationship
            return array_map(function ($k) {
                return parent::getAttribute($k);
            }, $key);
        }
        return parent::getAttribute($key);
    }

    public function qualifyColumn($column)
    {
        if (is_array($column)) { //Check for multi-column relationship
            return array_map(function ($c) {
                if (Str::contains($c, '.')) {
                    return $c;
                }

                return $this->getTable().'.'.$c;
            }, $column);
        }
        return parent::qualifyColumn($column);
    }

    /**
     * Configure Eloquent to use Relationships Query Builder.
     *
     * @return \Toanld\Relationships\Query\Builder|static
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
    }

    /**
     * Get value relationship.
     *
     * @return Model
     */
    public function getRelationshipValue($id,$relation_name){
        $data = $this->{$relation_name . "_list"};
        return isset($data[$id]) ? $data[$id] : null;
    }
}
