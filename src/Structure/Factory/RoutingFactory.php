<?php
namespace Cohesion\Structure\Factory;

use Cohesion\Route\Route;

class RoutingFactory extends AbstractFactory {
    public static $config;

    public static function getRoute() {
        return new Route(self::$config, self::$config->get('global.uri'));
    }
}
