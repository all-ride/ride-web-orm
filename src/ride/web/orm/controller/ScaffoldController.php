<?php

namespace ride\web\orm\controller;

use ride\library\dependency\exception\DependencyException;
use ride\library\form\exception\FormException;
use ride\library\form\Form;
use ride\library\html\table\FormTable;
use ride\library\http\Header;
use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\import\provider\FileProvider;
use ride\library\import\OrmModelExporter;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\entry\format\EntryFormatter;
use ride\library\orm\model\Model;
use ride\library\security\exception\UnauthorizedException;
use ride\library\system\file\FileSystem;
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
    const ROUTE_ENTRY = 'system.orm.scaffold.action.entry';

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
     * Depth for the form relation fields
     * @var integer|null
     */
    protected $formDepth;

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
     * Translation key for the submit button of the form
     * @var string
     */
    protected $translationSubmit;

    /**
     * Routes for actions
     * @var array
     */
    protected $routes;

    /**
     * Path to the template resource for the form action
     * @var string
     */
    protected $templateIndex;

    /**
     * Path to the template resource for the form action
     * @var string
     */
    protected $templateForm;

    /**
     * Flag to see if the security function should actually check permissions
     * @var boolean
     */
    protected $isSecured;

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

        $this->formDepth = $meta->getOption('scaffold.form.depth', 1);
        $this->isSecured = $meta->getOption('scaffold.security');
        $this->isLocalized = $meta->isLocalized();

        $this->pagination = $pagination;
        $this->search = $search;
        $this->order = $order;
        $this->orderMethod = null;
        $this->orderDirection = null;

        $this->routes = array(
            self::ACTION_INDEX => self::ROUTE_INDEX,
            self::ACTION_ADD => self::ROUTE_ADD,
            self::ACTION_EDIT => self::ROUTE_ENTRY,
            self::ACTION_EXPORT => self::ROUTE_EXPORT,
        );

        $this->templateIndex = 'orm/scaffold/index';
        $this->templateForm = 'orm/scaffold/form';

        $this->translationTitle = $meta->getOption('scaffold.title');
        $this->translationAdd = $meta->getOption('scaffold.title.add');
        $this->translationSubmit = 'button.save';

        $this->initialize();
    }

    /**
     * Hook after the constructor
     * @return null
     */
    protected function initialize() {

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
        if (!$this->isReadable(null)) {
            throw new UnauthorizedException();
        }

        // resolve locale
        if (!$locale) {
            $this->locale = $this->getContentLocale();

            if ($this->model->getMeta()->isLocalized()) {
                $this->response->setRedirect($this->getAction(self::ACTION_INDEX, array('locale' => $this->locale)));

                return;
            }
        } else {
            $this->locale = $i18n->getLocale($locale)->getCode();

            $this->setContentLocale($this->locale);
        }

        // handle table
        $this->initializeOrder();

        $baseUrl = $this->getAction(self::ACTION_INDEX);
        $table = $this->getTable($this->getAction(self::ACTION_DETAIL, array('id' => '%id%')));

        $form = $this->processTable($table, $baseUrl, 25, $this->orderMethod, $this->orderDirection);
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
        if ($this->isWritable(null) && $addAction) {
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
        } else {
            $viewActions += $this->getIndexActions($locale);
        }

        $variables = array(
            'meta' => $meta,
            'form' => $form->getView(),
            'table' => $table,
            'actions' => $viewActions,
            'exports' => $this->getExportActions($locale, $table),
            'title' => $title,
            'locale' => $locale,
            'localizeUrl' => null,
        );

        if ($this->model->getMeta()->isLocalized()) {
            $variables['locales'] = $locales;
            $variables['localizeUrl'] = $this->getAction(self::ACTION_INDEX, array('locale' => '%locale%'));
        }

        return $this->setTemplateView($this->templateIndex, $variables);
    }

    /**
     * Hook to add extra actions in the overview
     * @param string $locale Code of the locale
     * @return array Array with the URL of the action as key and the label as
     * value
     */
    protected function getIndexActions($locale) {
        return array();
    }

    /**
     * Gets the export actions of the overview
     * @param string $locale Code of the locale
     * @param \ride\library\html\table\FormTable $table
     * @return array Array with the extension of the format as key and the URL
     * to the export as value
     */
    protected function getExportActions($locale, FormTable $table) {
        $exportQuery = array();
        if ($table->hasSearch()) {
            $exportQuery['search'] = 'search=' . urlencode($table->getSearchQuery());
        }
        if ($table->hasOrderMethods()) {
            $exportQuery['order'] = 'order=' . urlencode($table->getOrderMethod());
            $exportQuery['direction'] = 'direction=' . urlencode($table->getOrderDirection());
        }

        if ($exportQuery) {
            $exportQuery = '?' . implode('&', $exportQuery);
        } else {
            $exportQuery = '';
        }

        $exportActions = $this->dependencyInjector->getAll('ride\\library\\import\\provider\\DestinationProvider');
        foreach ($exportActions as $extension => $exportProvider) {
            if ($exportProvider instanceof FileProvider) {
                $exportActions[$extension] = $this->getAction(self::ACTION_EXPORT, array('format' => $extension)) . $exportQuery;
            } else {
                unset($exportActions[$extension]);
            }
        }

        return $exportActions;
    }

    /**
     * Performs an export of the provided table and sets the view of the export to the response
     * @param \ride\library\html\table\FormTable $table Table to get the export of
     * @param string $extension The extension for the export
     * @return null
     */
    public function exportAction(i18n $i18n, FileSystem $fileSystem, OrmModelExporter $exporter, $locale = null, $format = null) {
        if (!$locale) {
            $locale = $i18n->getLocale()->getCode();

            $this->response->setRedirect($this->getAction(self::ACTION_EXPORT, array('locale' => $locale)));

            return;
        } else {
            $this->locale = $i18n->getLocale($locale)->getCode();
        }

        try {
            $destinationProvider = $this->dependencyInjector->get('ride\\library\\import\\provider\\DestinationProvider', $format);
            if (!$destinationProvider instanceof FileProvider) {
                throw new DependencyInjection('Destination provider is not a file provider');
            }

            $destinationProvider->setFile($fileSystem->getTemporaryFile());
        } catch (DependencyException $exception) {
            $this->addError('error.format.export.unsupported', array('format' => $format));
            $this->response->setRedirect($this->getAction(self::ACTION_INDEX));

            return;
        }

        $this->initializeOrder();

        $table = $this->getTable();
        $tableHelper = $this->getTableHelper();

        $page = 1;
        $rowsPerPage = 25;
        $searchQuery = null;

        $parameters = $this->request->getQueryParameters();

        $tableHelper->getArgumentsFromArray($parameters, $page, $rowsPerPage, $searchQuery, $this->orderMethod, $this->orderDirection);
        $tableHelper->setArgumentsToTable($table, $page, $rowsPerPage, $searchQuery, $this->orderMethod, $this->orderDirection);

        $form = $this->buildForm($table);

        $table->processExport($form);

        // ini_set('memory_limit', '512M');
        // ini_set('max_execution_time', '500');
        // ini_set('max_input_time', '500');

        $exporter->setTranslator($this->getTranslator());
        $exporter->export($table->getModelQuery(), $destinationProvider);

        $file = $destinationProvider->getFile();
        $title = $this->model->getName();

        $this->setDownloadView($file, $title . '.' . $format, true);
    }

    /**
     * Initializes the order parameters
     * @return null
     */
    protected function initializeOrder() {
        if ($this->orderMethod !== null || $this->orderDirection !== null) {
            return;
        }

        $meta = $this->model->getMeta();

        $this->orderMethod = $meta->getOption('order.field');
        $this->orderDirection = $meta->getOption('order.direction');

        if (!$this->orderMethod) {
            return;
        }

        $field = $meta->getField($this->orderMethod);

        $label = $field->getOption('label.name');
        if ($label) {
            $this->orderMethod = $this->getTranslator()->translate($label);
        } else {
            $this->orderMethod = ucfirst($field->getName());
        }
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

        $condition = $this->model->getMeta()->getOption('scaffold.condition');
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
        $defaultImage = $this->getTheme() . '/img/data.png';
        $imageUrlGenerator = $this->dependencyInjector->get('ride\\library\\image\\ImageUrlGenerator');

        $table->addDecorator(new DataDecorator($this->model, $imageUrlGenerator, $detailAction, $this->pkField, $defaultImage));
    }

    /**
     * Hook to add actions to the table
     * @param \ride\library\html\table\FormTable $table
     * @return null
     */
    protected function addTableActions(FormTable $table) {
        if (!$this->isDeletable(null)) {
            return;
        }

        $translator = $this->getTranslator();

        $table->addAction(
            $translator->translate('button.delete'),
            array($this, 'delete'),
            $translator->translate('label.table.confirm.delete')
        );

        if ($this->isLocalized) {
            $table->addAction(
                $translator->translate('button.delete.locale'),
                array($this, 'deleteLocalized'),
                $translator->translate('label.table.confirm.delete')
            );
        }
    }

    /**
     * Action to view the details of a entry
     * @param string $locale Locale code of the data
     * @param integer $id Primary key of the entry
     * @return null
     */
    public function detailAction(I18n $i18n, $locale, $id) {
        $this->locale = $i18n->getLocale($locale)->getCode();

        $url = $this->getAction(self::ACTION_EDIT, array('locale' => $this->locale, 'id' => $id));

        $this->response->setRedirect($url);
    }

    /**
     * Action to show and handle a form with a entry to the view
     * @param string $locale Locale code of the data
     * @param integer $id Primary key of the data object
     * @return null
     */
    public function formAction(I18n $i18n, $locale = null, $id = null) {
        // resolve locale
        if (!$locale) {
            $this->locale = $this->getContentLocale();

            if ($this->model->getMeta()->isLocalized()) {
                if ($id) {
                    $url = $this->getAction(self::ACTION_EDIT, array('locale' => $this->locale, 'id' => $id));
                } else {
                    $url = $this->getAction(self::ACTION_ADD, array('locale' => $this->locale));
                }

                $this->response->setRedirect($url);

                return;
            }
        } else {
            $this->locale = $i18n->getLocale($locale)->getCode();

            $this->setContentLocale($this->locale);
        }

        $this->model->getOrmManager()->setLocale($this->locale);

        // resolve data
        if ($id) {
            $entry = $this->getEntry($id);
            if (!$entry) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }

            if (!$this->isReadable($entry)) {
                throw new UnauthorizedException();
            }
        } else {
            if (!$this->isWritable(null)) {
                throw new UnauthorizedException();
            }

            $entry = $this->createEntry();
        }

        // override locale to get the localized relations
        if ($this->isLocalized) {
            $entry->setLocale($locale);
        }

        // handle form
        $form = $this->getForm($entry);
        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                $this->response->setRedirect($this->getFormReferer($entry));

                return;
            }

            try {
                $form->validate();

                $entry = $this->getFormEntry($form);
                if (!$this->isWritable($entry)) {
                    throw new UnauthorizedException();
                }

                $this->saveEntry($entry);

                $this->response->setRedirect($this->getFormReferer($entry));

                $entryFormatter = $this->orm->getEntryFormatter();
                $format = $this->model->getMeta()->getFormat(EntryFormatter::FORMAT_TITLE);

                $this->addSuccess('success.data.saved', array('data' => $entryFormatter->formatEntry($entry, $format)));

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        // handle display
        $referer = $this->request->getQueryParameter(self::PARAMETER_REFERER);

        $this->setFormView($form, $referer, $i18n->getLocaleCodeList(), $locale, $entry);
    }

    /**
     * Gets the referer for a save action
     * @param mixed $entry Entry instance
     * @return string
     */
    protected function getFormReferer($entry) {
        $referer = $this->request->getQueryParameter(self::PARAMETER_REFERER);
        if ($referer) {
            return $referer;
        }

        $pkField = $this->pkField;

        if (isset($entry->$pkField)) {
            $referer = $this->getAction(self::ACTION_DETAIL, array('id' => $entry->$pkField));
        }

        if ($referer) {
            return $referer;
        }

        return $this->getAction(self::ACTION_INDEX);
    }

    /**
     * Gets an entry
     * @param integer $id Primary key of the entry
     * @return mixed Instance of the entry with the provided id
     */
    protected function getEntry($id) {
        $query = $this->model->createQuery($this->locale);
        $query->setFetchUnlocalized(true);
        $query->addCondition('{' . $this->pkField . '} = %1%', $id);

        return $query->queryFirst();
    }

    /**
     * Creates a new entry for the add action
     * @return mixed
     */
    protected function createEntry() {
        return $this->model->createEntry();
    }

    /**
     * Gets the entry from the provided form
     * @param \ride\library\form\Form $form
     * @return mixed Entry instance
     */
    protected function getFormEntry(Form $form) {
        return $form->getData();
    }

    /**
     * Saves an entry to the model
     * @param mixed $entry Entry to save
     * @return null
     */
    protected function saveEntry($entry) {
        $this->model->save($entry);
    }

    /**
     * Action to delete the entries from the model
     * @param array $entries Array of entries or entry primary keys
     * @return null
     */
    public function delete($entries) {
        if (!$entries || !$this->isDeletable()) {
            return;
        }

        $entryFormatter = $this->orm->getEntryFormatter();
        $format = $this->model->getMeta()->getFormat(EntryFormatter::FORMAT_TITLE);

        foreach ($entries as $entry) {
            try {
                if (is_numeric($entry)) {
                    $entry = $this->model->createProxy($entry);
                }

                $data = $entryFormatter->formatEntry($entry, $format);

                if (!$this->isDeletable($entry)) {
                    $this->addError('error.data.deleted.permission', array('data' => $data));
                } else {
                    $this->model->delete($entry);

                    $this->addSuccess('success.data.deleted', array('data' => $data));
                }
            } catch (ValidationException $exception) {
                $errors = $exception->getAllErrors();
                foreach ($errors as $fieldName => $fieldErrors) {
                    foreach ($fieldErrors as $fieldError) {
                        $this->addError($fieldError->getCode(), $fieldError->getParameters());
                    }
                }
            }
        }

        $referer = $this->request->getHeader(Header::HEADER_REFERER);
        if (!$referer) {
            $referer = $this->request->getUrl();
        }

        $this->response->setRedirect($referer);
    }

    /**
     * Action to delete the locale entry from the model
     * @param array $entries Array of entries or entry primary keys
     * @return null
     */
    public function deleteLocalized($entries){
        if (!$entries || !$this->isDeletable()) {
            return;
        }

        $entryFormatter = $this->orm->getEntryFormatter();
        $format = $this->model->getMeta()->getFormat(EntryFormatter::FORMAT_TITLE);
        $locale = $this->locale;

        foreach ($entries as $entry) {
            try {
                if (is_numeric($entry)) {
                    $entry = $this->model->createProxy($entry, $locale);
                }

                if (!$this->isDeletable($entry)) {
                    $this->addError('error.data.deleted.permission', array('data' => $entryFormatter->formatEntry($entry, $format)));
                } else {
                    $entryLocale = $this->model->deleteLocalized($entry, $locale);

                    if (!$entryLocale){
                        $this->addError('error.delete.translation.empty', array('data' => $entryFormatter->formatEntry($entry, $format)));
                    } else {
                        $this->addSuccess('success.data.deleted', array('data' => $entryFormatter->formatEntry($entry, $format)));
                    }
                }
            } catch (ValidationException $exception) {
                $errors = $exception->getAllErrors();
                foreach ($errors as $fieldName => $fieldErrors) {
                    foreach ($fieldErrors as $fieldError) {
                        $this->addError($fieldError->getCode(), $fieldError->getParameters());
                    }
                }
            }
        }

        $referer = $this->request->getHeader(Header::HEADER_REFERER);
        if (!$referer) {
            $referer = $this->request->getUrl();
        }

        $this->response->setRedirect($referer);
    }

    /**
     * Sets the form view for the scaffolding to the response
     * @param \ride\library\form\Form $form Form of the entry
     * @param string $referer URL of the referer of the form action
     * @param array $locales Available locale codes
     * @param string $locale Code of the current locale
     * @param mixed $entry Entry instance
     * @return null
     */
    protected function setFormView(Form $form, $referer, array $locales, $locale, $entry = null) {
        if ($referer) {
            $urlReferer = '?' . self::PARAMETER_REFERER . '=' . urlencode($referer);
        } else {
            $urlReferer = null;
        }

        $meta = $this->model->getMeta();
        $title = $this->getViewTitle($entry);
        $subtitle = $this->getViewSubtitle($entry);
        $action = $this->request->getUrl() . $urlReferer;

        $pkField = $this->pkField;

        if (isset($this->component)) {
            $tabs = $this->component->getTabs();
            $tabNames = array_keys($tabs);
            $activeTab = array_shift($tabNames);
        } else {
            $tabs = array();
            $tabNames = array();
            $activeTab = null;
        }

        $variables = array(
            'meta' => $meta,
            'activeTab' => $activeTab,
            'tabs' => $tabs,
            'form' => $form->getView(),
            'action' => $action,
            'referer' => $referer,
            'entry' => $entry,
            'title' => $title,
            'subtitle' => $subtitle,
            'translationSubmit' => $this->translationSubmit,
            'localizeUrl' => null,
            'locale' => $locale,
            'isWritable' => $this->isWritable($entry),
        );

        if ($this->model->getMeta()->isLocalized()) {
            $variables['locales'] = $locales;
            if ($entry && $entry->$pkField) {
                $variables['localizeUrl'] = $this->getAction(self::ACTION_EDIT, array('locale' => '%locale%', 'id' => $entry->$pkField)) . $urlReferer;
            } else {
                $variables['localizeUrl'] = $this->getAction(self::ACTION_ADD, array('locale' => '%locale%'));
            }
        }

        $view = $this->setTemplateView($this->templateForm, $variables);

        $form->processView($view);

        return $view;
    }

    /**
     * Gets a title for the view
     * @param mixed $entry Entry which is being displayed, used only with the
     * form view
     * @return string
     */
    protected function getViewTitle($entry = null) {
        if ($this->translationTitle) {
            return $this->getTranslator()->translate($this->translationTitle);
        }

        return $this->model->getMeta()->getName();
    }

    /**
     * Gets a subtitle for the view
     * @param mixed $entry Entry which is being displayed, used only with the
     * form view
     * @return string
     */
    protected function getViewSubtitle($entry = null) {
        $pkField = $this->pkField;

        if (!$entry || !$entry->$pkField) {
            return;
        }

        $format = $this->model->getMeta()->getFormat(EntryFormatter::FORMAT_TITLE);

        return $this->orm->getEntryFormatter()->formatEntry($entry, $format);
    }

    /**
     * Gets the form for an entry of the model
     * @param mixed $entry Entry instance to preset the form
     * @return \ride\library\form\Form
     */
    protected function getForm($entry = null) {
        $this->component = $this->getFormComponent();

        $formBuilder = $this->createFormBuilder($entry);
        $formBuilder->setComponent($this->component);

        return $formBuilder->build();
    }

    /**
     * Gets the component for the form
     * @return \ride\library\form\component\Component
     */
    protected function getFormComponent() {
        $web = $this->dependencyInjector->get('ride\\web\\WebApplication');
        $ormService = $this->dependencyInjector->get('ride\\service\\OrmService');
        $securityManager = $this->getSecurityManager();

        $component = $this->model->getMeta()->getOption('scaffold.component');
        if ($component) {
            $component = new $component($web, $securityManager, $ormService, $this->model);
        } else {
            $component = new ScaffoldComponent($web, $securityManager, $ormService, $this->model);
        }

        if ($component instanceof ScaffoldComponent) {
            $component->setDepth($this->formDepth);
            $component->setLog($this->getLog());
        }

        $component->setLocale($this->locale);

        return $component;
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
     * @param string $routeId Id of the route
     * @param array $arguments Path arguments for the route
     * @return string
     * @throws \ride\library\router\exception\RouterException If the route is
     * not found
     */
    protected function getUrl($routeId, array $arguments = null, array $queryParameters = null, $querySeparator = '&') {
        if (!isset($arguments['locale'])) {
            $arguments['locale'] = $this->locale;
        }

        return parent::getUrl($routeId, $arguments, $queryParameters, $querySeparator);
    }

    /**
     * Checks if this model or a record thereof is deletable for the current user
     * @param mixed $entry An entry for a specific permission, null for a
     * generic one
     * @return boolean True when the entry is deletable, false otherwise
     */
    protected function isDeletable($entry = null) {
        return $this->checkPermission('delete');
    }

    /**
     * Checks if this model or a record thereof is readable for the current user
     * @param mixed $entry An entry for a specific permission, null for a
     * generic one
     * @return boolean True when the entry is readable, false otherwise
     */
    protected function isReadable($entry = null) {
        return $this->checkPermission('read');
    }

    /**
     * Checks if this model or a record thereof is writable for the current user
     * @param mixed $entry An entry for a specific permission, null for a
     * generic one
     * @return boolean True when the entry is writable, false otherwise
     */
    protected function isWritable($entry = null) {
        return $this->checkPermission('write');
    }

    /**
     * Checks a permission
     * @param string $permission Internal permission name
     * @return boolean
     */
    protected function checkPermission($permission) {
        if (!$this->isSecured) {
            return true;
        }

        $permission = 'orm.model.' . $this->model->getName() . '.' . $permission;

        return $this->getSecurityManager()->isPermissionGranted($permission);
    }

}
