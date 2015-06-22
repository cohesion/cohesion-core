<?php
namespace Cohesion\Structure;

/**
 * Data Transfer Obeject (DTO)
 *
 * DTOs are basic object classes that contain information about a 'thing'.
 * There should be no business logic or data access login in a DTO.
 *
 * @author Adric Schreuders
 */
abstract class DTO {
    private $reflection;

    const MAX_PHONE_LENGTH = 20;
    const MIN_PHONE_LENGTH = 6;
    const MAX_EMAIL_LENGTH = 60;
    const MAX_URL_LENGTH = 255;

    public function __construct($vars) {
        $this->reflection = new \ReflectionClass($this);
        $this->setMultiple($vars);
    }

    public function setMultiple($vars) {
        $classProperties = $this->reflection->getProperties();
        $classVars = array();
        foreach ($classProperties as $property) {
            $classVars[strtolower($property->name)] = $property->name;
        }
        $reflectionMethods = $this->reflection->getMethods();
        $classMethods = array();
        foreach ($reflectionMethods as $method) {
            $classMethods[strtolower($method->name)] = $method->name;
        }
        // for each var in vars array
        if ($vars) {
            foreach ($vars as $var => $value) {
                $var = $this->underscoreToCamel($var);
                // if var is a class var
                if (isset($classVars[strtolower($var)])) {
                    // if the var has a set method
                    if (isset($classMethods['set' . strtolower($var)])) {
                        // set the var using it's set method
                        $this->{$classMethods['set' . strtolower($var)]}($value);
                    // otherwise
                    } else {
                        // just set the var directly
                        $this->{$classVars[strtolower($var)]} = $value;
                    }
                }
            }
        }
    }

    public function setId($id) {
        $className = get_class($this);
        if (!$this->reflection->hasProperty('id')) {
            throw new \BadFunctionCallException("Bad call to setId() on $className which doesn't have an ID field");
        }
        if ($this->id && $this->id != $id) {
            throw new \InvalidArgumentException("Cannot set $className ID field after it's already been set");
        }
        $this->id = $id;
    }

    /**
     * Magic function that simulates getters and setters if they're not already
     * provided. You can still provide your own and if so that would be used
     * instead.
     */
    public function __call($method, $args = null) {
        if (preg_match('/^get(.*)$/', $method, $matches)) {
            $param = lcfirst($matches[1]);
            if ($this->reflection->hasProperty($param)) {
                return $this->$param;
            } else {
                throw new \BadFunctionCallException("Property `$param` does not exist within " . $this->reflection->getName());
            }
        }
        if (preg_match('/^set(.*)$/', $method, $matches)) {
            $param = lcfirst($matches[1]);
            if ($this->reflection->hasProperty($param)) {
                if (is_array($args) && count($args) === 1) {
                    $this->$param = $args[0];
                    return true;
                } else {
                    throw new \BadFunctionCallException("Method `$method` expects one argument " . (is_array($args) ? count($args) : 'none') . ' given');
                }
            } else {
                throw new \BadFunctionCallException("Property `$param` does not exist within " . $this->reflection->getName());
            }
        }
        if (preg_match('/^is(.*)$/', $method, $matches)) {
            $param = lcfirst($matches[1]);
            if ($this->reflection->hasProperty($param)) {
                return (boolean)$this->$param;
            } else {
                throw new \BadFunctionCallException("Property `$param` does not exist within " . $this->reflection->getName());
            }
        }
        throw new \BadFunctionCallException("Method `$method` does not exist");
    }

    /**
     * Export protected class parameters as an associative array
     */
    public function getVars($showNulls = false) {
        $classProperties = $this->reflection->getProperties(\ReflectionProperty::IS_PROTECTED);
        $vars = array();
        foreach ($classProperties as $property) {
            if (!isset($this->{$property->name})) {
                $var = null;
            // if it's another DTO
            } else if ($this->{$property->name} instanceof DTO) {
                // Get it's vars
                $var = $this->{$property->name}->getVars();
            // If it's an array of DTOs
            } else if (is_array($this->{$property->name})
                    && count($this->{$property->name}) > 0
                    && $this->{$property->name}[0] instanceof DTO) {
                $var = array();
                // Get the vars for each
                foreach ($this->{$property->name} as $i => $v) {
                    $var[$i] = $v->getVars();
                }
            // If it's some other kind of object
            } else if (is_object($this->{$property->name}) && method_exists($this->{$property->name}, '__toString')) {
                $var = (string)$this->{$property->name};
            // Otherwise
            } else {
                // Just use the value
                $var = $this->{$property->name};
            }
            if ($showNulls || ($var !== null && (!is_array($var) || count($var) > 0))) {
                $vars[$this->camelToUnderscore($property->name)] = $var;
            }
        }
        return $vars;
    }

    protected function getReflection() {
        return $this->reflection;
    }

    /**
     * Convert camelCase to camel_case
     * http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
     */
    protected function camelToUnderscore($name) {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $name, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    protected function underscoreToCamel($name, $firstCaps = false) {
        $words = explode('_', $name);
        $return = '';
        foreach ($words as $i => $word) {
            if ($i > 0 || $firstCaps) {
                $return .= ucfirst($word);
            } else {
                $return .= $word;
            }
        }
        return $return;
    }

    protected function validateId($id) {
        return (is_int($id) || (int)$id == $id) && $id > 0;
    }

    protected  function validateString($str, &$errors = null, $name = null, $min = null, $max = null) {
        if (!$min && !$str) {
            return true;
        }
        if (is_array($errors) && !$name) {
            $name = 'String';
        }
        if (is_string($str)) {
            if (strlen($str) < $min) {
                if (!is_array($errors)) {
                    return false;
                }
                $errors[] = $name . ' must be at least ' . $min . ' character' . ($min != 1 ? 's' : '');
            } else if (strlen($str) > $max) {
                if (!is_array($errors)) {
                    return false;
                }
                $errors[] = $name . ' cannot be longer than ' . $max . ' character' . ($max != 1 ? 's' : '');
            }
            return !$errors;
        } else {
            if (is_array($errors)) {
                $errors[] = 'Invalid ' . $name . ' is not a string';
            }
            return false;
        }
    }

    protected function validatePhone($phone, &$errors = null) {
        if (strlen($phone) > self::MAX_PHONE_LENGTH) {
            if (!is_array($errors)) {
                return false;
            }
            $errors[] = 'Phone number must be less than ' . self::MAX_PHONE_LENGTH . ' characters';
        } else if (strlen($phone) < self::MIN_PHONE_LENGTH) {
            if (!is_array($errors)) {
                return false;
            }
            $errors[] = 'Phone number must be at least ' . self::MIN_PHONE_LENGTH . ' characters';
        }
        if (preg_match('/^(?:\+\d{2,4}\s?)?(?:\(\d{2,4}\)\s?)?[\d -]{5,16}(?:(?:ext|x)\s?\d{1,5})?$/', $phone)) {
            return !$errors;
        } else {
            if (is_array($errors)) {
                $errors[] = 'Invalid phone number';
            }
            return false;
        }
    }

    protected function validateEmail($email, &$errors = null) {
        if (strlen($email) > self::MAX_EMAIL_LENGTH) {
            if (!is_array($errors)) {
                return false;
            }
            $errors[] = 'Email must be less than ' . self::MAX_EMAIL_LENGTH . ' characters';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return !$errors;
        } else {
            if (is_array($errors)) {
                $errors[] = 'Invalid email address';
            }
            return false;
        }
    }

    protected function validateTimestamp($timestamp, &$errors = null) {
        if (is_int($timestamp) || (is_numeric($timestamp) && (int)$timestamp == $timestamp)) {
            return true;
        } else {
            if (is_array($errors)) {
                $errors[] = 'Invalid timestamp: ' . $timestamp;
            }
            return false;
        }
    }

    protected function validateDate($date, &$errors = null) {
        $date = \DateTime::createFromFormat('d/m/Y', $date);
        if (!$date && is_array($errors)) {
            $errors[] = 'Invalid date format';
        } else {
            return true;
        }
        return false;
    }

    protected function validateUrl($url, &$errors = null) {
        if (!$url) {
            if (!is_array($errors)) {
                return false;
            }
            $errors[] = 'Empty URL';
        }
        if (strlen($url) > self::MAX_URL_LENGTH) {
            if (!is_array($errors)) {
                return false;
            }
            $errors[] = 'URL must be less than ' . self::MAX_URL_LENGTH . ' characters';
        }
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return !$errors;
        } else {
            if (is_array($errors)) {
                $errors[] = 'Invalid url';
            }
            return false;
        }
    }

    protected function validateIp($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP);
    }

    protected function validateCoordinates($latitude, $longitude, &$errors = null) {
        if (is_numeric($latitude) && is_numeric($longitude)) {
            if ($latitude == 0 && $longitude == 0) {
                if (is_array($errors)) {
                    $errors[] = 'Zero coordinates';
                }
            } else if ($latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180) {
                if (is_array($errors)) {
                    $errors[] = "Invalid coordinates $latitude, $longitude";
                }
            } else {
                return true;
            }
        } else if (is_array($errors)) {
            $errors[] = "Invalid coordinates";
        }
        return false;
    }
}
