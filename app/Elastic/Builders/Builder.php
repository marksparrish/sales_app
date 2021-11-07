<?php

namespace App\Elastic\Builders;

use App\Elastic\ElasticEngine;

// This is the generic builder class that extends the ElasticEngine
class Builder extends ElasticEngine
{
    public function __construct($model)
    {
        $this->model = $model;
        parent::__construct();
    }

    public function match($query_string)
    {
        $query_string = strtolower(preg_replace('/^\s+|\s+$|\s+(?=\s)/', '', $query_string));
		$this->setMustQuery('match',
            [
                'message' => [
                    'query' => $query_string,
                    'operator' => 'AND'
                ]
            ]
        );
        return $this;
    }

    public function mappings() {
        return [
            'index' => $this->index,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ]
            ]
        ];
    }
}
