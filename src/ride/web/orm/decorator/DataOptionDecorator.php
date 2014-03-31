<?php

namespace ride\web\orm\decorator;

use ride\library\decorator\Decorator;
use ride\library\html\table\FormTable;
use ride\library\orm\definition\ModelTable;
use ride\library\reflection\ReflectionHelper;

/**
 * Decorator to create an option field for a data object, needed for the table actions
 */
class DataOptionDecorator implements Decorator {

    /**
     * Instance of the reflection helper
     * @var \ride\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Name of the value property
     * @var string
     */
    protected $property;

    /**
     * Constructs a new data option decorator
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * Instance of the reflection helper
     * @param string $property Name of the value property
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, $property = null) {
        $this->reflectionHelper = $reflectionHelper;

        if (!$property) {
            $this->property = ModelTable::PRIMARY_KEY;
        } else {
            $this->property = $property;
        }
    }

    /**
     * Decorates the cell with an option field for the table actions
     * @param \ride\library\html\table\Cell $cell Cell which holds the data object
     * @param \ride\library\html\table\Row $row Row of the cell
     * @param integer $rowNumber Current row number
     * @param array $remainingValues Array with the values of the remaining rows of the table
     * @return null
     */
    public function decorate($value) {
        if (!is_object($value)) {
            return '';
        }

        return '<input type="checkbox" name="' . FormTable::FIELD_ID . '[]" value="' . $this->reflectionHelper->getProperty($value, $this->property) . '" />';
    }

}