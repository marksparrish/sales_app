<?php

namespace App\Elastic;

use Illuminate\Support\Collection;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class SearchResults
{
    public $total_hits;
    public Collection $models;
    public LengthAwarePaginator $links;
    public Collection $aggregations;
    private $raw;
    private $model;
    private $builder;

    /**
     * @param mixed $builder
     * @param mixed $results
     * @param mixed $model
     * @param mixed $size
     * @param mixed $page_name
     * @param mixed $page
     * @return void
     * @throws BindingResolutionException
     */
    public function __construct($builder, $results, $model, $size, $page_name, $page)
    {
        $this->builder = $builder;
        $this->raw = $results;
        $this->model = $model;
        $this->size = $size;
        $this->page = $page;
        $this->page_name = $page_name;

        $this->total_hits = $this->getTotalHits();
        $this->models = $this->getModels();
        $this->links = $this->getLinks();
        $this->aggregations = $this->getAggregations();
    }

    /** @return mixed  */
    private function getTotalHits()
    {
        return $this->raw['hits']['total']['value'];
    }

    /** @return mixed  */
    private function getModels()
    {
        return $this->size ? $this->model::find(collect($this->raw['hits']['hits'])->pluck('_id')): collect([]);
    }

    /**
     * @return mixed
     * @throws BindingResolutionException
     */
    private function getLinks()
    {
        return Container::getInstance()->makeWith(LengthAwarePaginator::class, [
            'items' => $this->models,
            'total' => $this->total_hits,
            'perPage' => $this->size,
            'currentPage' => $this->page,
            'options' => [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $this->page_name,
                'aggregations' => 'collect'
            ],
        ]);
    }

    /** @return Collection  */
    private function getAggregations()
    {
        // check if the aggregation key is in the results array
        $aggregations = Arr::get($this->raw, 'aggregations', []);
        foreach ($aggregations as $tag => $agg) {
            $callback = 'format' . Str::ucfirst($tag);
            // this call the formatter defined in the builder
            $aggregations[$tag] = call_user_func_array([$this->builder, $callback],[$agg]);
        }
        return collect($aggregations);
    }

    /** @return mixed  */
    public function raw()
    {
        return $this->raw;
    }

}
