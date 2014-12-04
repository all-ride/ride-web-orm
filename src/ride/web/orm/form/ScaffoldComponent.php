<?php

namespace ride\web\orm\form;

use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\library\i18n\translator\Translator;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\ModelField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\entry\format\EntryFormatter;
use ride\library\orm\model\Model;
use ride\library\reflection\ReflectionHelper;

use ride\web\orm\decorator\FormatDecorator;
use ride\web\orm\decorator\PropertyDecorator;
use ride\web\orm\taxonomy\OrmTagHandler;
use ride\web\WebApplication;

/**
 * Form to edit ORM data
 */
class ScaffoldComponent extends AbstractComponent {

    /**
     * Instance of the web application
     * @var \ride\web\WebApplication
     */
    protected $web;

    /**
     * Model of this form component
     * @var \ride\library\orm\model\Model
     */
    protected $model;

    /**
     * Locale for the fetched data
     * @var string
     */
    protected $locale;

    /**
     * Default field relation depth
     * @var string
     */
    protected $depth;

    /**
     * Names of the fields with their depth
     * @var array
     */
    protected $fieldDepths;

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
     * Instance of the entry
     * @var mixed
     */
    protected $entry;

    /**
     * Constructs a new scaffold form component
     * @param \ride\web\WebApplication $web Instance of the web application
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param \ride\library\orm\model\Model $model
     * @return null
     */
    public function __construct(WebApplication $web, ReflectionHelper $reflectionHelper, Model $model) {
        $this->web = $web;
        $this->reflectionHelper = $reflectionHelper;
        $this->model = $model;
        $this->locale = null;
        $this->depth = 1;

        $this->hiddenFields = array(
            ModelTable::PRIMARY_KEY => true,
        );

        $this->omittedFields = array();
        $this->fieldDepths = array();

        $fields = $this->model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($field->getOption('scaffold.form.omit')) {
                $this->omittedFields[$fieldName] = true;
            }

            if ($field->getOption('scaffold.form.type') == 'hidden') {
                $this->hiddenFields[$fieldName] = true;
            }

            $depth = $field->getOption('scaffold.form.depth');
            if ($depth !== null) {
                $this->fieldDepths[$name] = $depth;
            }
        }
    }

    /**
     * Sets the default field depth for relation fields
     * @param integer $depth
     * @return null
     */
    public function setDepth($depth) {
        $this->depth = $depth;
    }

    /**
     * Sets the depth for a relation field
     * @param string $name Name of the field
     * @param integer $depth Depth of the field
     * @return null
     */
    public function setFieldDepth($name, $depth) {
        $this->fieldDepths[$name] = $depth;
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
        return $this->model->getMeta()->getEntryClassName();
    }

    /**
     * Gets the added row names
     * @return array Array with the field name as key and value
     */
    public function getRowNames() {
        $fields = $this->model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if (isset($this->omittedFields[$fieldName])) {
                unset($fields[$fieldName]);
            } else {
                $fields[$fieldName] = $fieldName;
            }
        }

        return $fields;
    }

    /**
     * Parse the entry to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($entry) {
        if (!$entry) {
            return null;
        }

        $this->entry = $entry;

        $result = array();

        $fields = $this->model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if (isset($this->omittedFields[$fieldName])) {
                continue;
            }

            $result[$fieldName] = $this->reflectionHelper->getProperty($entry, $fieldName);
        }

        return $result;
    }

    /**
     * Parse the form values of an entry of this component
     * @param array $data
     * @return mixed Entry
     */
    public function parseGetData(array $data) {
        if (!$this->entry) {
            $this->entry = $this->model->createEntry();
        }

        $fields = $this->model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if (isset($this->omittedFields[$fieldName])) {
                continue;
            }

            if (isset($data[$fieldName])) {
                $value = $data[$fieldName];
            } else {
                $value = null;
                if ($field instanceof HasManyField) {
                    $value = array();
                } elseif (!$field instanceof RelationField && $field->getType() == 'boolean') {
                    $value = false;
                }
            }

            $this->reflectionHelper->setProperty($this->entry, $fieldName, $value);
        }

        return $this->entry;
    }

    /**
     * Prepares the form builder by adding row definitions
     * @param \ride\library\form\FormBuilder $builder
     * @param array $options Extra options from the controller
     * @return null
     */
    public function prepareForm(FormBuilder $builder, array $options) {
        $meta = $this->model->getMeta();

        $validationConstraint = $this->model->getValidationConstraint();
        if ($validationConstraint && get_class($validationConstraint) !== 'ride\\library\\validation\\constraint\\GenericConstraint') {
            $validationConstraint = null;
        }

        $optionTypes = array('option', 'select', 'object');

        $fields = $meta->getFields();
        foreach ($fields as $fieldName => $field) {
            if (isset($this->omittedFields[$fieldName])) {
                continue;
            }

            if (isset($this->hiddenFields[$fieldName])) {
                $builder->addRow($fieldName, 'hidden');

                continue;
            }

            $type = $field->getOption('scaffold.form.type');
            if ($type) {
                $isOptionType = in_array($type, $optionTypes);
            } else {
                $isOptionType = false;
            }

            $label = null;
            $description = null;
            $this->getLabel($options['translator'], $field, $label, $description);

            if ($validationConstraint) {
                $filters = $validationConstraint->getFilters($fieldName);
                $validators = $validationConstraint->getValidators($fieldName);
            } else {
                $filters = array();
                $validators = array();
            }

            if ($type == 'tags' || (!$isOptionType && !$field instanceof RelationField)) {
                $this->addPropertyRow($builder, $field, $label, $description, $filters, $validators, $options, $type);

                continue;
            }

            if (isset($this->fieldDepths[$fieldName])) {
                $depth = $this->fieldDepths[$fieldName];
            } else {
                $depth = $this->depth;
            }

            if ($isOptionType || (!$type && $depth == 0)) {
                $this->addOptionRow($builder, $field, $label, $description, $filters, $validators, $options, $type);

                continue;
            }

            $this->addComponentRow($builder, $field, $label, $description, $filters, $validators, $options, $depth);
        }
    }

    /**
     * Adds a row for a property field to the form
     * @param \ride\library\form\FormBuilder $builder Instance of the form builder
     * @param \ride\library\orm\definition\field\ModelField $field Field to add
     * @param string $label Label for the field
     * @param string $description Description of the field
     * @param array $filters Array with the filters for the property
     * @param array $validators Array with the validators for the property
     * @param array $options Extra options from the controller
     * @param string $type Type of the row, defaults to the field type
     * @return null
     */
    protected function addPropertyRow(FormBuilder $builder, ModelField $field, $label, $description, array $filters, array $validators, array $options, $type = null) {
        if (!$type) {
            $type = $field->getType();
        }

        $rowOptions = array(
            'label' => $label,
            'description' => $description,
            'filters' => $filters,
            'attributes' => array(),
        );

        if ($type == 'float') {
            $type = 'number';

            $rowOptions['attributes']['step'] = 'any';
        }

        if ($type != 'label') {
            $rowOptions['validators'] = $validators;
        }

        if ($type == 'file' || $type == 'image') {
            $path = $field->getOption('upload.path');
            if ($path) {
                $path = str_replace('%application%', $options['fileBrowser']->getApplicationDirectory()->getAbsolutePath(), $path);
                $path = str_replace('%public%', $options['fileBrowser']->getPublicDirectory()->getAbsolutePath(), $path);

                $rowOptions['path'] = $options['fileBrowser']->getFileSystem()->getFile($path);
            }
        }

        if ($type == 'tags') {
            $urlSuffix = '?match[name]=%term%';

            $vocabulary = $field->getOption('taxonomy.vocabulary');
            if ($vocabulary) {
                if (is_numeric($vocabulary)) {
                    $urlSuffix .= '&filter[vocabulary]=' . $vocabulary;
                } else {
                    $urlSuffix .= '&filter[vocabulary.slug]=' . $vocabulary;
                }
            }

            $rowOptions['handler'] = new OrmTagHandler($this->model->getOrmManager(), $vocabulary);
            $rowOptions['autocomplete.url'] = $this->web->getUrl('api.orm.list', array('model' => 'TaxonomyTerm')) . $urlSuffix;
        }

        $builder->addRow($field->getName(), $type, $rowOptions);
    }

    /**
     * Adds a option row for a (relation) field to the form
     * @param \ride\library\form\FormBuilder $builder Instance of the form builder
     * @param \ride\library\orm\definition\field\ModelField $field Field to add
     * @param string $label Label for the field
     * @param string $description Description of the field
     * @param array $filters Array with the filters for the property
     * @param array $validators Array with the validators for the property
     * @param array $options Extra options from the controller
     * @param string $type Type detected to use as widget or row type
     * @return null
     */
    protected function addOptionRow(FormBuilder $builder, ModelField $field, $label, $description, array $filters, array $validators, array $options, $type) {
        $fieldName = $field->getName();

        $rowOptions = array(
            'label' => $label,
            'description' => $description,
            'filters' => $filters,
            'validators' => $validators,
        );

        if (!$field instanceof PropertyField) {
            $entry = $options['data'];

            $relationModel = $this->model->getRelationModel($fieldName);
            $relationMeta = $relationModel->getMeta();

            $query = $relationModel->createQuery($this->locale);
            $query->setFetchUnlocalized(true);

            $condition = $field->getOption('scaffold.form.condition');
            if ($condition) {
                if (!$entry) {
                    $entry = $relationModel->createEntry();
                }

                $variables = array();

                $reflectionHelper = $this->model->getReflectionHelper();
                $meta = $this->model->getMeta();
                $properties = $meta->getProperties();
                $belongsTo = $meta->getBelongsTo();

                foreach ($properties as $name => $propertyField) {
                    $variables[$name] = $reflectionHelper->getProperty($data, $name);
                }

                foreach ($belongsTo as $name => $belongsToField) {
                    $value = $reflectionHelper->getProperty($entry, $name);
                    if (!$value) {
                        continue;
                    }

                    if (is_object($value)) {
                        $variables[$name] = $value->getId();
                    } else {
                        $variables[$name] = $value;
                    }
                }

                $query->addConditionWithVariables($condition, $variables);
            }

            $orderField = $relationMeta->getOption('order.field');
            if ($orderField) {
                $query->addOrderBy('{' . $orderField . '} ' . $relationMeta->getOption('order.direction', 'ASC'));
            }

            $selectOptions = $query->query();

            $isMultiSelect = $field instanceof HasManyField;
            if (!$isMultiSelect && $type != 'option') {
                $selectOptions = array('' => null) + $selectOptions;
            }

            $entryFormatter = $this->model->getOrmManager()->getEntryFormatter();
            $format = $relationModel->getMeta()->getFormat(EntryFormatter::FORMAT_TITLE);

            if ($type == 'object') {
                $type = null;
            }

            $rowOptions['decorator'] = new FormatDecorator($entryFormatter, $format);
            $rowOptions['value'] = 'id';
            $rowOptions['widget'] = $field->getOption('scaffold.form.widget', $type);

            $type = 'object';
        } else {
            $selectOptions = $field->getOption('scaffold.form.options');
            if ($selectOptions) {
                $selectOptions = json_decode($selectOptions, true);
                foreach ($selectOptions as $index => $value) {
                    $selectOptions[$index] = $options['translator']->translate($value);
                }
            } else {
                $selectOptions = array();
            }

            $isMultiSelect = false;
        }

        $rowOptions['multiple'] = $isMultiSelect;
        $rowOptions['options'] = $selectOptions;

        $builder->addRow($fieldName, $type, $rowOptions);
    }

    /**
     * Adds a select row for a relation field to the form
     * @param \ride\library\form\FormBuilder $builder Instance of the form builder
     * @param \ride\library\orm\definition\field\ModelField $field Field to add
     * @param string $label Label for the field
     * @param string $description Description of the field
     * @param array $options Extra options from the controller
     * @param integer $depth Depth for the relation fields
     * @return null
     */
    protected function addComponentRow(FormBuilder $builder, ModelField $field, $label, $description, array $filters, array $validators, array $options, $depth) {
        $fieldName = $field->getName();
        $relationModel = $this->model->getRelationModel($fieldName);
        $relationMeta = $this->model->getMeta()->getRelationMeta($fieldName);

        $formComponent = new self($this->web, $this->reflectionHelper, $relationModel);
        $formComponent->setDepth($depth - 1);
        if ($relationMeta && !$relationMeta->isHasManyAndBelongsToMany()) {
            $relationField = $relationMeta->getForeignKey();

            if (!is_array($relationField)) {
                $relationField = array($relationField);
            }

            foreach ($relationField as $omitField) {
                $formComponent->omitField($omitField);
            }
        }

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
     * @param \ride\library\i18n\translator\Translator $translator
     * @param \ride\library\orm\definition\field\ModelField $field
     * @param string $label
     * @param string $description
     * @return null
     */
    protected function getLabel(Translator $translator, ModelField $field, &$label, &$description) {
        $label = $field->getOption('label.name');
        if ($label) {
            $label = $translator->translate($label);
        } else {
            $label = ucfirst($field->getName());
        }

        $description = $field->getOption('label.description');
        if ($description) {
            $description = $translator->translate($description);
        }
    }

}
