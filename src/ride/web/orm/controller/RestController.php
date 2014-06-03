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
    public function searchAction(OrmManager $orm, $model, $locale = null) {
        $model = $this->getModel($orm, $model);
        if (!$model) {
            return;
        }

        $options = $this->request->getQueryParameters();
        $locale = $this->request->getQueryParameter('locale', $locale);
        $page = $this->request->getQueryParameter('page', 1);
        $limit = $this->request->getQueryParameter('limit', 20);

        $options['page'] = $page;
        $options['limit'] = $limit;

        $entries = $model->find($options, $locale);
        foreach ($entries as $index => $entry) {
            $entries[$index] = $model->convertEntryToArray($entry);
        }

        $this->setJsonView($result);
    }

    /**
     * Sets a json response of the model data list
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM manager
     * @param string $model Name of the model
     * @return null
     */
    public function listAction(OrmManager $orm, $model, $locale = null) {
        $model = $this->getModel($orm, $model);
        if (!$model) {
            return;
        }

        $options = $this->request->getQueryParameters();
        $locale = $this->request->getQueryParameter('locale', $locale);

        $entries = $model->find($options, $locale);
        $options = $model->getOptionsFromEntries($entries);

        $this->setJsonView($options);
    }

    /**
     * Sets a json response of a entry detail
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM manager
     * @param string $model Name of the model
     * @return null
     */
    public function detailAction(OrmManager $orm, $model, $id, $locale = null) {
        $model = $this->getModel($orm, $model);
        if (!$model) {
            return;
        }

        $locale = $this->request->getQueryParameter('locale', $locale);

        $entry = $model->getById($id, $locale);
        if (!$entry) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $data = $model->convertEntryToArray($entry);

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

            $expose = $model->getMeta()->getOption('rest.expose');
            if (!$expose || !Boolean::getBoolean($expose)) {
                throw new OrmException();
            }
        } catch (OrmException $exception) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        return $model;
    }

}
