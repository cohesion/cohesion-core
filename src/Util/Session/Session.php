<?php
namespace Cohesion\Util\Session;

use \Cohesion\Config\Config;

interface Session {

    public function start();

    public function get($key);

    public function set($key, $value);

    public function delete($key);

    public function end();
}
