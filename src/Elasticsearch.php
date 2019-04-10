<?php

namespace Codeception\Module;

use Codeception\Module;
use Codeception\Lib\ModuleContainer;

use Elasticsearch\ClientBuilder;

class Elasticsearch extends Module
{
    public $elasticsearch;

    public function _initialize()
    {
        $clientBuilder = ClientBuilder::create();
        $clientBuilder->setHosts($this->_getConfig('hosts'));
        $this->client = $clientBuilder->build();

        if ($this->_getConfig('cleanup')) {
            $this->client->indices()->delete(['index' => '*']);
        }
    }

    public function grabFromElasticsearch($index = null, $type = null, $queryString = '*')
    {
        $result = $this->client->search(
            [
                'index' => $index,
                'type' => $type,
                'q' => $queryString,
                'size' => 1
            ]
        );

        return !empty($result['hits']['hits'])
            ? $result['hits']['hits'][0]['_source']
            : array();
    }

    public function seeInElasticsearch($index, $type, $fieldsOrValue)
    {
        return $this->assertTrue($this->count($index, $type, $fieldsOrValue) > 0, "Couldn\'t find $index with". json_encode($fieldsOrValue));
    }

    public function dontSeeInElasticsearch($index, $type, $fieldsOrValue)
    {
        return $this->assertTrue($this->count($index, $type, $fieldsOrValue) === 0,
            "Unexpectedly managed to find $index with".json_encode($fieldsOrValue)));
    }

    protected function count($index, $type, $fieldsOrValue)
    {
        $query = [];

        if (is_array($fieldsOrValue)) {
            $query['bool']['filter'] = array_map(function ($value, $key) {
                return ['match' => [$key => $value]];
            }, $fieldsOrValue, array_keys($fieldsOrValue));
        }
        else {
            $query['multi_match'] = [
                'query' => $fieldsOrValue,
                'fields' => '_all',
            ];
        }

        $params = [
            'index' => $index,
            'type' => $type,
            'size' => 1,
            'body' => ['query' => $query],
        ];

        $this->client->indices()->refresh();

        $result = $this->client->search($params);

        return (int) $result['hits']['total'];
    }

    public function haveInElasticsearch($document)
    {
        $result = $this->client->index($document);

        $this->client->indices()->refresh();

        return $result;
    }

}
