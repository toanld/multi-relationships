<?php

namespace Toanld\Relationship\Database\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

/**
 * @mixin \Illuminate\Database\Query\Builder
 */
class CachedBuilder extends Builder
{
    const CACHE_THRESHOLD = 10;
    protected $cacheCountDuration = -1;
    const CACHE_KEY_PREFIX = 'pagination_';
    public $query;

    public function __construct(QueryBuilder $query,$duration = 0)
    {
        parent::__construct($query);
        $this->cacheCountDuration = $duration;
    }

    /**
     * Paginate the given query.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     *
     * @throws \InvalidArgumentException
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();
        $key = $this->getKeyCacheCount();
        //nếu set thời gian cache count thì lấy từ cache ra
        //dd(intval($this->cacheCountDuration) );
        if(intval($this->cacheCountDuration) > 0) {
            $total = (int) Cache::get($key);
            //dd($total);
            if ($total <= 0) {
                $total = $this->toBase()->getCountForPagination();
                if ($total > self::CACHE_THRESHOLD) {
                    //dd($total);
                    Cache::put($key, $total, $this->cacheCountDuration);
                }
            }
        }else{
            $total = $this->toBase()->getCountForPagination();
        }

        $results = $total ? $this->forPage($page, $perPage)->get($columns)
            : $this->model->newCollection();

        return $this->paginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    protected function getKeyCacheCount(){
        $arrWhere = $this->toBase()->wheres;
        $arrKeyCache = [];
        foreach ($arrWhere as $where){
            $str = '';
            foreach ($where as $k => $v){
                if(is_string($v)){
                    $v = preg_replace('/\/\*(.*)\*\//', '', $v);
                }
                $str .= $k . (is_array($v) ? implode(',',$v) : strval($v));
            }
            $arrKeyCache[] = $str;
        }
        $key = $this->toBase()->getConnection()->getDatabaseName() . ':' . self::CACHE_KEY_PREFIX . ':' . $this->getModel()->getTable() . ":where:" . md5(implode("|",$arrKeyCache));
        return $key;
    }

}
