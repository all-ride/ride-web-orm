<?php

namespace pallo\web\orm\table\builder;

use pallo\web\orm\table\builder\decorator\IndexDecorator;

use pallo\library\html\table\ArrayTable;
use pallo\library\i18n\translator\Translator;

/**
 * Table for model indexes
 */
class ModelIndexTable extends ArrayTable {

    /**
     * Constructs a new model index table
     * @param pallo\library\i18n\translator\Translator $translator
     * @param array $indexes
     * @param string $indexAction URL to the action for the index
     * @return null
     */
    public function __construct(Translator $translator, array $indexes, $indexAction = null) {
        parent::__construct($indexes);

        $this->addDecorator(new IndexDecorator($translator, $indexAction));
    }

}