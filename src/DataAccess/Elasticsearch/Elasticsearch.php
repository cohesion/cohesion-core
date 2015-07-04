<?php
namespace Cohesion\DataAccess\Elasticsearch;

use \Cohesion\Config\Configurable;
use \Cohesion\Config\Config;

/**
 * Wrapper for Elasticsearch/Elasticsearch client by Zachary Tong to be used
 * with Cohesion. Takes a config object so that the DAOFactory will be able to
 * construct it.
 */
class Elasticsearch implements Configurable {
    private $config;
    private $client = null;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function get($type, $id) {
        $params = array(
            'index' => $this->config->get('index'),
            'type' => $type,
            'id' => $id,
            '_source' => true
        );
        $client = $this->getClient();
        $result = $client->get($params);
        return $result;
    }

    public function insert($type, $id, $objVars) {
        $params = array(
            'index' => $this->config->get('index'),
            'type' => $type,
            'id' => $id,
            'body' => $objVars
        );
        $client = $this->getClient();
        $result = $client->index($params);
        if (isset($result['error'])) {
            throw new ElasticsearchException("Unable to index $type due to Eleasticsearch error: {$result['error']}");
        } else if ($result) {
            return true;
        } else {
            throw new ElasticsearchException("Unable to index $type due to an unknown Elesticsearch issue");
        }
    }

    public function delete($type, $id) {
        $params = array(
            'index' => $this->config->get('index'),
            'type' => $type,
            'id' => $id
        );
        $client = $this->getClient();
        $result = $client->delete($params);
        if ($result['found']) {
            return true;
        } else if ($result['error']) {
            throw new ElasticsearchException("Unable to remove $type index due to Eleasticsearch error: {$result['error']}");
        } else {
            throw new ElasticsearchException("Unable to remove $type index due to an unknown Elesticsearch issue");
        }
    }

    public function search($type, $query, $limit = 10, $offset = 0, $fieldsToSearch = null, $fieldsToReturn = null, $filters = null) {
        if (is_array($type)) {
            $type = implode(',', $type);
        }
        $params = array(
            'index' => $this->config->get('index'),
            'type' => $type,
            'from' => $offset,
            'size' => $limit
        );
        if (is_array($fieldsToReturn)) {
            $params['fields'] = $fieldsToReturn;
        }
        if (!$fieldsToSearch) {
            $fieldsToSearch = '_all';
        } else if (!is_array($fieldsToSearch) || count($fieldsToSearch) == 1) {
            if (is_array($fieldsToSearch)) {
                $fieldsToSearch = $fieldsToSearch[0];
            }
        }
        if (!is_array($fieldsToSearch)) {
            $query = array(
                'match' => array(
                    $fieldsToSearch => $query
                )
            );
        } else {
            $query = array(
                'multi_match' => array(
                    'query' => $query,
                    'fields' => $fieldsToSearch
                )
            );
        }
        if ($filters) {
            if (count($filters) > 1) {
                $params['body']['query']['filtered']['filter']['and'] = $filters;
            } else {
                $params['body']['query']['filtered']['filter'] = $filters;
            }
            $params['body']['query']['filtered']['query'] = $query;
        } else {
            $params['body']['query'] = $query;
        }
        $client = $this->getClient();
        $result = $client->search($params);
        if (isset($result['hits'])) {
            $totalMatches = $result['hits']['total'];
            $hits = array();
            foreach ($result['hits']['hits'] as $hit) {
                if (isset($hit['_source'])) {
                    $hits[] = $hit['_source'];
                } else {
                    $hits[] = $hit['fields'];
                }
            }
        }
        return $hits;
    }

    public function getClient() {
        if ($this->client === null) {
            $params = array();
            if ($this->config->get('hosts')) {
                $params['hosts'] = $this->config->get('hosts');
            }
            if ($this->config->get('log_path')) {
                $params['logging'] = true;
                $params['logPath'] = $this->config->get('log_path');
            }
            $this->client = new \Elasticsearch\Client($params);
        }
        return $this->client;
    }

    public function getConfig() {
        return $this->config;
    }
}
