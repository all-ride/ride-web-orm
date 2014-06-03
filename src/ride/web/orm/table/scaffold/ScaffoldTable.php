<?php

namespace ride\web\orm\table\scaffold;

use ride\library\i18n\translator\Translator;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable as OrmModelTable;
use ride\library\orm\model\Model;

/**
 * Table for a scaffolded model
 */
class ScaffoldTable extends ModelTable {

    /**
     * Array with the field names to search in
     * @var array
     */
    private $searchFields;

    /**
     * Array with the statements for the order functionality
     * @var array
     */
    private $orderStatements;

    /**
     * Constructs a new scaffold table
     * @param \ride\library\orm\model\Model $model Model for the data of the
     * table
     * @param \ride\library\i18n\translator\Translator $translator Instance of
     * the translator
     * @param string $locale Code of the data locale
     * @param boolean|array $search False to disable search, True to search all
     * properties or an array with the fields to query
     * @param boolean|array $order False to disable order, True to order all
     * properties or an array with the fields to order
     * @return null
     */
    public function __construct(Model $model, Translator $translator, $locale = 'en', $search = true, $order = true) {
        parent::__construct($model, $locale);

        $this->translator = $translator;

        $meta = $model->getMeta();

        if ($meta->isLocalized()) {
            $this->query->setFetchUnlocalized(true);
            $this->query->setWillAddIsLocalizedOrder(true);
        }

        if ($search) {
            if ($search && !is_array($search)) {
                $search = array();
            }

            $this->setSearchFields($search);
        }

        if ($order) {
            if ($order && !is_array($order)) {
                $order = array();
            }

            $this->setOrderFields($order);
        }

        unset($this->translator);
    }

    /**
     * Enables the search on this table and sets the provided fields as search query fields
     * @param array $fieldNames Array with the field names to search in. If none provided, all the properties of the model will be queried
     * @return null
     */
    protected function setSearchFields(array $fieldNames) {
        if ($fieldNames) {

            $this->searchFields = $fieldNames;
        } else {
            $this->searchFields = array();

            $fields = $this->model->getMeta()->getFields();
            foreach ($fields as $field) {
                if (!$field->getOption('scaffold.search')) {
                    continue;
                }

                $this->searchFields[$field->getName()] = true;
            }
        }

        if ($this->searchFields) {
            $this->setHasSearch(true);
        }
    }

    /**
     * Enables the order on this table and sets the provided property fields as order fields
     * @param array $fieldNames Array with the field names to search in. If none provided, all the properties of the model will be added
     * @return null
     */
    protected function setOrderFields(array $fieldNames) {
        $meta = $this->model->getMeta();

        if (!$fieldNames) {
            $fieldNames = $this->getModelPropertyNames();
        }

        $this->orderStatements = array();

        foreach ($fieldNames as $index => $fieldName) {
            $callback = array($this, 'addOrderToQuery');

            if (is_array($fieldName)) {
                if (!isset($fieldName['ASC'])) {
                    throw new OrmException('Provided order method ' . $index . ' has no ASC statement');
                }

                if (!isset($fieldName['DESC'])) {
                    throw new OrmException('Provided order method ' . $index . ' has no DESC statement');
                }

                $label = $index;
                $orderStatements = $fieldName;
            } else {
                $field = $meta->getField($fieldName);

                if (!$field->getOption('scaffold.order') || $field instanceof RelationField) {
                    continue;
                }

                $label = $field->getOption('label');
                if ($this->translator && $label) {
                    $label = $this->translator->translate($label);
                } else {
                    $label = ucfirst($fieldName);
                }

                $orderStatements = array(
                    'ASC' => '{' . $fieldName . '} ASC',
                    'DESC' => '{' . $fieldName . '} DESC',
                );
            }

            $this->orderStatements[$label] = $orderStatements;

            $this->addOrderMethod($label, $callback, $callback, $label);
        }

        // set initial order
        $order = $meta->getOption('scaffold.query.order');
        if (!$order) {
            return;
        }

        if (strpos($order, ' ') !== false) {
            list($orderFieldName, $orderDirection) = explode(' ', $order, 2);
            $orderDirection = strtoupper($orderDirection);
        } else {
            $orderFieldName = $order;
            $orderDirection = 'ASC';
        }

        $trimmedOrderFieldName = substr($orderFieldName, 1, -1);
        if ($orderFieldName == '{' . $trimmedOrderFieldName . '}') {
            $orderFieldName = $trimmedOrderFieldName;
        }

        if ($orderDirection != 'ASC' && $orderDirection != 'DESC') {
            $orderDirection = 'ASC';
        }

        $field = $meta->getField($orderFieldName);
        $label = $field->getOption('label');
        if ($this->translator && $label) {
            $orderMethod = $this->translator->translate($label);
        } else {
            $orderMethod = ucfirst($orderFieldName);
        }

        $this->setOrderMethod($orderMethod);
        $this->setOrderDirection($orderDirection);
    }

    /**
     * Gets the names of the model fields usefull for the search or the order functionality
     * @return array Array with the field name as key and the field object as value
     */
    private function getModelPropertyNames() {
        $meta = $this->model->getMeta();
        $fields = $meta->getProperties();

        unset($fields[OrmModelTable::PRIMARY_KEY]);

        if (isset($fields[0])) {
            return $fields;
        } else {
            return array_keys($fields);
        }
    }

    /**
     * Adds the condition for the search query to the model query of this table
     * @return null
     */
    protected function applySearch() {
        if (empty($this->searchQuery) || empty($this->searchFields)) {
            return;
        }

        $value = '%' . $this->searchQuery . '%';

        $condition = '';
        foreach ($this->searchFields as $fieldName => $null) {
            $condition .= ($condition == '' ? '' : ' OR ') . '{' . $fieldName . '} LIKE %1%';
        }

        $this->query->addCondition($condition, $value);
    }

    /**
     * Adds the order by to the query of this table
     * @param array $values Values of the table
     * @param string $label Label of the order method
     * @return null
     */
    public function addOrderToQuery(array $values, $label) {
        if (!isset($this->orderStatements[$label])) {
            throw new OrmException($label . ' not found in the order method list');
        }

        $direction = strtoupper($this->getOrderDirection());

        $this->query->addOrderBy($this->orderStatements[$label][$direction]);
    }

}
