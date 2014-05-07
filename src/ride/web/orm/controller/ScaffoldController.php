<?php

namespace ride\web\orm\controller;

use ride\library\form\exception\FormException;
use ride\library\form\Form;
use ride\library\html\table\FormTable;
use ride\library\http\Header;
use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\data\format\DataFormatter;
use ride\library\orm\model\Model;
use ride\library\security\exception\UnauthorizedException;
use ride\library\validation\exception\ValidationException;

use ride\web\base\controller\AbstractController;
use ride\web\orm\form\ScaffoldComponent;
use ride\web\orm\table\scaffold\decorator\DataDecorator;
use ride\web\orm\table\scaffold\decorator\LocalizeDecorator;
use ride\web\orm\table\scaffold\ScaffoldTable;

/**
 * Controller to scaffold a model
 */
class ScaffoldController extends AbstractController {

    /**
     * Action to add data
     * @var string
     */
    const ACTION_ADD = 'add';

    /**
     * Action to the detail of data
     * @var string
     */
    const ACTION_DETAIL = 'detail';

    /**
     * Action to edit data
     * @var string
     */
    const ACTION_EDIT = 'edit';

    /**
     * Action to export the data
     * @var string
     */
    const ACTION_EXPORT = 'export';

    /**
     * Action for the data index
     * @var string
     */
    const ACTION_INDEX = 'index';

    /**
     * Argument name for the export action
     * @var string
     */
    const PARAMETER_EXPORT = 'export';

    /**
     * Argument name of the order method
     * @var string
     */
    const PARAMETER_ORDER_METHOD = 'order';

    /**
     * Argument name of the order direction
     * @var string
     */
    const PARAMETER_ORDER_DIRECTION = 'direction';

    /**
     * Argument name of the page
     * @var string
     */
    const PARAMETER_PAGE = 'page';

    /**
     * Argument name of the number of rows per page
     * @var string
     */
    const PARAMETER_ROWS = 'rows';

    /**
     * Argument name of the search query
     * @var string
     */
    const PARAMETER_SEARCH_QUERY = 'search';

    /**
     * Argument name of the referer
     * @var string
     */
    const PARAMETER_REFERER = 'referer';

    /**
     * Translation key for the not deletable error
     * @var string
     */
    const TRANSLATION_ERROR_DELETABLE = 'orm.error.permission.delete';

    /**
     * Translation key for the not readable error
     * @var string
     */
    const TRANSLATION_ERROR_READABLE = 'orm.error.permission.read';

    /**
     * Translation key for the not writable error
     * @var string
     */
    const TRANSLATION_ERROR_WRITABLE = 'orm.error.permission.write';

    /**
     * Model option for the title of the scaffolding
     * @var string
     */
    const OPTION_TITLE = 'scaffold.title';

    /**
     * Model option for the title of the add button
     * @var string
     */
    const OPTION_TITLE_ADD = 'scaffold.title.add';

    /**
     * Model option for a condition of the overview query
     * @var string
     */
    const OPTION_CONDITION = 'scaffold.query.condition';

    /**
     * Route for the index action
     * @var string
     */
    const ROUTE_INDEX = 'system.orm.scaffold.index';

    /**
     * Route for the add action
     * @var string
     */
    const ROUTE_ADD = 'system.orm.scaffold.action';

    /**
     * Route for the edit action
     * @var string
     */
    const ROUTE_EDIT = 'system.orm.scaffold.action.data';

    /**
     * Route for the export action
     * @var string
     */
    const ROUTE_EXPORT = 'system.orm.scaffold.export';

    /**
     * The model for scaffolding
     * @var \ride\library\orm\model\Model
     */
    protected $model;

    /**
     * Name of the field to query records on (id, slug, ...)
     * @var string
     */
    protected $pkField;

    /**
     * Flag to see if the model is localized
     * @var boolean
     */
    protected $isLocalized;

    /**
     * Code of the working locale
     * @var string
     */
    protected $locale;

    /**
     * Boolean to enable or disable the search functionality, an array of field names to query is also allowed to enable the search
     * @var boolean|array
     */
    protected $search;

    /**
     * Boolean to enable or disable the order functionality, an array of field names to order is also allowed to enable the order
     * @var boolean|array
     */
    protected $order;

    /**
     * Variable to set the initial order method
     * @var string
     */
    protected $orderMethod;

    /**
     * Variable to set the initial order direction
     * @var string
     */
    protected $orderDirection;

    /**
     * Boolean to enable or disable the pagination functionality, an array of pagination options is also allowed to enable the pagination
     * @var boolean|array
     */
    protected $pagination;

    /**
     * Recursive depth used when retrieving data
     * @var integer|null
     */
    protected $recursiveDepth;

    /**
     * Translation key for the add title
     * @var string
     */
    protected $translationAdd;

    /**
     * Translation key for the general title
     * @var string
     */
    protected $translationTitle;

    /**
     * Routes for actions
     * @var array
     */
    protected $routes;

    /**
     * Constructs a new scaffold controller
     * @param string $modelName Name of the model to scaffold, if not provided the name will be retrieved from the class name
     * @param boolean|array $search Boolean to enable or disable the search functionality, an array of field names to query is also allowed to enable the search
     * @param boolean|array $order Boolean to enable or disable the order functionality, an array of field names to order is also allowed to enable the order
     * @param boolean|array $pagination Boolean to enable or disable the pagination functionality, an array of pagination options is also allowed to enable the pagination
     * @return null
     */
    public function __construct(Model $model, $search = true, $order = true, $pagination = true) {
        if ($pagination || !is_array($pagination)) {
            $pagination = array(5, 10, 25, 50, 100, 250, 500);
        }

        $meta = $model->getMeta();

        $this->model = $model;
        $this->pkField = ModelTable::PRIMARY_KEY;

        $this->recursiveDepth = $meta->getOption('scaffold.recursive.depth', 1);
        $this->isLocalized = $meta->isLocalized();

        $this->pagination = $pagination;
        $this->search = $search;
        $this->order = $order;
        $this->orderMethod = null;
        $this->orderDirection = null;

        $this->routes = array(
            self::ACTION_INDEX => self::ROUTE_INDEX,
            self::ACTION_ADD => self::ROUTE_ADD,
            self::ACTION_EDIT => self::ROUTE_EDIT,
            self::ACTION_EXPORT => self::ROUTE_EXPORT,
        );

        $this->translationTitle = $meta->getOption(self::OPTION_TITLE);
        $this->translationAdd = $meta->getOption(self::OPTION_TITLE_ADD);
    }

    /**
     * Sets the route for a action
     * @param string $action Action of the route
     * @param string $routeId Id of the route
     * @return null
     */
    public function setRoute($action, $routeId) {
        $this->routes[$action] = $routeId;
    }

    /**
     * Hook before every action
     * @return boolean True to perform the action, false otherwise
     */
    public function preAction() {
        $this->orm = $this->dependencyInjector->get('ride\\library\\orm\\OrmManager');

        return true;
    }

    /**
     * Processes and sets a data table view to the response
     * @return null
     */
    public function indexAction(I18n $i18n, $locale = null) {
    	if (!$this->isReadable()) {
            throw new UnauthorizedException();
    	}

    	// resolve locale
    	if (!$locale) {
    	    $this->locale = $i18n->getLocale()->getCode();

    	    if ($this->model->getMeta()->isLocalized()) {
                $this->response->setRedirect($this->getAction(self::ACTION_INDEX, array('locale' => $this->locale)));

                return;
    	    }
    	} else {
    	    $this->locale = $i18n->getLocale($locale)->getCode();
    	}

        // handle table
        if ($this->orderMethod === null && $this->orderDirection === null) {
            $meta = $this->model->getMeta();

            $this->orderMethod = $meta->getOption('scaffold.order.field');
            $this->orderDirection = $meta->getOption('scaffold.order.direction');

            if ($this->orderMethod) {
                $field = $meta->getField($this->orderMethod);

                $label = $field->getOption('label');
                if ($label) {
                    $this->orderMethod = $this->getTranslator()->translate($label);
                } else {
                    $this->orderMethod = ucfirst($field->getName());
                }
            }
        }

        $baseUrl = $this->getAction(self::ACTION_INDEX);
        $table = $this->getTable($this->getAction(self::ACTION_DETAIL));

        $form = $this->processTable($table, $baseUrl, 10, $this->orderMethod, $this->orderDirection);
        if ($this->response->willRedirect() || $this->response->getView()) {
            return;
        }

        // set view
        $this->setIndexView($table, $form, $i18n->getLocaleCodeList(), $locale);
    }

    /**
     * Sets the index view for the scaffolding to the response
     * @param \ride\library\html\table\FormTable $table Table with the model data
     * @param \ride\library\form\Form $form Form of the table
     * @param array $locales Available locale codes
     * @param string $locale Code of the current locale
     * @param array $actions Array with the URL of the action as key and the label for the action as value
     * @return null
     */
    protected function setIndexView(FormTable $table, Form $form, array $locales, $locale, array $actions = null) {
        $meta = $this->model->getMeta();
        $title = $this->getViewTitle();

        $viewActions = array();

        $addAction = $this->getAction(self::ACTION_ADD);
        if ($this->isWritable(null, false) && $addAction) {
            $translator = $this->getTranslator();

            $addAction .=  '?referer=' . urlencode($this->request->getUrl());

            $addTranslation = $this->translationAdd;
            if (!$addTranslation) {
                $addTranslation = 'button.add';
            }

            $viewActions[$addAction] = $translator->translate($addTranslation);
        }

        if ($actions) {
            $viewActions += $actions;
        }

        $exportActions = $this->dependencyInjector->getAll('ride\\library\\html\\table\\export\\ExportFormat');
        foreach ($exportActions as $extension => $exportFormat) {
            $exportActions[$extension] = $this->getAction(self::ACTION_EXPORT, array('format' => $extension));
        }

        $variables = array(
            'meta' => $meta,
            'form' => $form->getView(),
            'table' => $table,
            'actions' => $viewActions,
            'exports' => $exportActions,
            'title' => $title,
            'localizeUrl' => null,
        );

        if ($this->model->getMeta()->isLocalized()) {
            $variables['locales'] = $locales;
            $variables['locale'] = $locale;
            $variables['localizeUrl'] = $this->getAction(self::ACTION_INDEX, array('locale' => '%locale%'));
        }

        $this->setTemplateView('orm/scaffold/index', $variables);
    }

    /**
     * Performs an export of the provided table and sets the view of the export to the response
     * @param \ride\library\html\table\FormTable $table Table to get the export of
     * @param string $extension The extension for the export
     * @return null
     */
    public function exportAction(i18n $i18n, $locale = null, $format = null) {
        if (!$locale) {
            $locale = $i18n->getLocale()->getCode();

            $this->response->setRedirect($this->getAction(self::ACTION_EXPORT, array('locale' => $locale)));

            return;
        } else {
            $this->locale = $i18n->getLocale($locale)->getCode();
        }

        try {
            $export = $this->dependencyInjector->get('ride\\library\\html\\table\\export\\ExportFormat', $format);
        } catch (DependencyException $exception) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '500');
        ini_set('max_input_time', '500');

        $title = $this->model->getName();

        $export->initExport($title);

        $table = $this->getTable();

        $this->processExport($table);

        $table->populateExport($export);

        $file = $export->finishExport();

        $this->setDownloadView($file, $title . '.xlsx', true);
    }

    /**
     * Processes the export action
     * @param \ride\library\html\table\FormTable $table Table of the index view
     * @return null
     */
    protected function processExport(FormTable $table) {
        $table->processExport($this->request);
    }

    /**
     * Gets a data table for the model
     * @param string $formAction URL where the table form will point to
     * @return \ride\library\html\table\FormTable
     */
    protected function getTable($detailAction = null) {
        if (!$detailAction) {
            $detailAction = $this->getAction(self::ACTION_EDIT, array('id' => '%id%'));
        }
        $detailAction .= '?referer=' . urlencode($this->request->getUrl());

        $table = new ScaffoldTable($this->model, $this->getTranslator(), $this->locale, $this->search, $this->order);
        $table->setPaginationOptions($this->pagination);
        $table->setPrimaryKeyField($this->pkField);

        $this->addTableDecorators($table, $detailAction);

        if ($this->model->getMeta()->isLocalized()) {
            $i18n = $this->getI18n();
            $locales = $i18n->getLocaleCodeList();

            $localizeDecorator = new LocalizeDecorator($this->model, $detailAction, $this->locale, $locales);

            $table->addDecorator($localizeDecorator);
        }

        $condition = $this->model->getMeta()->getOption(self::OPTION_CONDITION);
        if ($condition) {
            $table->getModelQuery()->addCondition($condition);
        }

        $this->addTableActions($table);

        return $table;
    }

    /**
     * Adds the table decorators
     * @param \ride\library\html\table\FormTable $table
     * @param string $detailAction URL to the detail of the
     * @return null
     */
    protected function addTableDecorators(FormTable $table, $detailAction) {
        $imageUrlGenerator = $this->dependencyInjector->get('ride\\library\\image\\ImageUrlGenerator');

        $table->addDecorator(new DataDecorator($imageUrlGenerator, $this->model, $detailAction, $this->pkField));
    }

    /**
     * Hook to add actions to the table
     * @param \ride\library\html\table\FormTable $table
     * @return null
     */
    protected function addTableActions(FormTable $table) {
        if ($this->isDeletable(null, false)) {
            $translator = $this->getTranslator();

            $table->addAction(
                $translator->translate('button.delete'),
                array($this, 'delete'),
                $translator->translate('label.table.confirm.delete')
            );
        }
    }

    /**
     * Action to set a form with a data object to the view
     * @param integer $id Primary key of the data object
     * @param string $locale Locale code of the data
     * @return null
     */
    public function formAction(I18n $i18n, $locale = null, $id = null) {
        // resolve locale
        if (!$locale) {
            $this->locale = $i18n->getLocale()->getCode();

            if ($this->model->getMeta()->isLocalized()) {
                if ($id) {
                    $url = $this->getAction(self::ACTION_EDIT, array('locale' => $this->locale, $this->pkField => $id));
                } else {
                    $url = $this->getAction(self::ACTION_ADD, array('locale' => $this->locale));
                }

                $this->response->setRedirect($url);

                return;
            }
        } else {
            $this->locale = $i18n->getLocale($locale)->getCode();
        }

        // resolve data
        if ($id) {
            if (!$this->isReadable($id)) {
                throw new UnauthorizedException();
            }

            $data = $this->getData($id);
            if ($data == null) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        } else {
            if (!$this->isWritable()) {
                throw new UnauthorizedException();
            }

            $data = $this->createData();
        }

        // handle form
        $form = $this->getForm($data);
        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                $this->response->setRedirect($this->getFormReferer($data));

                return;
            }

            try {
                $form->validate();

                $data = $this->getFormData($form);

                if ($this->isLocalized) {
                    $data->dataLocale = $this->locale;
                }

                $this->saveData($data);

                $this->response->setRedirect($this->getFormReferer($data));

                $format = $this->model->getMeta()->getDataFormat(DataFormatter::FORMAT_TITLE);
                $data = $this->orm->getDataFormatter()->formatData($data, $format);

                $this->addSuccess('success.data.saved', array('data' => $data));

                return;
            } catch (ValidationException $exception) {
                $this->response->setStatusCode(Response::STATUS_CODE_BAD_REQUEST);
                $this->addError('error.validation');

                $errors = $exception->getAllErrors();
                foreach ($errors as $fieldName => $fieldErrors) {
                    try {
                        $row = $form;

                        $tokens = explode('[', $fieldName);
                        foreach ($tokens as $token) {
                            $token = trim($token, ']');

                            $row = $row->getRow($token);
                        }

                        if ($row->getType() == 'hidden') {
                            throw new FormException();
                        }

                        continue;
                    } catch (FormException $e) {
                        // field not in the form, add error as general error
                        foreach ($fieldErrors as $error) {
                            $this->addError($error->getCode(), $error->getParameters());
                        }
                    }
                }

                $form->setValidationException($exception);
            }
        }

        // handle display
        $referer = $this->request->getQueryParameter(self::PARAMETER_REFERER);

        $this->setFormView($form, $referer, $i18n->getLocaleCodeList(), $locale, $data);
    }

    /**
     * Gets the referer for a save action
     * @param mixed $data Data container
     * @return string
     */
    protected function getFormReferer($data) {
        $referer = $this->request->getQueryParameter(self::PARAMETER_REFERER);
        if ($referer) {
            return $referer;
        }

        $pkField = $this->pkField;

        if (isset($data->$pkField)) {
            $referer = $this->getAction(self::ACTION_DETAIL, array('id' => $data->$pkField));
        }

        if ($referer) {
            return $referer;
        }

        return $this->getAction(self::ACTION_INDEX);
    }

    /**
     * Gets the data for the edit action
     * @param integer $id Primary key of the data to retrieve
     * @return mixed Data object for the provided id
     */
    protected function getData($id) {
        $query = $this->model->createQuery($this->locale);
        $query->setRecursiveDepth($this->recursiveDepth);
        $query->setFetchUnlocalizedData(true);
        $query->addCondition('{' . $this->pkField . '} = %1%', $id);

        return $query->queryFirst();
    }

    /**
     * Creates a new data container for the add action
     * @return mixed
     */
    protected function createData() {
        return $this->model->createData();
    }

    /**
     * Gets the data object from the provided form
     * @param \ride\library\html\form\Form $form
     * @return mixed Data object
     */
    protected function getFormData(Form $form) {
        return $form->getData();
    }

    /**
     * Saves the data to the model
     * @param mixed $data
     * @return null
     */
    protected function saveData($data) {
        $this->model->save($data);
    }

    /**
     * Action to delete the data from the model
     * @param array $data Array of primary keys
     * @return null
     */
    public function delete($data) {
        if (!$data || !$this->isDeletable()) {
            return;
        }

        $referer = $this->request->getHeader(Header::HEADER_REFERER);
        if (!$referer) {
            $referer = $this->request->getUrl();
        }

        $format = $this->model->getMeta()->getDataFormat(DataFormatter::FORMAT_TITLE);

        $dataFormatter = $this->orm->getDataFormatter();

        $this->response->setRedirect($referer);

        foreach ($data as $id) {
            if (!$this->isDeletable($id, false)) {

            } else {
                try {
                    $data = $this->getData($id);

                    $this->model->delete($data);

                    $this->addSuccess('success.data.deleted', array('data' => $dataFormatter->formatData($data, $format)));
                } catch (ValidationException $exception) {
                    $errors = $exception->getAllErrors();
                    foreach ($errors as $fieldName => $fieldErrors) {
                        foreach ($fieldErrors as $fieldError) {
                            $this->addError($fieldError->getCode(), $fieldError->getParameters());
                        }
                    }
                }
            }
        }
    }

    /**
     * Sets the form view for the scaffolding to the response
     * @param \ride\library\form\Form $form Form of the data
     * @param string $referer URL of the referer of the form action
     * @param array $locales Available locale codes
     * @param string $locale Code of the current locale
     * @param mixed $data Data object
     * @return null
     */
    protected function setFormView(Form $form, $referer, array $locales, $locale, $data = null) {
        if ($referer) {
            $urlReferer = '?' . self::PARAMETER_REFERER . '=' . urlencode($referer);
        } else {
            $urlReferer = null;
        }

        $meta = $this->model->getMeta();
        $title = $this->getViewTitle($data);
        $subtitle = $this->getViewSubtitle($data);
        $action = $this->request->getUrl() . $urlReferer;

        $pkField = $this->pkField;

        $variables = array(
        	'meta' => $meta,
            'form' => $form->getView(),
            'action' => $action,
            'referer' => $referer,
            'data' => $data,
            'title' => $title,
            'subtitle' => $subtitle,
            'localizeUrl' => null,
        );

        if ($this->model->getMeta()->isLocalized()) {
            $variables['locales'] = $locales;
            $variables['locale'] = $locale;
            if ($data && $data->$pkField) {
                $variables['localizeUrl'] = $this->getAction(self::ACTION_EDIT, array('locale' => '%locale%', 'id' => $data->$pkField)) . $urlReferer;
            } else {
                $variables['localizeUrl'] = $this->getAction(self::ACTION_ADD, array('locale' => '%locale%'));
            }
        }

        $view = $this->setTemplateView('orm/scaffold/form', $variables);

        $form->processView($view);

        return $view;
    }

    /**
     * Gets a title for the view
     * @param mixed $data The data which is being displayed, used only with the form view
     * @return string
     */
    protected function getViewTitle($data = null) {
        if ($this->translationTitle) {
            return $this->getTranslator()->translate($this->translationTitle);
        }

        return $this->model->getMeta()->getName();
    }

    /**
     * Gets a subtitle for the view
     * @param mixed $data The data which is being displayed, used only with the form view
     * @return string
     */
    protected function getViewSubtitle($data = null) {
        $pkField = $this->pkField;

        if (!$data || !$data->$pkField) {
            return;
        }

        $format = $this->model->getMeta()->getDataFormat(DataFormatter::FORMAT_TITLE);

        return $this->orm->getDataFormatter()->formatData($data, $format);
    }

    /**
     * Gets the form for the data of the model
     * @param mixed $data Data object to preset the form
     * @return \ride\library\form\Form
     */
    protected function getForm($data = null) {
        $web = $this->dependencyInjector->get('ride\\web\\WebApplication');
        $reflectionHelper = $this->model->getReflectionHelper();

        $component = $this->model->getMeta()->getOption('scaffold.component');
        if ($component) {
            $this->component = new $component($web, $reflectionHelper, $this->model);
        } else {
            $this->component = new ScaffoldComponent($web, $reflectionHelper, $this->model);
        }

        if ($this->component instanceof ScaffoldComponent) {
            $this->component->setDefaultRecursiveDepth($this->recursiveDepth);
        }

        $this->component->setLocale($this->locale);

        $formBuilder = $this->createFormBuilder($data);
        $formBuilder->setComponent($this->component);
        $formBuilder->setRequest($this->request);

        return $formBuilder->build();
    }

    /**
     * Gets the action URL
     * @param string $action Name of the action
     * @param array $arguments Arguments for the action
     * @return string|null URL if action is set, null otherwise
     */
    protected function getAction($action, $arguments = array()) {
        if (!isset($this->routes[$action])) {
            return null;
        }

        if (!isset($arguments['model'])) {
            $arguments['model'] = $this->model->getName();
        }
        if (!isset($arguments['locale'])) {
            $arguments['locale'] = $this->locale;
        }
        if (!isset($arguments['action'])) {
            $arguments['action'] = $action;
        }

        $action = $this->routes[$action];

        if (substr($action, 0, 4) != 'http') {
            $action = $this->getUrl($action, $arguments);
        } else {
            foreach ($arguments as $name => $value) {
                $action = str_replace('%' . $name . '%', $value, $action);
            }
        }

        return $action;
    }

    /**
     * Gets the URL of the provided route
     * @param string $routeId The id of the route
     * @param array $arguments Path arguments for the route
     * @return string
     * @throws \ride\library\router\exception\RouterException If the route is
     * not found
     */
    protected function getUrl($routeId, array $arguments = null) {
        if (!isset($arguments['locale'])) {
            $arguments['locale'] = $this->locale;
        }

        return parent::getUrl($routeId, $arguments);
    }

    /**
     * Checks if this model or a record thereof is deletable for the current user
     * @param integer $id The id of the data
     * @param boolean $addErrorToResponse Set to true to add an error messsage and the base view to the response when the data is not deletable
     * @return boolean True when the data is deletable, false otherwise
     */
    protected function isDeletable($id = null, $addErrorToResponse = true) {
        return true;
    }

    /**
     * Checks if this model or a record thereof is readable for the current user
     * @param integer $id The id of the data
     * @param boolean $addErrorToResponse Set to true to add an error messsage and the base view to the response when the data is not readable
     * @return boolean True when the data is readable, false otherwise
     */
    protected function isReadable($id = null, $addErrorToResponse = true) {
        return true;
    }

    /**
     * Checks if this model or a record thereof is writable for the current user
     * @param integer $id The id of the data
     * @param boolean $addErrorToResponse Set to true to add an error messsage and the base view to the response when the data is not writable
     * @return boolean True when the data is writable, false otherwise
     */
    protected function isWritable($id = null, $addErrorToResponse = true) {
        return true;
    }

}
