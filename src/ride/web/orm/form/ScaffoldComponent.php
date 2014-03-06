<?php

namespace ride\web\orm\form;

use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\library\i18n\translator\Translator;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\ModelField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\Model;
use ride\web\orm\decorator\PropertyDecorator;

use ride\library\reflection\ReflectionHelper;

/**
 * Form to edit ORM data
 */
class ScaffoldComponent extends AbstractComponent {

    /**
     * Model of this form component
     * @var ride\library\orm\model\Model
     */
    protected $model;

    /**
     * Locale for the fetched data
     * @var string
     */
    protected $locale;

    /**
     * Default field recursive depth
     * @var string
     */
    protected $recursiveDepth;

    /**
     * Names of the fields to hide
     * @var array
     */
    protected $hiddenFields;

    /**
     * Names of the fields to omit
     * @var array
     */
    protected $omittedFields;

    /**
     * Names of the fields with their recursive depth
     * @var array
     */
    protected $recursiveFields;

    /**
     * Instance of the data
     * @var mixed
     */
    protected $data;

    /**
     * Constructs a new scaffold form component
     * @param ride\library\orm\model\Model $model
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, Model $model) {
        $this->helper = $reflectionHelper;
        $this->model = $model;
        $this->locale = null;
        $this->recursiveDepth = 1;

        $this->hiddenFields = array(
            ModelTable::PRIMARY_KEY => true,
        );

        $this->omittedFields = array();
        $this->recursiveFields = array();

        $meta = $model->getMeta();

        $hiddenFields = $meta->getOption('scaffold.fields.hide');
        if ($hiddenFields) {
            $hiddenFields = explode(',', $hiddenFields);
            foreach ($hiddenFields as $fieldName) {
                $fieldName = trim($fieldName);

                $this->hiddenFields[$fieldName] = true;
            }
        }

        $omittedFields = $meta->getOption('scaffold.fields.omit');
        if ($omittedFields) {
            $omittedFields = explode(',', $omittedFields);
            foreach ($omittedFields as $fieldName) {
                $fieldName = trim($fieldName);

                $this->omittedFields[$fieldName] = true;
            }
        }
    }

    /**
     * Sets the default recursive depth for relation fields
     * @param integer $recursiveDepth
     * @return null
     */
    public function setDefaultRecursiveDepth($recursiveDepth) {
        $this->recursiveDepth = $recursiveDepth;
    }

    /**
     * Sets the recursive depth for a relation field
     * @param string $name
     * @param integer $recursiveDepth
     * @return null
     */
    public function setFieldRecursiveDepth($name, $recursiveDepth) {
        $this->recursiveFields[$name] = $recursiveDepth;
    }

    /**
     * Sets the locale for the fetched data
     * @param string $locale
     * @return null
     */
    public function setLocale($locale) {
        $this->locale = $locale;
    }

    /**
     * Adds a field to the hidden fields
     * @param string $name Name of the field
     * @return null
     */
    public function hideField($name) {
        $this->hiddenFields[$name] = true;
    }

    /**
     * Adds a field to the omitted fields
     * @param string $name Name of the field
     * @return null
     */
    public function omitField($name) {
        $this->omittedFields[$name] = true;
    }

    /**
     * Gets the name of this component, used when this component is the root
     * of the form to be build
     * @return string
     */
    public function getName() {
        return 'form-' . strtolower($this->model->getName());
    }

    /**
     * Gets the data type for the data of this form component
     * @return string|null A string for a data class, null for an array
     */
    public function getDataType() {
        return $this->model->getMeta()->getDataClassName();
    }

    /**
     * Parse the data to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($data) {
        if (!$data) {
            return null;
        }

        $this->data = $data;

        $result = array();

        $fields = $this->model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            $result[$fieldName] = $this->helper->getProperty($data, $fieldName);
        }

        return $result;
    }

    /**
     * Parse the form values to data of the component
     * @param array $data
     * @return mixed $data
     */
    public function parseGetData(array $data) {
        if (!$this->data) {
            $this->data = $this->model->createData();
        }

        $fields = $this->model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($field->getOption('scaffold.form.omit')) {
                continue;
            }

            if (isset($data[$fieldName])) {
                $this->helper->setProperty($this->data, $fieldName, $data[$fieldName]);
            } else {
                $value = null;
                if ($field instanceof HasManyField) {
                    $value = array();
                } elseif (!$field instanceof RelationField && $field->getType() == 'boolean') {
                    $value = false;
                }

                $this->helper->setProperty($this->data, $fieldName, $value);
            }
        }

        return $this->data;
    }

    /**
     * Prepares the form builder by adding row definitions
     * @param ride\library\html\form\builder\Builder $builder
     * @param array $options Extra options from the controller
     * @return null
     */
    public function prepareForm(FormBuilder $builder, array $options) {
        $translator = $options['translator'];

        $meta = $this->model->getMeta();
        $validationConstraint = $this->model->getValidationConstraint();

        $fields = $meta->getFields();
        foreach ($fields as $fieldName => $field) {
            if (isset($this->omittedFields[$fieldName]) || $field->getOption('scaffold.form.omit')) {
                continue;
            }

            if (isset($this->hiddenFields[$fieldName]) || $field->getOption('scaffold.form.hide')) {
                $builder->addRow($fieldName, 'hidden');

                continue;
            }

            $label = null;
            $description = null;

            $this->getLabel($translator, $field, $label, $description);

            if ($validationConstraint) {
                $filters = $validationConstraint->getFilters($fieldName);
                $validators = $validationConstraint->getValidators($fieldName);
            } else {
                $filters = array();
                $validators = array();
            }

            if (!$field instanceof RelationField) {
                $this->addPropertyRow($builder, $field, $label, $description, $filters, $validators, $options);

                continue;
            }

            $recursiveDepth = $this->recursiveDepth;
            if (isset($this->recursiveFields[$fieldName])) {
                $recursiveDepth = $this->recursiveFields[$fieldName];
            }

            $control = $field->getOption('scaffold.form.control');

            if ($control == 'select' || (!$control && ($recursiveDepth == 0 || $field instanceof BelongsToField))) {
                $this->addSelectRow($builder, $field, $label, $description, $filters, $validators, $options);

                continue;
            }

            $this->addComponentRow($builder, $field, $label, $description, $filters, $validators, $options, $recursiveDepth);
        }
    }

    /**
     * Adds a row for a property field to the form
     * @param ride\library\form\FormBuilder $builder Instance of the form builder
     * @param ride\library\orm\definition\field\ModelField $field Field to add
     * @param string $label Label for the field
     * @param string $description Description of the field
     * @param array $filters Array with the filters for the property
     * @param array $validators Array with the validators for the property
     * @param array $options Extra options from the controller
     * @return null
     */
    protected function addPropertyRow(FormBuilder $builder, ModelField $field, $label, $description, array $filters, array $validators, array $options) {
        $type = $field->getType();
        $rowOptions = array(
            'label' => $label,
            'description' => $description,
            'filters' => $filters,
            'validators' => $validators,
        );

        if ($type == 'float') {
            $type = 'number';
        }

        if ($type == 'file' || $type == 'image') {
            $path = $field->getOption('upload.path');
            if ($path) {
                $path = str_replace('%application%', $options['fileBrowser']->getApplicationDirectory()->getAbsolutePath(), $path);
                $path = str_replace('%public%', $options['fileBrowser']->getPublicDirectory()->getAbsolutePath(), $path);

                $rowOptions['path'] = $options['fileBrowser']->getFileSystem()->getFile($path);
            } else {
                $rowOptions['path'] = $options['fileBrowser']->getApplicationDirectory()->getChild('data');
            }
        }

        $builder->addRow($field->getName(), $type, $rowOptions);
    }

    /**
     * Adds a select row for a relation field to the form
     * @param ride\library\form\FormBuilder $builder Instance of the form builder
     * @param ride\library\orm\definition\field\ModelField $field Field to add
     * @param string $label Label for the field
     * @param string $description Description of the field
     * @param array $filters Array with the filters for the property
     * @param array $validators Array with the validators for the property
     * @param array $options Extra options from the controller
     * @return null
     */
    protected function addSelectRow(FormBuilder $builder, ModelField $field, $label, $description, array $filters, array $validators, array $options) {
        $fieldName = $field->getName();
        $relationModel = $this->model->getRelationModel($fieldName);
        $data = $options['data'];

        $condition = $field->getOption('scaffold.select.condition');
        if ($condition) {
            if (!$data) {
                $data = $relationModel->createData();
            }

            $dataArray = array();

            $meta = $this->model->getMeta();
            $properties = $meta->getProperties();
            $belongsTo = $meta->getBelongsTo();

            foreach ($properties as $name => $propertyField) {
                $dataArray[$name] = $data->$name;
            }

            foreach ($belongsTo as $name => $belongsToField) {
                if (is_object($data->$name)) {
                    $dataArray[$name] = $data->$name->id;
                } else {
                    $dataArray[$name] = $data->$name;
                }
            }

            $query = $relationModel->getDataListQuery();
            $query->addConditionWithVariables($condition, $dataArray);

            $result = $query->query();

            $selectOptions = $relationModel->getDataListResult($result);
        } else {
            $selectOptions = $relationModel->getDataList();
        }

        $isMultiSelect = $field instanceof HasManyField;

        if (!$isMultiSelect) {
            $selectOptions = array('' => '---') + $selectOptions;
        }

        $builder->addRow($fieldName, 'select', array(
            'decorator' => new PropertyDecorator($this->model->getReflectionHelper(), ModelTable::PRIMARY_KEY),
            'options' => $selectOptions,
            'multiple' => $isMultiSelect,
            'label' => $label,
            'description' => $description,
            'filters' => $filters,
            'validators' => $validators,
        ));
    }

    /**
     * Adds a select row for a relation field to the form
     * @param ride\library\form\FormBuilder $builder Instance of the form builder
     * @param ride\library\orm\definition\field\ModelField $field Field to add
     * @param string $label Label for the field
     * @param string $description Description of the field
     * @param array $options Extra options from the controller
     * @param integer $recursiveDepth Number of model to recurse
     * @return null
     */
    protected function addComponentRow(FormBuilder $builder, ModelField $field, $label, $description, array $filters, array $validators, array $options, $recursiveDepth) {
        $fieldName = $field->getName();
        $relationModel = $this->model->getRelationModel($fieldName);

        $formComponent = new self($this->helper, $relationModel);
        $formComponent->setDefaultRecursiveDepth($recursiveDepth - 1);

        if ($field instanceof BelongsToField) {
            $builder->addRow($fieldName, 'component', array(
                'component' => $formComponent,
                'label' => $label,
                'description' => $description,
                'filters' => $filters,
                'validators' => $validators,
            ));
        } else {
            $builder->addRow($fieldName, 'collection', array(
                'type' => 'component',
                'options' => array(
                    'component' => $formComponent,
                ),
                'label' => $label,
                'description' => $description,
                'filters' => $filters,
                'validators' => $validators,
            ));
        }
    }

    /**
     * Gets the label and description from the field
     * @param ride\library\i18n\translation\Translator $translator
     * @param ride\library\orm\definition\field\ModelField $field
     * @param string $label
     * @param string $description
     * @return null
     */
    protected function getLabel(Translator $translator, ModelField $field, &$label, &$description) {
        $label = $field->getOption('label');
        if ($label) {
            $descriptionLabel = $label . '.description';
            $description = $translator->translate($descriptionLabel);
            if ($description == '[' . $descriptionLabel . ']') {
                $description = null;
            }

            $label = $translator->translate($label);
        } else {
            $label = ucfirst($field->getName());
            $description = null;
        }
    }

}