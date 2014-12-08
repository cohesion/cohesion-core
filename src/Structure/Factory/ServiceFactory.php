<?php
namespace Cohesion\Structure\Factory;

use \Cohesion\Auth\Auth;
use \Cohesion\Config\Config;
use \ReflectionClass;

class ServiceFactory extends AbstractFactory {

    private $config;
    private $auth;

    private $daoFactory;
    private $services;
    private $cyclicDependancies;

    public function __construct(Config $config, Auth $auth = null) {
        $this->config = $config;
        $this->auth = $auth;
        $this->daoFactory = new DataAccessFactory($config->getConfig('data_access'));
        $this->services = array();
        $this->cyclicDependancies = array();
    }

    public function get($class) {
        if (isset($this->services[$class])) {
            return $this->services[$class];
        }
        if (!class_exists($class)) {
            throw new InvalidClassException("$class doesn't exist");
        }
        $reflection = new ReflectionClass($class);
        $params = $this->getConstructor($reflection)->getParameters();
        $values = array();
        foreach ($params as $i => $param) {
            unset($value);
            if ($param->getClass() && $param->getClass()->getName() == 'Cohesion\\Config\\Config') {
                $value = $this->config->getConfig('application');
            } else if ($param->getClass() && $param->getClass()->getName() == 'Cohesion\\Structure\\DAO') {
                $value = $this->getServiceDAO($class);
            } else if ($param->getClass() && $param->getClass()->isSubclassOf('Cohesion\\Structure\\DAO')) {
                $value = $this->daoFactory->get($param->getClass()->getName());
            } else if ($param->getClass() && $param->getClass()->isSubclassOf('Cohesion\\Structure\\Service')) {
                if (in_array($param->getClass()->getName(), $this->cyclicDependancies)) {
                    $dependancies = implode(' -> ', $this->cyclicDependancies) . ' -> ' . $param->getClass()->getName();
                    $this->cyclicDependancies = array();
                    throw new CyclicDependancyException("Cyclic dependancy discovered while loading $dependancies");
                }
                $this->cyclicDependancies[] = $param->getClass()->getName();
                $value = $this->get($param->getClass()->getName());
                array_pop($this->cyclicDependancies);
            } else if ($param->getClass() && $param->getClass()->isSubclassOf('Cohesion\\Structure\\Util')) {
                $value = $this->getUtil($param->getClass());
            } else if ($this->auth && $param->getClass() && $param->getClass()->isSubclassOf('Cohesion\\Auth\\User')) {
                $value = $this->auth->getUser();
            } else if ($param->getClass() && $param->getClass()->isInstantiable()) {
                $constructor = null;
                try {
                    $constructor = $this->getConstructor($param->getClass());
                } catch (InvalidClassException $e) {
                }
                if (!$constructor || $constructor->getNumberOfRequiredParameters() == 0) {
                    $value = $param->getClass()->newInstance();
                } else if (!$param->isOptional()) {
                    throw new InvalidPropertyException('Invalid Service property ' . $param->getName() . '. Unable to create instance of ' . $param->getClass->getName());
                }
            } else if (!$param->isOptional()) {
                throw new InvalidPropertyException('Invalid Service property ' . $param->getName());
            }
            if (isset($value)) {
                $values[$i] = $value;
            }
        }
        $service = $reflection->newInstanceArgs($values);
        if ($this->auth) {
            $service->setUser($this->auth->getUser());
        }
        $this->services[$class] = $service;
        return $service;
    }

    public function setAuth(Auth $auth) {
        $this->auth = $auth;
    }

    public function getUtil($class) {
        if (is_string($class)) {
            $class = new ReflectionClass($class);
        }
        $utilClass = $class->getName();
        return new $utilClass($this->config->getConfig('utility.' . strtolower($class->getShortName())));
    }

    private function getServiceDAO($serviceClass) {
        $servicePrefix = $this->config->get('application.class.prefix');
        $serviceSuffix = $this->config->get('application.class.suffix');
        $name = preg_replace(["/^$servicePrefix/", "/$serviceSuffix$/"], '', $serviceClass);
        $daoPrefix = $this->config->get('data_access.class.prefix');
        $daoSuffix = $this->config->get('data_access.class.suffix');
        $class = $daoPrefix . $name . $daoSuffix;
        if (!class_exists($class)) {
            throw new InvalidClassException("$class does not exist");
        }
        return $this->daoFactory->get($class);
    }
}
