<?php

namespace ride\web\orm\table\scaffold\decorator;

use ride\library\html\table\decorator\ActionDecorator as LibraryActionDecorator;

/**
 * ORM  decorator to create a entry action
 */
class ActionDecorator extends LibraryActionDecorator {

    /**
     * Gets the href attribute for the anchor
     * @param mixed $value Value of the cell
     * @return string Href attribute for the anchor
     */
    protected function getHrefFromValue($value) {
        $id = $this->reflectionHelper->getProperty($value, 'id');

        $href = $this->href;
        $href = str_replace('%id%', $id, $href);
        $href = str_replace('%25id%25', $id, $href);

        return $href;
    }

}
