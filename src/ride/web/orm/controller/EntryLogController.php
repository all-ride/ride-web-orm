<?php

namespace ride\web\orm\controller;

use ride\library\orm\model\EntryLogModel;
use ride\library\orm\OrmManager;

/**
 * Controller to view the entry log
 */
class EntryLogController extends ScaffoldController {

    /**
     * Constructs a new scaffold controller
     * @param ride\library\orm\OrmManager $orm
     * @return null
     */
    public function __construct(OrmManager $orm) {
        $model = $orm->getModel(EntryLogModel::NAME);

        parent::__construct($model);
    }

}
