<?php

namespace ride\web\orm\controller;

use ride\library\http\Response;
use ride\library\orm\exception\OrmException;
use ride\library\orm\OrmManager;
use ride\library\reflection\Boolean;

use ride\web\base\controller\AbstractController;

/**
 * Controller for a generic REST API to the ORM data
 */
class RestController extends AbstractController {

    /**
     * Sets a json response of the model data list
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM manager
     * @param string $model Name of the model
     * @return null
     */
    public function listAction(OrmManager $orm, $model) {
        $model = $this->getModel($orm, $model);
        if (!$model) {
            return;
        }

        $options = $this->request->getQueryParameters();

        $data = $model->getDataList($options);

        $this->setJsonView($data);
    }

    /**
     * Gets a model from the ORM manager with the REST expose flag ensured to
     * true
     * @return \ride\library\orm\model\Model|null
     */
    protected function getModel(OrmManager $orm, $model) {
        try {
            $model = $orm->getModel($model);
        } catch (OrmException $exception) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $expose = $model->getMeta()->getOption('rest.expose');
        if (!$expose || !Boolean::getBoolean($expose)) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        return $model;
    }

}
