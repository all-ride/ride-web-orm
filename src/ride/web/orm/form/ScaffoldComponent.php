<?php

namespace ride\web\orm\form;

use ride\library\decorator\EntryFormatDecorator;
use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\library\i18n\translator\Translator;
use ride\library\log\Log;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\ModelField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\Model;
use ride\library\reflection\ReflectionHelper;
use ride\library\security\SecurityManager;
use ride\library\validation\validator\SizeValidator;

use ride\service\OrmService;

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
     * Instance of the reflection helper
     * @var \ride\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Instance of the security manager
     * @var \ride\library\security\SecurityManager
     */
    protected $securityManager;

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
     * Instance of the log
     * @var \ride\library\log\Log
     */
    protected $log;

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
     * Array with the tabs
     * @var array
     */
    protected $tabs;

    /**
     * Constructs a new scaffold form component
     * @param \ride\web\WebApplication $web Instance of the web application
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param \ride\library\orm\model\Model $model
     * @return null
     */
    public function __construct(WebApplication $web, SecurityManager $securityManager, OrmService $ormService, Model $model) {
        $this->web = $web;
        $this->reflectionHelper = $model->getReflectionHelper();
        $this->securityManager = $securityManager;
        $this->ormService = $ormService;
        $this->model = $model;
        $this->locale = null;
        $this->depth = 1;

        $this->hiddenFields = array(
            ModelTable::PRIMARY_KEY => true,
        );

        $this->omittedFields = array();
        $this->fieldDepths = array();
        $this->tabs = array();
        $this->proxy = array();

        $meta = $this->model->getMeta();

        $tabs = $meta->getOption('scaffold.form.tabs');
        if ($tabs) {
            $tabs = explode(',', $tabs);
            foreach ($tabs as $tab) {
                $this->tabs[$tab] = array(
                    'translation' => $meta->getOption('scaffold.form.tab.' . $tab, 'label.' . $tab),
                    'rows' => array(),
                );
            }
        }

        $fields = $meta->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($field->getOption('scaffold.form.omit')) {
                $this->omittedFields[$fieldName] = true;
            }

            if ($field->getOption('scaffold.form.type') == 'hidden') {
                $this->hiddenFields[$fieldName] = true;
            }

            $depth = $field->getOption('scaffold.form.depth');
            if ($depth !== null) {
                $this->fieldDepths[$fieldName] = $depth;
            }

            $permission = $field->getOption('scaffold.form.permission');
            if ($permission && !$this->securityManager->isPermissionGranted($permission)) {
                $this->omittedFields[$fieldName] = true;
            }

            if (isset($this->omittedFields[$fieldName]) || isset($this->hiddenFields[$fieldName])) {
                continue;
            }

            $tab = $field->getOption('scaffold.form.tab');
            if (isset($this->tabs[$tab])) {
                $this->tabs[$tab]['rows'][$fieldName] = $fieldName;
            }
        }
    }

    /**
     * Sets the instance of the log
     * @param \ride\library\log\Log $log Instance of the log
     * @return null
     */
    public function setLog(Log $log) {
        $this->log = $log;
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
     * Gets the tabs for this component
     * @return array Array with the machine name of the tab as key and an array
     * with the translation and rows elements
     */
    public function getTabs() {
        return $this->tabs;
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

            $value = $this->reflectionHelper->getProperty($entry, $fieldName);
            if (isset($this->proxy[$fieldName])) {
                if (is_array($value)) {
                    foreach ($value as $index => $indexValue) {
                        $value[$index] = $indexValue->getId();
                    }
                } elseif ($value) {
                    $value = $value->getId();
                }
            }

            $result[$fieldName] = $value;
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

        $meta = $this->model->getMeta();

        if ($this->locale && $meta->isLocalized()) {
            $this->entry->setLocale($this->locale);
        }

        $fields = $meta->getFields();
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

            if (isset($this->proxy[$fieldName])) {
                $relationModel = $this->model->getRelationModel($fieldName);

                if (is_array($value)) {
                    foreach ($value as $index => $indexValue) {
                        $value[$index] = $relationModel->createProxy($indexValue, $this->locale);
                    }
                } elseif ($value) {
                    $value = $relationModel->createProxy($value, $this->locale);
                } else {
                    $value = null;
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
        if ($validationConstraint) {
            $validationConstraintClass = get_class($validationConstraint);
            switch ($validationConstraintClass) {
                case 'ride\\library\\orm\\entry\\constraint\\EntryConstraint':
                case 'ride\\library\\validation\\constraint\\GenericConstraint':
                    break;
                default:
                    $validationConstraint = null;
            }
        }

        $optionTypes = array('option', 'select', 'object');
        $propertyTypes = array('tags', 'assets', 'label', 'geo');

        $fields = $meta->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($this->log) {
                $this->log->logDebug('Generating field ' . $fieldName);
            }

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

            if (in_array($type, $propertyTypes) || (!$isOptionType && !$field instanceof RelationField)) {
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

        $fieldDependency = $this->getFieldDependency($field);
        if ($fieldDependency) {
            $rowOptions['attributes']['class'] = $fieldDependency;
        }

        if ($type == 'boolean') {
            $rowOptions['attributes']['data-toggle-dependant'] = 'option-' . $field->getName();
        } elseif ($type == 'float') {
            $type = 'number';

            $rowOptions['attributes']['step'] = 'any';
        } elseif ($type == 'date') {
            $rowOptions['round'] = true;
        } elseif ($type == 'label') {
            $decorator = $field->getOption('scaffold.form.decorator');
            if ($decorator) {
                if (strpos($decorator, '#')) {
                    list($interface, $id) = explode('#', $decorator, 2);
                } else {
                    $interface = $decorator;
                    $id = null;
                }

                $dependencyInjector = $this->model->getOrmManager()->getDependencyInjector();
                $rowOptions['decorator'] = $dependencyInjector->get($interface, $id);
                $rowOptions['html'] = true;
            } elseif ($field instanceof RelationField) {
                $orm = $this->model->getOrmManager();
                $relationModel = $orm->getModel($field->getRelationModelName());

                $entryFormatter = $orm->getEntryFormatter();
                $format = $relationModel->getMeta()->getFormat('title');

                $decorator = $field->getOption('scaffold.form.decorator');

                $rowOptions['decorator'] = new EntryFormatDecorator($entryFormatter, $format);
            }
        } elseif ($type == 'file' || $type == 'image') {
            $path = $field->getOption('upload.path');
            if ($path) {
                $path = str_replace('%application%', $options['fileBrowser']->getApplicationDirectory()->getAbsolutePath(), $path);
                $path = str_replace('%public%', $options['fileBrowser']->getPublicDirectory()->getAbsolutePath(), $path);

                $rowOptions['path'] = $options['fileBrowser']->getFileSystem()->getFile($path);
            }
        } elseif ($type == 'tags') {
            $urlSuffix = '?list=1&fields[taxonomy-terms]=&filter[match][name]=%term%';

            $vocabulary = $field->getOption('taxonomy.vocabulary');
            if ($vocabulary) {
                if (is_numeric($vocabulary)) {
                    $urlSuffix .= '&filter[exact][vocabulary]=' . $vocabulary;
                } else {
                    $urlSuffix .= '&filter[exact][vocabulary.slug]=' . $vocabulary;
                }
            }

            $rowOptions['handler'] = new OrmTagHandler($this->model->getOrmManager(), $vocabulary);
            $rowOptions['autocomplete.url'] = $this->web->getUrl('api.orm.entry.index', array('type' => 'taxonomy-terms')) . $urlSuffix;
            $rowOptions['autocomplete.type'] = 'jsonapi';
            $rowOptions['locale'] = $this->locale;
        } elseif ($type == 'assets') {
            $rowOptions['multiple'] = $field instanceof HasManyField;

            $folder = $field->getOption('assets.folder');
            if ($folder) {
                $rowOptions['folder'] = $folder;
            }
        } elseif ($type == 'geo') {
            $geoType = $field->getOption('geo.type');
            if (strpos($geoType, ',')) {
                $geoType = explode(',', $geoType);
            }

            $rowOptions['multiple'] = $field instanceof HasManyField;
            $rowOptions['filter'] = $field->getOption('geo.filter');
            $rowOptions['type'] = $geoType;
            $rowOptions['locale'] = $this->locale;
        }

        if ($type != 'label') {
            $rowOptions['validators'] = $validators;
        }

        if ($type == 'tags' || $type == 'geo') {
            foreach ($validators as $validator) {
                if ($validator instanceof SizeValidator) {
                    $rowOptions['autocomplete.max.items'] = $validator->getOption('maximum', 0);
                }
            }
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
        $options = $this->ormService->getFieldInputOptions($this->model, $field, $options['translator'], $options['data']);

        $rowOptions = array(
            'label' => $label,
            'description' => $description,
            'options'  => $options,
            'attributes' => array(
                'data-toggle-dependant' => 'option-' . $field->getName(),
            ),
            'filters' => $filters,
            'validators' => $validators,
            'widget' => 'option',
        );

        $fieldDependency = $this->getFieldDependency($field);
        if ($fieldDependency) {
            $rowOptions['attributes']['class'] = $fieldDependency;
        }

        if (!$field instanceof PropertyField) {
            if ($type == 'object') {
                $type = null;
            }

            $rowOptions['widget'] = $field->getOption('scaffold.form.widget', $type);
            $rowOptions['multiple'] = $field instanceof HasManyField;

            $type = 'option';

            $this->proxy[$field->getName()] = true;
        } else {
            $rowOptions['multiple'] = false;
        }

        if (!$rowOptions['multiple'] && $rowOptions['widget'] != 'option') {
            $rowOptions['options'] = array('' => null) + $rowOptions['options'];
        }

        $builder->addRow($field->getName(), $type, $rowOptions);
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

        $formComponent = new self($this->web, $this->securityManager, $this->ormService, $relationModel);
        $formComponent->setDepth($depth - 1);
        $formComponent->setLocale($this->locale);

        if ($relationMeta && !$relationMeta->isHasManyAndBelongsToMany()) {
            $relationField = $relationMeta->getForeignKey();

            if (!is_array($relationField)) {
                $relationField = array($relationField);
            }

            foreach ($relationField as $omitField) {
                $formComponent->omitField($omitField);
            }
        }

        $rowOptions = array(
            'label' => $label,
            'description' => $description,
            'filters' => $filters,
            'attributes' => array(),
        );

        $fieldDependency = $this->getFieldDependency($field);
        if ($fieldDependency) {
            $rowOptions['attributes']['class'] = $fieldDependency;
        }

        if ($field instanceof BelongsToField) {
            $type = 'component';
            $owOptions['component'] = $formComponent;
        } else {
            $isOrdered = false;
            if ($field instanceof HasManyField) {
                $isOrdered = $field->isOrdered();
            }

            $type = 'collection';
            $rowOptions['type'] = 'component';
            $rowOptions['options'] = array(
                'component' => $formComponent,
            );
            $rowOptions['order'] = $isOrdered;
        }

        $builder->addRow($fieldName, $type, $rowOptions);
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

    protected function getFieldDependency(ModelField $field) {
        $dependant = $field->getOption('scaffold.form.dependant');
        if (!$dependant) {
            return null;
        }

        if (strpos($dependant, '-')) {
            list($dependantField, $dependantValue) = explode('-', $dependant, 2);
        } else {
            $dependantField = $dependant;
            $dependantValue = '1';
        }

        return 'option-' . $dependantField . ' option-' . $dependantField . '-' . $dependantValue;
    }

}
