<?php

namespace ride\web\orm\decorator;

use ride\library\decorator\Decorator;
use ride\library\orm\model\data\format\DataFormatter;

/**
 * Decorator for a orm data object based on the data formats
 */
class DataFormatDecorator implements Decorator {

    /**
     * Instance of the data formatter
     * @var ride\library\orm\model\data\format\DataFormatter
     */
    protected $dataFormatter;

    /**
     * Format to apply on the data
     * @var string
     */
    protected $format;

    /**
     * Constructs a new data decorator
     * @param ride\library\orm\model\data\format\DataFormatter $dataFormatter
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