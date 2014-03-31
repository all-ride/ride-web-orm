<?php

namespace ride\web\orm\table\builder;

use ride\library\html\table\FormTable;
use ride\library\i18n\translator\Translator;
use ride\library\orm\definition\ModelTable;

use ride\web\orm\table\builder\decorator\ModelFieldDecorator;
use ride\web\orm\table\builder\decorator\ModelFieldFlagsDecorator;
use ride\web\orm\table\builder\decorator\ModelFieldLabelDecorator;

/**
 * Extended table for model fields
 */
class ModelFieldTable extends FormTable {

    /**
     * Name of the table
     * @var string
     */
    const NAME = 'table-model-field';

    /**
     * Constructs a new model field table
     * @param \ride\library\i18n\translator\Translator $translator
     * @param \ride\library\orm\definition\ModelTable $table Table containing the fields
     * @param string $modelAction URL to the action of a model
     * @param string $fieldAction URL to the action of a field
     * @return null
     */
    public function __construct(Translator $translator, ModelTable $table, $tableAction, $modelAction = null, $fieldAction = null) {
        $fields = $table->getFields();
        unset($fields[ModelTable::PRIMARY_KEY]);

        parent::__construct($fields, $tableAction, self::NAME);

        $this->addDecorator(new ModelFieldDecorator($translator, $modelAction, $fieldAction));
        $this->addDecorator(new ModelFieldLabelDecorator($translator));
        $this->addDecorator(new ModelFieldFlagsDecorator($translator));
    }

//     /**
//      * Gets the HTML of this table
//      * @return string
//      */
//     public function getHtml() {
//         if ($this->actions) {
//             $this->addDecorator(new ModelFieldOptionDecorator(), null, true);
//         }

//         return parent::getHtml();
//     }

}