<?php
namespace Cohesion\Structure\Factory;

use \Cohesion\Environment\Environment;
use \Cohesion\Config\Config;

abstract class AbstractFactory {

    protected function getConstructor(\ReflectionClass $reflection, \ReflectionClass $originalClass = null) {
        if (!$originalClass) {
            $originalClass = $reflection;
        }
        $constructor = $reflection->getConstructor();
        if ($constructor) {
            return $constructor;
        } else {
            $parent = $reflection->getParentClass();
            if ($parent) {
                return $this->getConstructor($parent, $originalClass);
            } else {
                throw new InvalidClassException('Missing constructor for ' . $originalClass->getName());
            }
        }
    }
}
