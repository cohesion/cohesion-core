<?php
namespace Cohesion\Structure;

use \Cohesion\Config\Config;
use \Cohesion\Util\Input;
use \Cohesion\Auth\Auth;
use \Cohesion\Environment\Environment;
use \Cohesion\Structure\Factory\ServiceFactory;

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
abstract class Controller {
    protected $config;
    protected $input;
    protected $auth;
    protected $env;
    protected $factory;

    public function __construct(
        ServiceFactory $factory,
        Input $input = null,
        Config $config = null,
        Auth $auth = null,
        Environment $env = null
    ) {
        $this->factory = $factory;
        $this->input = $input;
        $this->config = $config;
        $this->auth = $auth;
        $this->env = $env;
    }
}
