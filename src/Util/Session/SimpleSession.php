<?php
namespace Cohesion\Util\Session;

use \Cohesion\Config\Config;

class SimpleSession implements Session {

    public function __construct() {
    }

    public function start() {
        session_start();
    }

    public function get($key) {
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } else {
            return null;
        }
    }

    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public function delete($key) {
        unset($_SESSION[$key]);
    }

    public function end() {
        session_destroy();
    }
}
