<?php

namespace pallo\web\orm\table\builder\decorator;

use pallo\library\html\table\decorator\Decorator;
use pallo\library\html\table\Cell;
use pallo\library\html\table\Row;
use pallo\library\html\Image;
use pallo\library\i18n\translator\Translator;
use pallo\library\orm\definition\field\ModelField;

/**
 * Decorator for the flags of a model field
 */
class ModelFieldFlagsDecorator implements Decorator {

    /**
     * Path to the image of a localized field
     * @var string
     */
    const IMAGE_LOCALIZED = 'img/orm/localized.png';

    /**
     * Translation key for the alternate text of the localized image
     * @var string
     */
    const TRANSLATION_LOCALIZED = 'orm.label.localized';

    /**
     * The HTML of the localized image
     * @var string
     */
    private $localizedImage = null;

    /**
     * Constructs a new model field label decorator
     * @param pallo\library\i18n\translation\Translator $translator
     * @return null
     */
    public function __construct(Translator $translator) {
        $this->translator = $translator;
    }

    /**
     * Decorates the cell
     * @param pallo\library\html\table\Cell $cell Cell of the value to decorate
     * @param pallo\library\html\table\Row $row Row containing the cell
     * @param int $rowNumber Number of the current row
     * @param array $remainingValues Array containing the values of the remaining rows of the table
     * @return null
     */
    public function decorate(Cell $cell, Row $row, $rowNumber, array $remainingValues) {
        $field = $cell->getValue();
        if (!($field instanceof ModelField)) {
            return;
        }

        if (!$field->isLocalized()) {
            $cell->setValue('');

            return;
        }

        $cell->setValue($this->getLocalizedImageHtml());
    }

    /**
     * Gets the HTML of the localized image
     * @return string The HTML of the localized image
     */
    private function getLocalizedImageHtml() {
        if ($this->localizedImage) {
            return $this->localizedImage;
        }

        $image = new Image(self::IMAGE_LOCALIZED);
        $image->setAttribute('title', $this->translator->translate(self::TRANSLATION_LOCALIZED));

        $this->localizedImage = $image->getHtml();

        return $this->localizedImage;
    }

}