<?php

namespace ride\web\orm\table\builder\decorator;

use ride\library\html\table\decorator\Decorator;
use ride\library\html\table\Cell;
use ride\library\html\table\Row;
use ride\library\html\Anchor;
use ride\library\i18n\translator\Translator;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasOneField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\ModelField;
use ride\library\orm\definition\field\RelationField;

/**
 * Decorator for a model field
 */
class ModelFieldDecorator implements Decorator {

    /**
     * Instance of the translator
     * @var ride\library\i18n\translation\Translator
     */
    private $translator;

    /**
     * URL to the action of a model
     * @var string
     */
    private $modelAction;

    /**
     * URL to the action of a field name
     * @var string
     */
    private $fieldAction;

    /**
     * Constructs a new field decorator
     * @param ride\library\i18n\translation\Translator $translator
     * @param string $modelAction URL to the action for a model
     * @param string $fieldAction URL to the action for a field
     * @return null
     */
    public function __construct(Translator $translator, $modelAction = null, $fieldAction = null) {
        $this->translator = $translator;
        $this->modelAction = $modelAction;
        $this->fieldAction = $fieldAction;
    }

    /**
     * Decorates the cell
     * @param ride\library\html\table\Cell $cell Cell of the value to decorate
     * @param ride\library\html\table\Row $row Row containing the cell
     * @param int $rowNumber Number of the current row
     * @param array $remainingValues Array containing the values of the remaining rows of the table
     * @return null
     */
    public function decorate(Cell $cell, Row $row, $rowNumber, array $remainingValues) {
        $field = $cell->getValue();
        if (!($field instanceof ModelField)) {
            return;
        }

        $fieldName = $field->getName();

        if ($this->fieldAction) {
            $anchor = new Anchor($fieldName, str_replace('%field%', $fieldName, $this->fieldAction));
            $value = $anchor->getHtml();
        } else {
            $value = $fieldName;
        }

        $value .= '<div class="info">';
        if ($field instanceof RelationField) {
            if ($field instanceof BelongsToField) {
                $relationType = 'belongsTo';
            } elseif ($field instanceof HasOneField) {
                $relationType = 'hasOne';
            } else if ($field instanceof HasManyField) {
                $relationType = 'hasMany';
            }

            $relationModelName = $field->getRelationModelName();
            $linkModelName = $field->getLinkModelName();
            $foreignKeyName = $field->getForeignKeyName();

            if ($this->modelAction) {
                $anchor = new Anchor($relationModelName, str_replace('%25model%25', $relationModelName, $this->modelAction));
                $relationModelName = $anchor->getHtml();

                if ($linkModelName) {
                    $anchor = new Anchor($linkModelName, str_replace('%25model%25', $linkModelName, $this->modelAction));
                    $linkModelName = $anchor->getHtml();
                }
            }

            $parameters = array(
                'type' => $relationType,
                'model' => $relationModelName,
                'link' => $linkModelName,
                'foreignKey' => $foreignKeyName,
            );

            if ($linkModelName) {
                if ($foreignKeyName) {
                    $value .= $this->translator->translate('label.relation.type.link.fk', $parameters);
                } else {
                    $value .= $this->translator->translate('label.relation.type.link', $parameters);
                }
            } else {
                if ($foreignKeyName) {
                    $value .= $this->translator->translate('label.relation.type.fk', $parameters);
                } else {
                    $value .= $this->translator->translate('label.relation.type', $parameters);
                }
            }
        } else {
            $value .= $this->translator->translate('label.field.type', array('type' => $field->getType())) . '<br />';

            $defaultValue = $field->getDefaultValue();
            if ($defaultValue !== null) {
                $value .= $this->translator->translate('label.value.default') . ': ' . $defaultValue;
            }
        }
        $value .= '</div>';

        $cell->setValue($value);
    }

}