<?php

namespace ride\web\orm\table\builder\decorator;

use ride\library\database\definition\Index;
use ride\library\html\table\decorator\Decorator;
use ride\library\html\table\Cell;
use ride\library\html\table\Row;
use ride\library\html\Anchor;
use ride\library\i18n\translator\Translator;

/**
 * Decorator for a index
 */
class IndexDecorator implements Decorator {

    /**
     * URL to the action for the field name
     * @var string
     */
    private $action;

    /**
     * Instance of the translator
     * @var ride\library\i18n\translation\Translator
     */
    private $translator;

    /**
     * Constructs a new index decorator
     * @param ride\library\i18n\translator\Translator $translator
     * @param string $action URL to the action for the index name
     * @return null
     */
    public function __construct(Translator $translator, $action = null) {
        $this->translator = $translator;
        $this->action = $action;
    }

    /**
     * Decorates the cell
     * @param ride\library\html\table\Cell $cell Cell of the value to decorate
     * @param ride\library\html\table\Row $row Row containing the cell
     * @param int $rowNumber Number of the current row
     * @param array $remainingValues Array containing the values of the remaining rows of the table
     * @return null
     */
    public function decorate(Cell $cell, Row $row, $rowNumber, array $values) {
        $index = $cell->getValue();
        if (!($index instanceof Index)) {
            return;
        }

        $indexName = $index->getName();

        if ($this->action) {
            $anchor = new Anchor($indexName, str_replace('%index%', $indexName, $this->action));
            $value = $anchor->getHtml();
        } else {
            $value = $indexName;
        }

        $value .= '<div class="info">';
        $value .= $this->getFieldsInfo($index);
        $value .= '</div>';

        $cell->setValue($value);
    }

    /**
     * Gets the information about the fields of the index
     * @param ride\library\orm\definition\Index $index
     * @return string
     */
    private function getFieldsInfo(Index $index) {
        $info = '';

        $fields = array_keys($index->getFields());
        $numFields = count($fields);

        if ($numFields == 1) {
            $field = array_pop($fields);
            $info .= $this->translator->translate('label.index.field.in', array('field' => $field)) . '<br />';
        } else {
            $last = array_pop($fields);
            $first = implode(', ', $fields);
            $info .= $this->translator->translate('label.index.fields.in', array('first' => $first, 'last' => $last)) . '<br />';
        }

        return $info;
    }

}