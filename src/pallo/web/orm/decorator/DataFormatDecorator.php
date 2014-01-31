<?php

namespace pallo\web\orm\decorator;

use pallo\library\decorator\Decorator;
use pallo\library\orm\model\data\format\DataFormatter;

/**
 * Decorator for a orm data object based on the data formats
 */
class DataFormatDecorator implements Decorator {

    /**
     * Instance of the data formatter
     * @var pallo\library\orm\model\data\format\DataFormatter
     */
    protected $dataFormatter;

    /**
     * Format to apply on the data
     * @var string
     */
    protected $format;

    /**
     * Constructs a new data decorator
     * @param pallo\library\orm\model\data\format\DataFormatter $dataFormatter
     * @param string $format
     * @param string $property
     * @return null
     */
    public function __construct(DataFormatter $dataFormatter, $format) {
        $this->dataFormatter = $dataFormatter;
        $this->format = $format;
    }

    /**
     * Decorates the value
     * @param mixed $value Value to decorate
     * @return string Decorated value
     */
    public function decorate($value) {
        if (!is_object($value)) {
            return $value;
        }

        return $this->dataFormatter->formatData($value, $this->format);
    }

}