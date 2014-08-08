<?php

namespace ride\web\orm\table\scaffold\decorator;

use ride\library\html\table\decorator\DataDecorator as LibraryDataDecorator;
use ride\library\html\table\Cell;
use ride\library\html\table\Row;
use ride\library\html\Anchor;
use ride\library\html\Image;
use ride\library\image\exception\ImageException;
use ride\library\image\ImageUrlGenerator;
use ride\library\orm\entry\format\EntryFormatter;
use ride\library\orm\model\Model;

/**
 * Decorator for a orm data object based on the data formats
 */
class DataDecorator extends LibraryDataDecorator {

    /**
     * Meta of the data model
     * @var \ride\library\orm\model\Model
     */
    private $model;

    /**
     * The formatter of the data
     * @var \ride\library\orm\model\data\format\DataFormatter
     */
    private $formatter;

    /**
     * Constructs a new data decorator
     * @param \ride\\libraryimage\ImageUrlGenerator $imageUrlGenerator URL generator for images
     * @param \ride\library\orm\model\Model $model Model The model to format
     * @param string $action URL where the title of the data will point to. Use
     * %id% as placeholder for the primary key of the data
     * @param string $pkField Name of the primary key field
     * @param string $defaultImage Path to the default image of the data
     * @return null
     */
    public function __construct(Model $model, ImageUrlGenerator $imageUrlGenerator = null, $action = null, $pkField = null, $defaultImage = null) {
        parent::__construct($model->getReflectionHelper(), $action, $imageUrlGenerator, $defaultImage);

        if ($pkField) {
            $this->propertyId = $pkField;
        } else {
            $this->propertyId = ModelTable::PRIMARY_KEY;
        }

        $modelMeta = $model->getMeta();
        $this->propertyTitle = $modelMeta->getFormat(EntryFormatter::FORMAT_TITLE);
        $this->propertyTeaser = $modelMeta->getFormat(EntryFormatter::FORMAT_TEASER);
        $this->propertyImage = $modelMeta->getFormat(EntryFormatter::FORMAT_IMAGE);

        if (!$this->propertyImage) {
            $this->imageUrlGenerator = null;
        }

        $this->formatter = $model->getOrmManager()->getEntryFormatter();
        $this->model = $model;
    }

    /**
     * Gets the title of the data
     * @param mixed $data Instance of the data
     * @return string|null
     */
    protected function getDataTitle($data) {
        return $this->formatter->formatEntry($data, $this->propertyTitle);
    }

    /**
     * Gets the teaser of the data
     * @param mixed $data Instance of the data
     * @return string|null
     */
    protected function getDataTeaser($data) {
        if ($this->propertyTeaser) {
            return $this->formatter->formatEntry($data, $this->propertyTeaser);
        }

        return null;
    }

    /**
     * Gets the image for the data
     * @param mixed $data Instance of the data
     * @return string|null Path to the image of the data
     */
    protected function getDataImage($data) {
        if ($this->propertyImage) {
            return $this->formatter->formatEntry($data, $this->propertyImage);
        }

        return null;
    }

}
