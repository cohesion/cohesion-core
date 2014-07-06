<?php
namespace Cohesion\Templating;

use \Mustache_Loader_FilesystemLoader;

class MustacheVariablePartialLoader extends Mustache_Loader_FilesystemLoader {
    protected $vars;

    public function __construct($baseDir, array $options = array(), array $vars = array()) {
        $this->setVars($vars);
        parent::__construct($baseDir, $options);
    }

    public function load($name) {
        $parts = explode('.', $name);
        $vars = $this->vars;
        foreach ($parts as $i => $part) {
            if (array_key_exists($part, $vars)) {
                if (is_array($vars[$part])) {
                    $vars = $vars[$part];
                } else {
                    if ($i === count($parts) -1) {
                        $name = $vars[$part];
                    }
                }
            }
        }
        return parent::load($name);
    }

    public function setVars($vars) {
        $this->vars = $vars;
    }
}

