<?php
namespace Cohesion\Structure;

use \Cohesion\Config\Configurable;
use \Cohesion\Config\Config;

interface Util extends Configurable {
    public function __construct(Config $config);
}
