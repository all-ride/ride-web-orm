<?php

namespace pallo\web\orm\table\decorator;

use pallo\library\html\table\decorator\Decorator;
use pallo\library\html\table\Cell;
use pallo\library\html\table\Row;
use pallo\library\html\Anchor;
use pallo\library\html\Image;
use pallo\library\image\exception\ImageException;
use pallo\library\image\ImageUrlGenerator;
use pallo\library\orm\model\data\format\DataFormatter;
use pallo\library\orm\model\Model;

/**
 * Decorator for a orm data object based on the data formats
 */
class DataDecorator implements Decorator {

    /**
     * Path to the default data image
     * @var string
     */
    const DEFAULT_IMAGE = 'img/data.png';

    /**
     * Style class for the image of the data
     * @var string
     */
    const STYLE_IMAGE = 'data';

    /**
     * Generator for images
     * @var pallo\image\ImageUrlGenerator
     */
    private $imageUrlGenerator;

    /**
     * Meta of the data model
     * @var pallo\library\orm\model\Model
     */
    private $model;

    /**
     * URL where the title of the data will point to
     * @var string
     */
    private $action;

    /**
     * Path to the default image of the data
     * @var string
     */
    private $defaultImage;

    /**
     * The formatter of the data
     * @var pallo\library\orm\model\data\format\DataFormatter
     */
    private $formatter;

    /**
     * Format for the title of the data
     * @var string
     */
    private $formatTitle;

    /**
     * Format for the teaser of the data
     * @var string
     */
    private $formatTeaser;

    /**
     * Format for the image of the data
     * @var string
     */
    private $formatImage;

    /**
     * Constructs a new data decorator
     * @param pallo\image\ImageUrlGenerator $imageUrlGenerator URL generator for images
     * @param pallo\library\orm\model\Model $model Model The model to format
     * @param string $action URL where the title of the data will point to. Use
     * %id% as placeholder for the primary key of the data
     * @param string $pkField Name of the primary key field
     * @param string $defaultImage Path to the default image of the data
     * @return null
     */
    public function __construct(ImageUrlGenerator $imageUrlGenerator, Model $model, $action = null, $pkField = null, $defaultImage = null) {
        if (!$defaultImage) {
            $defaultImage = self::DEFAULT_IMAGE;
        }

        $this->imageUrlGenerator = $imageUrlGenerator;

        $this->model = $model;
        $this->pkField = $pkField;
        $this->action = $action;
        $this->defaultImage = $defaultImage;
        $this->formatter = $model->getOrmManager()->getDataFormatter();

        $modelTable = $model->getMeta()->getModelTable();

        $this->formatTitle = $modelTable->getDataFormat(DataFormatter::FORMAT_TITLE);
        $this->formatTeaser = $modelTable->getDataFormat(DataFormatter::FORMAT_TEASER, false);
        $this->formatImage = $modelTable->getDataFormat(DataFormatter::FORMAT_IMAGE, false);
    }

    /**
     * Decorates the data in the cell
     * @param pallo\library\html\table\Cell $cell Cell to decorate
     * @param pallo\library\html\table\Row $row Row containing the cell
     * @param int $rowNumber Number of the current row
     * @param array $remainingValues Array with the values of the remaining rows of the table
     * @return null
     */
    public function decorate(Cell $cell, Row $row, $rowNumber, array $remainingValues) {
        $data = $cell->getValue();

        if (!$this->model->getMeta()->isValidData($data)) {
            $cell->setValue('');

            return;
        }

        $title = $this->formatter->formatData($data, $this->formatTitle);

        $teaser = '';
        if ($this->formatTeaser) {
            $teaser = $this->formatter->formatData($data, $this->formatTeaser);
        }

        $value = $this->getImageHtml($data);

        if ($this->action) {
            $pkField = $this->pkField;
            if (!$pkField) {
                $pkField = ModelTable::PRIMARY_KEY;
            }

            $url = $this->action;
            $url = str_replace('%id%', $data->$pkField, $url);
            $url = str_replace('%25id%25', $data->$pkField, $url);

            if (!$title) {
                $title = $data->$pkField;
            }

            $anchor = new Anchor($title, $url);

            $value .= $anchor->getHtml();
        } else {
            $value .= $title;
        }

        if ($teaser) {
            $value .= '<div class="help-block">' . $teaser . '</div>';
        }

        $cell->setValue($value);
    }

    /**
     * Gets the HTML for the image of the data
     * @param mixed $data
     * @return string
     */
    private function getImageHtml($data) {
        if (!$this->formatImage) {
            return '';
        }

        $image = $this->formatter->formatData($data, $this->formatImage);
        if (!$image) {
            $image = $this->defaultImage;
        }

        try {
            $url = $this->imageUrlGenerator->generateUrl($image, 'crop', 50, 50);

            $image = new Image($url);
            $image->addToClass(self::STYLE_IMAGE);

            return $image->getHtml();
        } catch (ImageException $exception) {
            return '<span style="color: red;">' . $exception->getMessage() . '</span>';
        }
    }

}