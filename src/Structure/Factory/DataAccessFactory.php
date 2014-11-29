<?php
namespace Cohesion\Structure\Factory;

use \Cohesion\Config\Config;
use \ReflectionClass;
use \InvalidArgumentException;

class DataAccessFactory extends AbstractFactory {

    private $config;
    private $accesses;
    private $cyclicDependancies;

    public function __construct(Config $config) {
        $this->config = $config;
        $this->accesses = array();
        $this->cyclicDependancies = array();
    }

    public function get($class) {
        if (isset($this->accesses[$class])) {
            return $this->accesses[$class];
        }
        if (!class_exists($class)) {
            throw new InvalidClassException("$class doesn't exist");
        }
        $reflection = new ReflectionClass($class);
        $params = $this->getConstructor($reflection)->getParameters();
        $values = array();
        foreach ($params as $i => $param) {
            unset($value);
            if (!$param->getClass()) {
                throw new InvalidClassException("Unknown access type for {$class} constructor parameter '{$param->getName()}'. Make sure DataAccess objects use Type Hints");
            }
            $type = $param->getClass()->getShortName();
            if (!isset($this->accesses[$type])) {
                if ($param->getClass()->isSubclassOf('Cohesion\\Structure\\DAO')) {
                    if (isset($this->cyclicDependancies[$param->getClass()->getName()])) {
                        $dependancies = clone $this->cyclicDependancies;
                        $dependancies[] = $param->getClass()->getName();
                        $this->cyclicDependancies = array();
                        throw new CyclicDependancyException('Cyclic dependancy discovered while loading ' . implode(' -> ', $dependancies));
                    }
                    $this->cyclicDependancies[$class] = true;
                    $value = $this->get($param->getClass()->getName());
                    unset($this->cyclicDependancies[$class]);
                } else {
                    $config = $this->config->getConfig(strtolower($type));
                    if (!$config) {
                        throw new InvalidAccessException("Unknown access type. $type not set in the configuration");
                    }
                    $driver = $config->get('driver');
                    if ($driver) {
                        $driver = "\\Cohesion\\DataAccess\\$type\\$driver";
                        $typeReflection = new ReflectionClass($driver);
                    } else {
                        $driver = $param->getClass()->getName();
                        $typeReflection = $param->getClass();
                        if ($param->getClass()->isAbstract()) {
                            throw new InvalidAccessException("No driver found for $type");
                        }
                    }
                    if (!class_exists($driver)) {
                        throw new InvalidAccessException("No class found for $driver driver");
                    }
                    $accessParams = $this->getConstructor($typeReflection)->getParameters();
                    if (count($accessParams) === 0) {
                        $value = new $driver();
                    } else if (count($accessParams === 1 && $accessParams[0]->getClass()->instanceOf('Cohesion\\Config\\Config'))) {
                        $value = new $driver($config);
                    } else {
                        throw new InvalidAccessException("Unable to construct $driver as it doesn't take a Config object");
                    }
                }
                $this->accesses[$type] = $value;
            }
            $values[$i] = $this->accesses[$type];
        }
        $dao = $reflection->newInstanceArgs($values);
        $this->accesses[$class] = $dao;
        return $dao;
    }
}
