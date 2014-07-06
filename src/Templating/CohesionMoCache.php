<?php
namespace Cohesion\Templating;

use \Mustache_Cache;
use \Cohesion\DataAccess\Cache\Cache;

class CohesionMoCache implements Mustache_Cache {

    protected $cache;
    protected $cacheTTL;

    public function __construct(Cache $cache, $ttl = 3600) {
        $this->cache = $cache;
        $this->cacheTTL = $ttl;
    }

    public function load($key) {
        $value = $this->cache->load($key);
        if ($value) {
            $this->includeTemplate($value);
            return true;
        }
        return false;
    }

    public function cache($key, $value) {
        $this->cache->save($value, $key, $this->cacheTTL);
        $this->includeTemplate($value);
    }

    protected function includeTemplate(&$value) {
        eval('?>' . $value);
    }
}

