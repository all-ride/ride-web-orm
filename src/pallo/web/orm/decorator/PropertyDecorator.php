<?php

namespace pallo\web\orm\decorator;

use pallo\library\decorator\Decorator;
use pallo\library\reflection\ReflectionHelper;

/**
 * Decorator for property of a data object
 */
class PropertyDecorator implements Decorator {

    /**
     * Instance of the reflection helper
     * @var pallo\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Name of the property
     * @var string
     */
    protected $property;

    /**
     * Constructs a new data decorator
     * @param pallo\library\reflection\ReflectionHelper $reflectionHelper
     * @param string $property
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, $property) {
        $this->reflectionHelper = $reflectionHelper;
        $this->property = $property;
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

        return $this->reflectionHelper->getProperty($value, $this->property);
    }

}