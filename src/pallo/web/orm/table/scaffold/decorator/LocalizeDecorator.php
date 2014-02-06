<?php

namespace pallo\web\orm\table\scaffold\decorator;

use pallo\library\html\table\decorator\ActionDecorator;
use pallo\library\html\table\decorator\Decorator;
use pallo\library\html\table\Cell;
use pallo\library\html\table\Row;
use pallo\library\html\Anchor;
use pallo\library\orm\model\Model;

/**
 * Decorator to view the localized state of data
 */
class LocalizeDecorator implements Decorator {

    /**
     * Style class for a unlocalized data row
     * @var string
     */
    const STYLE_UNLOCALIZED = 'unlocalized';

    /**
     * URL where the locale code should point to
     * @var string
     */
    private $action;

    /**
     * The localized model of the data
     * @var pallo\library\orm\model\LocalizedModel
     */
    private $localizedModel;

    /**
     * Array with the locale codes
     * @var array
     */
    private $locales;

    /**
     * The code of the current localize locale
     * @var string
     */
    private $locale;

    /**
     * Constructs a new localize decorator
     * @param pallo\library\orm\model\Model $model Model of the data
     * @param string $action URL where the locale code should point to
     * @return null
     */
    public function __construct(Model $model, $action, $locale, array $locales) {
        $action = str_replace('/' . $locale . '/', '/%locale%/', $action);
        $action = str_replace('/' . $locale . '?', '/%locale%?', $action);
        $action = str_replace('locale=' . $locale, 'locale=%locale%', $action);
        if (substr($action, -3) == '/' . $locale) {
            $action = substr($action, 0, -2) . '%locale%';
        }

        $this->action = $action;

        $this->meta = $model->getMeta();
        $this->localizedModel = $model->getLocalizedModel();

        $this->locale = $locale;
        $this->locales = $locales;

        unset($this->locales[$this->locale]);
    }

    /**
     * Decorates the data into a locale overview
     * @param pallo\library\html\table\Cell $cell Cell to decorate
     * @param pallo\library\html\table\Row $row Row containing the cell
     * @param int $rowNumber Number of the current row
     * @param array $remainingValues Array with all the values of the remaining rows of the table
     * @return null
     */
    public function decorate(Cell $cell, Row $row, $rowNumber, array $remainingValues) {
        $data = $cell->getValue();
        $value = '';

        if (!$this->meta->isValidData($data)) {
            $cell->setValue($value);

            return;
        }

        if (isset($data->dataLocale) && $data->dataLocale != $this->locale) {
            $row->addToClass(self::STYLE_UNLOCALIZED);
        }

        $ids = $this->localizedModel->getLocalizedIds($data->id);

        foreach ($this->locales as $locale) {
            if (array_key_exists($locale, $ids)) {
                $localeString = '<strong>' . $locale . '</strong>';
            } else {
                $localeString = $locale;
            }

            if ($this->action !== null) {
                $action = str_replace('%id%', $data->id, $this->action);
                $action = str_replace('%locale%', $locale, $action);

                $anchor = new Anchor($localeString, $action);
                $localeString = $anchor->getHtml();
            }

            $value .= ($value == '' ? '' : ' ') . $localeString;
        }

        $cell->setValue($value);
    }

}