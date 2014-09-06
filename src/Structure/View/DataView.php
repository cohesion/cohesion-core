<?php
namespace Cohesion\Structure\View;

use Cohesion\Structure\DTO;

abstract class DataView extends View {
    protected $format;

    public function __construct($data = null, $format = null) {
        if ($data !== null) {
            $this->setData($data);
        }
        $this->format = $format;
    }

    public function setData($data) {
        $data = $this->getVars($data);
        $this->addVar('result', $data);
    }

    protected function getOutput() {
        $vars = $this->vars;
        $success = !$this->errors;
        $vars['success'] = $success;
        if (!$success) {
            $vars['errors'] = $this->errors;
        }
        return $vars;
    }

    private function getVars($data) {
        if ($data instanceof DTO) {
            $data = $data->getVars();
        } else if (is_array($data)) {
            foreach ($data as $i => $item) {
                $data[$i] = $this->getVars($item);
            }
        }
        return $data;
    }
}

class InvalidViewFormatException extends ViewException {}
