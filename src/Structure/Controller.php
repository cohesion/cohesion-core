<?php
namespace Cohesion\Structure;

use \Cohesion\Config\Configurable;
use \Cohesion\Config\Config;
use \Cohesion\Util\Input;
use \Cohesion\Auth\Auth;
use \Cohesion\Environment\Environment;

/**
 * The Controllers are the external facing code that access the input variables
 * and returns the output of the relevant view. The Controller handles the
 * authentication, accesses the relevant Handler(s) then constructs the
 * relevant view.
 *
 * Controllers shouldn't contain any business logic including authorisation.
 *
 * @author Adric Schreuders
 */
abstract class Controller implements Configurable {
    protected $config;
    protected $input;
    protected $auth;
    protected $env;

    public function __construct(Config $config, Input $input = null, Auth $auth = null, Environment $env = null) {
        $this->config = $config;
        $this->input = $input;
        $this->auth = $auth;
        $this->env = $env;
    }
}
