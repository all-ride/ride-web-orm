<?php

namespace ride\web\orm\controller;

use ride\library\orm\OrmManager;
use ride\library\router\Route;

use ride\web\base\controller\AbstractController;
use ride\web\orm\table\builder\decorator\ModelActionDecorator;
use ride\web\orm\table\builder\ModelFieldTable;
use ride\web\orm\table\builder\ModelIndexTable;
use ride\web\orm\table\builder\ModelTable;

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

        $scaffoldLabel = $translator->translate('button.scaffold');

        $baseUrl = $this->getUrl('system.orm');
        $modelUrl = $this->getUrl('system.orm.model', array('model' => '%model%'));
        $scaffoldUrl = $this->getUrl('system.orm.scaffold', array('model' => '%model%'));

        $models = $orm->getModels(true);

        $table = new ModelTable($orm, $translator, $models, $modelUrl);
        $table->setPaginationOptions(array(5, 10, 25, 50, 100, 250, 500));
        $table->addDecorator(new ModelActionDecorator($scaffoldLabel, $scaffoldUrl));

        $form = $this->processTable($table, $baseUrl, 10);
        if ($this->response->getView() || $this->response->willRedirect()) {
            return;
        }

        $this->setTemplateView('orm/models', array(
        	'table' => $table,
        	'form' => $form,
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
        $entryClass = $meta->getEntryClassName();
        $proxyClass = $meta->getProxyClassName();

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
            'entryClass' => $entryClass,
            'proxyClass' => $proxyClass,
            'tableAction' => $tableAction,
            'tableFields' => $tableFields,
            'tableFieldsForm' => $tableFieldsForm,
            'tableIndexes' => $tableIndexes,
            'hasApi' => class_exists('ride\\web\\api\\controller\\ApiController'),
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
     * @return \ride\library\http\Request The request for the scaffolding
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
            case ScaffoldController::ACTION_DETAIL:
                if ($id === null) {
                    $this->response->setStatusCode(404);

                    return;
                }

                $controllerAction = 'detailAction';
                $actionArguments['id'] = $id;

                break;
            default:
                $this->response->setStatusCode(404);

                return;
        }

        $modelName = $model;

        $model = $orm->getModel($modelName);

        $className = $model->getMeta()->getOption('scaffold.controller');
        if ($className) {
            $controller = $this->dependencyInjector->get($className, null, array('model' => $model), true);
        } else {
            $controller = $this->dependencyInjector->get('ride\\web\\orm\\controller\\ScaffoldController', null, array('model' => $model));
        }

        $route = $this->request->getRoute();
        $path = $route->getPath() . '/' . $modelName . '/' . $locale . ($id ? '/' . $id : '') . ($action ? '/' . $action : '');
        $callback = array($controller, $controllerAction);

        $route = new Route($path, $callback);
        $route->setArguments($actionArguments);

        $this->request->setRoute($route);

        return $this->request;
    }

}
