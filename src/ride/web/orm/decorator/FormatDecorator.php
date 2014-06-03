<?php

namespace ride\web\orm\decorator;

use ride\library\decorator\Decorator;
use ride\library\orm\entry\format\EntryFormatter;

/**
 * Decorator for a orm entry based on a format
 */
class FormatDecorator implements Decorator {

    /**
     * Instance of the data formatter
     * @var \ride\library\orm\model\data\format\DataFormatter
     */
    protected $entryFormatter;

    /**
     * Format to apply on the data
     * @var string
     */
    protected $format;

    /**
     * Constructs a new format decorator
     * @param \ride\library\orm\entry\format\EntryFormatter $entryFormatter
     * @param string $format
     * @return null
     */
    public function __construct(EntryFormatter $entryFormatter, $format) {
        $this->entryFormatter = $entryFormatter;
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

        return $this->entryFormatter->formatEntry($value, $this->format);
    }

}
