<?php

namespace ride\web\orm\table\scaffold;

use ride\library\decorator\BooleanDecorator;
use ride\library\decorator\DateFormatDecorator;
use ride\library\form\Form;
use ride\library\html\table\decorator\StaticDecorator;
use ride\library\html\table\decorator\ValueDecorator;
use ride\library\html\table\FormTable;
use ride\library\html\Element;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\model\data\format\DataFormatter;
use ride\library\orm\model\Model;

use ride\web\orm\decorator\DataFormatDecorator;
use ride\web\orm\decorator\DataOptionDecorator;

/**
 * Base data model table
 */
class ModelTable extends FormTable {

    /**
     * Model used for the data of this table
     * @var \ride\library\orm\model\Model
     */
    protected $model;

    /**
     * Model query used to populate the rows of this table
     * @var \ride\library\orm\query\ModelQuery
     */
    protected $query;

    /**
     * Name of the primary key field to use as value
     * @var string
     */
    protected $pkField;

    /**
     * Constructs a new model table
     * @param \ride\library\orm\model\Model $model
     * @param string $locale Code of the locale for the data
     * @return null
     */
    public function __construct(Model $model, $locale = null) {
        $this->model = $model;
        $this->query = $model->createQuery($locale);
        $this->pkField = null;

        parent::__construct(array());
    }

    /**
     * Gets the model query of this table
     * @return \ride\library\orm\query\ModelQuery
     */
    public function getModelQuery() {
        return $this->query;
    }

    /**
     * Sets the name of the primary key field to use as identifier value
     * @param string $primaryKeyField
     * @return null
     */
    public function setPrimaryKeyField($primaryKeyField) {
        $this->pkField = $primaryKeyField;
    }

    /**
     * Gets the HTML of this table
     * @param string $part The part to get
     * @return string
     */
    public function getHtml($part = Element::FULL) {
        if (!$this->isPopulated && $this->actions) {
            $decorator = new ValueDecorator(null, new DataOptionDecorator($this->model->getReflectionHelper(), $this->pkField));
            $decorator->setCellClass('option');

            $this->addDecorator($decorator, null, true);
        }

        return parent::getHtml($part);
    }

    /**
     * Processes the export and
     * @param Request $request
     */
    public function processExport(Form $form) {
        if (!parent::processExport($form)) {
            return false;
        }

        if ($this->exportColumnDecorators || $this->exportGroupDecorators) {
            return;
        }

        $this->addExportDecorators();

        $this->values = $this->query->query();

        return true;
    }

    /**
     * Adds the export decorators to this table
     * @return null
     */
    protected function addExportDecorators() {
        $meta = $this->model->getMeta();

        $orm = $this->model->getOrmManager();
        $dataFormatter = $orm->getDataFormatter();

        $booleanDecorator = new BooleanDecorator();
        $dateFormatDecorator = new DateFormatDecorator();

        $fields = $meta->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($field instanceof PropertyField) {
                $type = $field->getType();

                switch ($type) {
                    case 'boolean':
                        $decorator = new ValueDecorator($fieldName, $booleanDecorator);

                        break;
                    case 'date':
                    case 'datetime':
                        $decorator = new ValueDecorator($fieldName, $dateFormatDecorator);

                        break;
                    default:
                        $decorator = new ValueDecorator($fieldName);

                        break;
                }

                $this->addExportDecorator($decorator, new StaticDecorator(ucfirst($fieldName)));
            } elseif ($field instanceof BelongsToField) {
                $relationModel = $orm->getModel($field->getRelationModelName());
                $format = $relationModel->getMeta()->getModelTable()->getDataFormat(DataFormatter::FORMAT_TITLE);

                $dataFormatDecorator = new DataFormatDecorator($dataFormatter, $format);
                $decorator = new ValueDecorator($fieldName, $dataFormatDecorator);

                $this->addExportDecorator($decorator, new StaticDecorator(ucfirst($fieldName)));
            }
        }
    }

    /**
     * Processes and applies the actions, search, order and pagination of this
     * table
     * @param \ride\library\form\Form $form
     * @return null
     */
    public function processForm(Form $form) {
        if (!parent::processForm($form)) {
            return false;
        }

        if (!$this->pageRows || ($this->pageRows && $this->countRows)) {
            $this->values = $this->query->query();
        }

        return true;
    }

    /**
     * Applies the pagination to the model query of this table
     * @return null
     */
    protected function applyPagination() {
        if (!$this->pageRows) {
            return;
        }

        $this->countRows = $this->countTotalRows();

        $this->pages = ceil($this->countRows / $this->pageRows);

        if ($this->page > $this->pages) {
            $this->page = 1;
        }

        $offset = ($this->page - 1) * $this->pageRows;

        $this->query->setLimit($this->pageRows, $offset);
    }

    /**
     * Performs a count on the model query of this table
     * @return integer Number of rows
     */
    protected function countTotalRows() {
        return $this->query->count();
    }

}