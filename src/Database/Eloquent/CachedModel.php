<?php

namespace Toanld\Relationships\Database\Eloquent;

use Illuminate\Database\Eloquent\Model;

abstract class CachedModel extends Model
{
    protected $cacheCountDuration = -1;
    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @return CachedBuilder|\Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        //dd($this->cache_count_duration);
        return new CachedBuilder($query,$this->cacheCountDuration);
    }
}
