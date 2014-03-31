<?php

namespace ride\web\orm\table\builder;

use ride\library\html\table\decorator\StaticDecorator;
use ride\library\html\table\FormTable;
use ride\library\i18n\translator\Translator;
use ride\library\orm\OrmManager;

use ride\web\orm\table\builder\decorator\ModelDecorator;
use ride\web\orm\table\builder\decorator\ModelOptionDecorator;

/**
 * Table to display an overview of model definitions
 */
class ModelTable extends FormTable {

    /**
     * Constructs a new models table
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM manager
     * @param \ride\library\i18n\translator\Translator $translator
     * @param array $models
     * @param string $tableAction
     * @param string $modelAction
     */
    public function __construct(OrmManager $orm, Translator $translator, array $models, $modelAction = null) {
        ksort($models);

        parent::__construct($models);

        $this->addDecorator(new ModelDecorator($orm, $translator, $modelAction)); //, new StaticDecorator($translator->translate('orm.label.model')));
    }

//     public function getHtml() {
//         if ($this->actions) {
//             $this->addDecorator(new ModelOptionDecorator(), null, true);
//         }

//         return parent::getHtml();
//     }

}