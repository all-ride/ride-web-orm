<?php

namespace pallo\web\orm\controller;

use pallo\library\orm\OrmManager;
use pallo\library\router\Route;

use pallo\web\base\controller\AbstractController;
use pallo\web\orm\table\builder\decorator\ModelActionDecorator;
use pallo\web\orm\table\builder\ModelFieldTable;
use pallo\web\orm\table\builder\ModelIndexTable;
use pallo\web\orm\table\builder\ModelTable;

/**
 * Controller of the ORM application
 */
class BuilderController extends AbstractController {

    /**
     * Action to show an index of the models
     * @return null
     */
    public function indexAction(OrmManager $orm) {
        $translator = $this->getTranslator();

        $models = $orm->getModels(true);
        $modelAction = $this->getUrl('system.orm.model', array('model' => '%model%'));

        $table = new ModelTable($orm, $translator, $models, $modelAction);
        $tableForm = $this->buildForm($table);
        $table->addDecorator(new ModelActionDecorator($translator->translate('button.scaffold'), $this->getUrl('system.orm.scaffold', array('model' => '%model%'))));
        $table->processForm($tableForm);

        if ($this->response->getView() || $this->response->willRedirect()) {
            return;
        }

        $this->setTemplateView('orm/models', array(
        	'tableModels' => $table,
        	'tableModelsAction' => $this->request->getUrl(),
        	'tableModelsForm' => $tableForm,
        ));
    }

    /**
     * Action to show the detail of a model
     * @param string $modelName Name of the model
     * @return null
     */
    public function modelAction(OrmManager $orm, $model) {
        $translator = $this->getTranslator();

        $model = $orm->getModel($model);
        $meta = $model->getMeta();
        $modelTable = $meta->getModelTable();
        $modelClass = get_class($model);
        $dataClass = $meta->getDataClassName();

        $tableAction = $this->request->getUrl();
        $modelAction = $this->getUrl('system.orm.model', array('model' => '%model%'));

        $tableFields = new ModelFieldTable($translator, $modelTable, $tableAction, $modelAction);
        $tableFieldsForm = $this->buildForm($tableFields);
        $tableFields->processForm($tableFieldsForm);

        if ($this->response->getView() || $this->response->willRedirect()) {
            return;
        }

        $tableIndexes = new ModelIndexTable($translator, $modelTable->getIndexes());

        $this->setTemplateView('orm/model', array(
        	'model' => $model,
            'modelTable' => $modelTable,
            'modelClass' => $modelClass,
            'dataClass' => $dataClass,
            'tableAction' => $tableAction,
            'tableFields' => $tableFields,
            'tableFieldsForm' => $tableFieldsForm,
            'tableIndexes' => $tableIndexes,
            'hasApi' => class_exists('pallo\\web\\api\\controller\\ApiController'),
        ));
    }

    /**
     * Action to define the models in the database
     * @return null
     */
    public function defineAction(OrmManager $orm) {
        $referer = $this->request->getQueryParameter('referer');

        if ($this->request->isPost()) {
            $orm->defineModels();

            $this->addSuccess('success.orm.define');

            if (!$referer) {
                $referer = $this->getUrl('orm');
            }

            $this->response->setRedirect($referer);

            return;
        }

        $this->setTemplateView('orm/confirm', array(
        	'referer' => $referer,
        ));
    }

    /**
     * Action to scaffold a model
     * @param string $modelName Name of the model to scaffold
     * @return pallo\core\mvc\Request The request for the scaffolding
     */
    public function scaffoldAction(OrmManager $orm, $model = null, $locale = null, $id = null, $action = null, $format = null) {
        if (!$model) {
            $this->response->setStatusCode(404);

            return;
        }

        $actionArguments = array(
            'locale' => $locale,
        );

        switch ($action) {
            case null:
                $controllerAction = 'indexAction';

                break;
            case ScaffoldController::ACTION_ADD:
                if ($id !== null) {
                    $this->response->setStatusCode(404);

                    return;
                }

                $controllerAction = 'formAction';

                break;
            case ScaffoldController::ACTION_EXPORT:

                if ($id !== null) {
                    $this->response->setStatusCode(404);

                    return;
                }

                $controllerAction = 'exportAction';
                $actionArguments['format'] = $format;

                break;
            case ScaffoldController::ACTION_EDIT:
                if ($id === null) {
                    $this->response->setStatusCode(404);

                    return;
                }

                $controllerAction = 'formAction';
                $actionArguments['id'] = $id;

                break;
            default:
                $this->response->setStatusCode(404);

                return;
        }

        $modelName = $model;

        $model = $orm->getModel($modelName);

        $className = $model->getMeta()->getOption('scaffold.controller', 'pallo\\web\\orm\\controller\\ScaffoldController');
        $controller = new $className($model);

        $route = $this->request->getRoute();
        $path = $route->getPath() . '/' . $modelName . '/' . $locale . ($id ? '/' . $id : '') . ($action ? '/' . $action : '');
        $callback = array($controller, $controllerAction);

        $route = new Route($path, $callback);
        $route->setArguments($actionArguments);

        $this->request->setRoute($route);

        return $this->request;
    }

}