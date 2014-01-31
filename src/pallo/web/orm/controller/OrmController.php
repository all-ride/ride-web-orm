<?php

namespace pallo\web\orm\controller;

use pallo\library\orm\OrmManager;
use pallo\library\router\Route;

use pallo\web\base\controller\AbstractController;

/**
 * Controller of the ORM application
 */
class OrmController extends AbstractController {

    /**
     * Action to show an index of the models
     * @return null
     */
    public function indexAction(OrmManager $orm) {
        $translator = $this->getTranslator();

        $models = $orm->getModels(true);

        $this->setTemplateView('orm/models', array(
        	'models' => $models,
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
        $table = $meta->getModelTable();

        $this->setTemplateView('orm/model', array(
        	'model' => $model,
            'meta' => $meta,
            'table' => $table,
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