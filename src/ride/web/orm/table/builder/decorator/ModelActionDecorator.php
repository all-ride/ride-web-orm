<?php

namespace ride\web\orm\table\builder\decorator;

use ride\library\html\table\decorator\ActionDecorator;
use ride\library\orm\model\Model;

/**
 * Action decorator for a model
 */
class ModelActionDecorator extends ActionDecorator {

    /**
     * Gets the href for the value of the cell
     * @param mixed $value The value to get the href from
     * @return string The href for the action of the model
     */
    protected function getHrefFromValue($value) {
        if ($value instanceof Model) {
            return str_replace('%25model%25', $value->getName(), $this->href);
        }

        $this->setWillDisplay(false);

        return null;
    }

}