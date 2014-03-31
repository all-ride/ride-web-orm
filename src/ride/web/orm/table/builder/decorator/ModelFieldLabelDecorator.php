<?php

namespace ride\web\orm\table\builder\decorator;

use ride\library\html\table\decorator\Decorator;
use ride\library\html\table\Cell;
use ride\library\html\table\Row;
use ride\library\i18n\translator\Translator;
use ride\library\orm\definition\field\ModelField;

/**
 * Decorator for the label of a model field
 */
class ModelFieldLabelDecorator implements Decorator {

    /**
     * Translator for the labels
     * @var \ride\library\i18n\translator\Translator
     */
    private $translator;

    /**
     * Constructs a new model field label decorator
     * @param \ride\library\i18n\translator\Translator $translator
     * @return null
     */
    public function __construct(Translator $translator) {
        $this->translator = $translator;
    }

    /**
     * Decorates the cell
     * @param \ride\library\html\table\Cell $cell Cell of the value to decorate
     * @param \ride\library\html\table\Row $row Row containing the cell
     * @param int $rowNumber Number of the current row
     * @param array $remainingValues Array containing the values of the remaining rows of the table
     * @return null
     */
    public function decorate(Cell $cell, Row $row, $rowNumber, array $remainingValues) {
        $field = $cell->getValue();
        if (!($field instanceof ModelField)) {
            return;
        }

        $label = $field->getOption('label');
        if (!$label) {
            $cell->setValue('');
            return;
        }

        $value = $this->translator->translate($label);
        $value .= '<div class="info">' . $label . '</div>';

        $cell->setValue($value);
    }

}