<?php
namespace Cohesion\Util\External;

use \Cohesion\Structure\Util;
use \Cohesion\Config\Config;

abstract class ExternalAPI implements Util {

    protected $config;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public abstract function get($id);

    public abstract function search($query, $limit);

    protected function getUrlContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}

class ExternalAPIException extends \Exception {}

