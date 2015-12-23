<?php
namespace Cohesion\Structure\Factory;

use \Cohesion\Config\Config;
use \Cohesion\Config\MissingConfigurationException;
use \ReflectionClass;

class UtilityFactory extends AbstractFactory {

    protected $config;
    protected $utils = array();

    public function __construct(Config $config = null) {
        $this->config = $config;
    }

    /**
     * Get a utility class
     *
     * @param mixed $class Either a string of the class name or an instance of a \ReflectionClass representing the utility to get
     * @return mixed The utility matching the given class
     * @throws InvalidClassException if either the utility class or the utility driver class doesn't exist
     * @throws MissingConfigurationException if the utility requires configuration and it doesn't have any, or one of the required variable is missing form the configuration
     */
    public function get($class) {
        if ($class instanceof ReflectionClass) {
            $reflection = $class;
            $class = $reflection->getName();
        }
        if (isset($this->utils[$class])) {
            return $this->utils[$class];
        }
        if (!isset($reflection)) {
            if (!class_exists($class) && !interface_exists($class)) {
                throw new InvalidClassException("$class doesn't exist");
            }
            $reflection = new ReflectionClass($class);
        }

        $config = null;
        if ($this->config) {
            $config = $this->config->getConfig($class);
            if (!$config) {
                $config = $this->config->getConfig($reflection->getShortName());
            }
        }
        if (!$config) {
            if ($reflection->isInstantiable() && $this->getConstructor($reflection)->getNumberOfRequiredParameters() == 0) {
                return $reflection->newInstance();
            } else {
                throw new MissingConfigurationException("No configuration found for utility $class");
            }
        }
        $driver = $config->get('driver');
        if (!$driver) {
            if ($reflection->isInstantiable()) {
                $driver = $reflection->getName();
                $driverReflection = $reflection;
            } else {
                throw new MissingConfigurationException("Configuration for $class must include a driver");
            }
        } else {
            if (!class_exists($driver)) {
                throw new InvalidClassException("$class driver $driver doesn't exist");
            }
            $driverReflection = new ReflectionClass($driver);
            if (!$driverReflection->isInstantiable()) {
                throw new InvalidClassException("$class driver $driver is not instantiable");
            }
        }
        $utility = $this->getClass($driverReflection, $config);
        $this->utils[$class] = $utility;
        return $utility;
    }

    protected function getClass(ReflectionClass $class, Config $config = null, $name = null) {
        $parameters = $this->getConstructor($class)->getParameters();

        $values = array();
        if (!$config && $parameters) {
            throw new MissingConfigurationException('Missing configuration for utility class ' . $class->getName());
        }
        foreach ($parameters as $i => $parameter) {
            if (($parameter->getClass() && $parameter->getClass()->getName() === 'Cohesion\\Structure\\Config') || $parameter->getName() === 'config') {
                $values[] = $config;
            } else if ($config->get($parameter->getName()) !== null) {
                if ($parameter->getClass()) {
                    if ($driver = $config->getConfig($parameter->getName())->get('driver')) {
                        $driverReflection = new ReflectionClass($driver);
                        $values[] = $this->getClass($driverReflection, $config->getConfig($parameter->getName()), $parameter->getName());
                    } else {
                        $values[] = $this->getClass($parameter->getClass(), $config->getConfig($parameter->getName()), $parameter->getName());
                    }
                } else {
                    $values[] = $config->get($parameter->getName());
                }
            } else if (!$parameter->isOptional()) {
                if (!$name) {
                    $name = $class->getShortName();
                }
                throw new MissingConfigurationException("Missing configuration for $name {$parameter->getName()}");
            } else {
                $values[] = $parameter->getDefaultValue();
            }
        }
        return $class->newInstanceArgs($values);
    }
}

