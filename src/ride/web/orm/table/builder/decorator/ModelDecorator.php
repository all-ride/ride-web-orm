<?php

namespace ride\web\orm\table\builder\decorator;

use ride\library\html\table\decorator\Decorator;
use ride\library\html\table\Cell;
use ride\library\html\table\Row;
use ride\library\html\Anchor;
use ride\library\i18n\translator\Translator;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\model\Model;
use ride\library\orm\OrmManager;

/**
 * Decorator for a model
 */
class ModelDecorator implements Decorator {

    /**
     * Instance of the ORM manager
     * @var \ride\library\orm\OrmManager
     */
    private $orm;

    /**
     * Translator needed for the model information
     * @var \ride\library\i18n\translator\Translator
     */
    private $translator;

    /**
     * The action behind the model name
     * @var string
     */
    private $action;

    /**
     * Constructs a new model decorator
     * @param string $action URL for the anchor behind the model name
     * @param \ride\library\orm\OrmManager $orm
     * @param \ride\library\i18n\translator\Translator $translator
     * @return null
     */
    public function __construct(OrmManager $orm, Translator $translator, $action = null) {
        $this->orm = $orm;
        $this->translator = $translator;
        $this->action = $action;
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
        $model = $cell->getValue();
        if (!($model instanceof Model)) {
            return;
        }

        $modelName = $model->getName();

        if ($this->action) {
            $anchor = new Anchor($modelName, str_replace('%25model%25', $modelName, $this->action));
            $value = $anchor->getHtml();
        } else {
            $value = $modelName;
        }

        $info = $this->getRelationInfo($model) . $this->getUnlinkedModelsInfo($modelName);

        if ($info) {
            $value .= '<div class="info">' . $info . '</div>';
        }

        $cell->setValue($value);
    }

    /**
     * Gets the general relation information of the provided model
     * @param \ride\library\orm\model\Model $model The model to get the information from
     * @return string The general relation information of the provided model
     */
    private function getRelationInfo(Model $model) {
        $table = $model->getMeta()->getModelTable();

        $info = '';

        $relations = array();
        $fields = $table->getFields();
        foreach ($fields as $field) {
            if ($field instanceof PropertyField) {
                continue;
            }

            $relationModelName = $field->getRelationModelName();

            $relationModelValue = $relationModelName;
            if ($this->action) {
                $anchor = new Anchor($relationModelName, str_replace('%model%', $relationModelName, $this->action));
                $relationModelValue = $anchor->getHtml();
            }

            $relations[$relationModelName] = $relationModelValue;
        }
        $numRelations = count($relations);

        if ($numRelations == 1) {
            $relation = array_pop($relations);
            $info .= $this->translator->translate('label.relation.with', array('model' => $relation)) . '<br />';
        } elseif ($numRelations) {
            $last = array_pop($relations);
            $first = implode(', ', $relations);
            $info .= $this->translator->translate('label.relations.with', array('first' => $first, 'last' => $last)) . '<br />';
        }

        return $info;
    }

    /**
     * Gets the information about the unlinked models of the provided table
     * @param string $tableName The name of the table
     * @return string The information about the unlinked models of the provided table
     */
    private function getUnlinkedModelsInfo($tableName) {
        $info = '';

        $model = $this->orm->getModel($tableName);
        $unlinkedModels = $model->getMeta()->getUnlinkedModels();
        $numUnlinkedModels = count($unlinkedModels);

        if ($this->action) {
            foreach ($unlinkedModels as $index => $modelName) {
                $anchor = new Anchor($modelName, str_replace('%25model%25', $modelName, $this->action));
                $unlinkedModels[$index] = $anchor->getHtml();
            }
        }

        if ($numUnlinkedModels == 1) {
            $model = array_pop($unlinkedModels);
            $info .= $this->translator->translate('label.unlinked.model', array('model' => $model)) . '<br />';
        } elseif ($numUnlinkedModels) {
            $last = array_pop($unlinkedModels);
            $first = implode(', ', $unlinkedModels);
            $info .= $this->translator->translate('label.unlinked.models', array('first' => $first, 'last' => $last)) . '<br />';
        }

        return $info;
    }

}