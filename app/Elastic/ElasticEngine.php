<?php

namespace App\Elastic;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\RuntimeException as ExceptionsRuntimeException;
use Exception;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use RuntimeException;

/** @package App\Elastic */

abstract class ElasticEngine
{
    /**
     * Model using the search.
     *
     * @var \Illuminate\Database\Eloquent\Collection $models
     */
    public $model;

    /**
     * Elasticsearch Index for the models.
     *
     * @var string
     */
    public $index;

    /**
     * Holds the mappings for the index
     *
     * @var array
     */
    public $mappings;

    /**
     * Sets the track_total_hits value to default of true
     *
     * @var boolean
     */
    public $track_total_hits = true;

    /**

     * How many results should be returned.
     *
     * @var integer
     */
    public $size = 10;

    /**
     * Sets the page value to default of 1
     *
     * @var integer
     */
    public $page = 1;

    /**
     * Sets the page value to default of 1
     *
     * @var integer
     */
    public $page_name = 1;

    /**
     * Elasticsearch client
     *
     * @var string
     */
    private Client $client;

    /**
     * Collection storage for the Must Queries on Index for the models.
     *
     * @var array
     */
    private ?Collection $must_query = null;

    /**
     * Should Queries on Index for the models.
     *
     * @var array
     */
    private ?Collection $should_query = null;

    /**
     * Must Not Queries on Index for the models.
     *
     * @var array
     */
    private ?Collection $must_not_query = null;

    /**
     * Filters on Index for the models.
     *
     * @var array
     */
    private ?Collection $filter = null;

    /**
     * Aggregations on Index for the models.
     *
     * @var array
     */
    private ?Collection $aggs = null;

    /**
     * Sets the scripts value to default of null
     *
     * @var boolean
     */
    private ?array $script = null;


    /**
     * @return void
     * @throws RuntimeException
     * @throws ExceptionsRuntimeException
     * @throws Exception
     */
    public function __construct()
    {
        // sets the host
        $params = [
            'hosts' => [
                'docker.for.mac.localhost:9200'
            ],
            'retries' => 2,
            'handler' => ClientBuilder::singleHandler()
        ];
        $this->client = ClientBuilder::fromConfig($params);

        // set initial index
        // you can override this using the index setter function below
        // or you can define a method in the model of searchableAs and return a index string
        if (method_exists($this->model, 'searchableAs')) {
            $this->setIndex($this->model->searchableAs());
        } else {
            $this->setIndex($this->model->getTable());
        }
        // if the index does not exist then create it
        if (!$this->client->indices()->exists(['index' => $this->index])) {
            $this->createIndex();
        }
    }

    /**
     * @param int $size
     * @param string $page_name
     * @param mixed|null $page
     * @return SearchResults
     */
    public function paginate($size = 10, $page_name = 'page', $page = null)
    {
        $this->size = $size;
        $this->page = $page ?: Paginator::resolveCurrentPage();
        // execute the query and return the results for formatting
        $results = $this->performSearch();
        return new SearchResults($this, $results, $this->model, $size, $page_name, $page);
    }

    /** @return array  */
    private function params()
    {
        $params = [];
        $params['index'] = $this->index;
        $params['from'] = ($this->page - 1) * $this->size;
        $params['size'] = $this->size;
        $params['track_total_hits'] = $this->track_total_hits;

        // only add must_query key to params when there are queries
        if ($this->must_query) {
            $params['body']['query']['bool']['must'] = $this->must_query->toArray();
        }

        // only add should_query key to params when there are queries
        if ($this->should_query) {
            $params['body']['query']['bool']['should'] = $this->should_query->toArray();
        }

        // only add must_not_query key to params when there are queries
        if ($this->must_not_query) {
            $params['body']['query']['bool']['must_not'] = $this->must_not_query->toArray();
        }

        // only add filter key to params when there are filters
        if ($this->filter) {
            $params['body']['query']['bool']['filter'] = $this->filter->toArray();
        }

        // only add aggregations key to params when there are aggregations
        if ($this->aggs) {
            $params['body']['aggs'] = $this->aggs->toArray();
        }

        // only add a script if there is a script to add.  Only one script can be added.
        if ($this->script) {
            $params['body']['script'] = $this->script;
        }

        // Need to add a sort option to ensure the results are the same.
        $params['body']['sort'][] = "_doc";

        return $params;
    }

    /** @return mixed  */
    protected function performSearch()
    {
        return $this->client->search($this->params());
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush()
    {
        $this->deleteIndex();
        $this->createIndex();
        return "All Models Deleted from {$this->index}";
    }

    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array  $options
     * @return mixed
     *
     * @throws \Exception
     */
    public function createIndex()
    {
        return $this->client->indices()->create($this->mappings());
    }

    /**
     * Delete a search index.
     *
     * @param  string  $name
     * @return mixed
     */
    public function deleteIndex()
    {
        $params = ['index' => $this->index];
        return $this->client->indices()->delete($params);
    }

    // Setters
    // You can override the index
    // $search->index('my_index');
    // instead of $search->index = 'my_index';
    /**
     * @param mixed $index
     * @return $this
     */
    public function setIndex($index)
    {
        $this->index = $index;
        return $this;
    }

    /**
     * @param mixed $key
     * @param mixed $array
     * @return void
     */
    public function setMustQuery($key, $array)
    {
        $this->must_query = $this->must_query ?: collect([]);
        $this->must_query->put($key, $array);
        return;
    }

    /**
     * @param mixed $key
     * @param mixed $array
     * @return void
     */
    public function setShouldQuery($key, $array)
    {
        $this->should_query = $this->should_query ?: collect([]);
        $this->should_query->put($key, $array);
        return;
    }

    /**
     * @param mixed $key
     * @param mixed $array
     * @return void
     */
    public function setMustNotQuery($key, $array)
    {
        $this->must_not_query = $this->must_not_query ?: collect([]);
        $this->must_not_query->put($key, $array);
        return;
    }

    /**
     * @param mixed $key
     * @param mixed $array
     * @return void
     */
    public function setFilter($key, $array)
    {
        $this->filter = $this->filter ?: collect([]);
        $this->filter->put($key, $array);
        return;
    }

    /**
     * @param mixed $key
     * @param mixed $array
     * @return void
     */
    public function setAggregation($key, $array)
    {
        $this->aggs = $this->aggs ?: collect([]);
        $this->aggs->put($key, $array);
        return;
    }

    // define any abstract methods that the build must implement
    /** @return mixed  */
    abstract protected function mappings();
}
