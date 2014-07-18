<?php
namespace Cohesion\Environment;

use \Cohesion\Auth\AdminAuth;

class CLIEnvironment extends Environment {
    public function __construct() {
        parent::__construct();
        $this->auth = new AdminAuth();
    }
}

