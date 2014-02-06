<?php

namespace pallo\web\orm\table\builder;

use pallo\library\html\table\decorator\StaticDecorator;
use pallo\library\html\table\FormTable;
use pallo\library\i18n\translator\Translator;
use pallo\library\orm\OrmManager;

use pallo\web\orm\table\builder\decorator\ModelDecorator;
use pallo\web\orm\table\builder\decorator\ModelOptionDecorator;

/**
 * Table to display an overview of model definitions
 */
class ModelTable extends FormTable {

    /**
     * Constructs a new models table
     * @param pallo\library\orm\OrmManager $orm Instance of the ORM manager
     * @param pallo\library\i18n\translator\Translator $translator
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