<?php

namespace ride\web\orm\form\row;

use ride\application\orm\geo\entry\GeoLocationEntry;
use ride\application\orm\geo\model\GeoLocationModel;

use ride\library\validation\factory\ValidationFactory;

use ride\web\form\row\AutoCompleteStringRow;
use ride\web\WebApplication;

/**
 * Geo location row
 */
class GeoLocationRow extends AutoCompleteStringRow {

    /**
     * Name of the geo location type option
     * @var string
     */
    const OPTION_TYPE = 'type';

    /**
     * Name of the filter option
     * @var string
     */
    const OPTION_FILTER = 'filter';

    /**
     * Instance of the geo location model
     * @var \ride\application\orm\geo\model\GeoLocationModel
     */
    protected $model;

    /**
     * Instance of the web application
     * @var \ride\web\WebApplication
     */
    protected $web;

    /**
     * Constructs a new form row
     * @param string $name Name of the row
     * @param array $options Extra options for the row or type implementation
     * @return null
     */
    public function __construct($name, array $options) {
        parent::__construct($name, $options);

        $this->setOption(self::OPTION_AUTO_COMPLETE_MIN_LENGTH, 2);
        $this->setOption(self::OPTION_AUTO_COMPLETE_MAX_ITEMS, $this->isMultiple() ? 0 : 1);
        $this->setOption(self::OPTION_AUTO_COMPLETE_TYPE, 'jsonapi');
        $this->setOption(self::OPTION_MULTIPLE, false);
    }

    /**
     * Sets a tag handler to this row
     * @param \ride\library\form\row\TagHandler $tagHandler
     * @return null
     */
    public function setGeoLocationModel(GeoLocationModel $geoLocationModel) {
        $this->model = $geoLocationModel;
    }

    /**
     * Sets the web application
     * @param \ride\web\WebApplication $app
     * @return null
     */
    public function setWebApplication(WebApplication $web) {
        $this->web = $web;
    }

    /**
     * Performs necessairy build actions for this row
     * @param string $namePrefix Prefix for the row name
     * @param string $idPrefix Prefix for the field id
     * @param \ride\library\validation\factory\ValidationFactory $validationFactory
     * @return null
     */
    public function buildRow($namePrefix, $idPrefix, ValidationFactory $validationFactory) {
        $this->setGeoLocationOptions();

        parent::buildRow($namePrefix, $idPrefix, $validationFactory);
    }

    /**
     * Sets the necessairy options for this row to work
     * @return null
     */
    protected function setGeoLocationOptions() {
        $expression = array();

        $type = $this->getOption(self::OPTION_TYPE);
        if ($type) {
            $expression[] = '{type} = "' . $type . '"';
        }

        $filter = $this->getOption(self::OPTION_FILTER);
        if ($filter) {
            if (is_string($filter)) {
                $expression[] = '{path} LIKE "%~' . $filter . '~%"';
            } elseif ($filter instanceof GeoLocationEntry) {
                $expression[] = '{path} LIKE "' . $filter->getPath() . '~%"';
            }
        }

        $expression[] = '({name} LIKE "%%term%%" OR {code} LIKE "%%term%%")';

        $queryParameters = array(
            'list' => 1,
            'fields' => array(
                'geo-locations' => 'name',
            ),
            'filter' => array(
                'expression' => implode(' AND ', $expression),
            ),
        );
        $url = $this->web->getUrl('api.orm.entry.index', array('type' => 'geo-locations'), $queryParameters);

        $this->setOption(self::OPTION_AUTO_COMPLETE_URL, $url);
    }

    /**
     * Processes the request and updates the data of this row
     * @param array $values Submitted values
     * @return null
     */
    public function processData(array $values) {
        $isChanged = false;

        if (!isset($values[$this->name])) {
            return;
        }

        if ($this->getOption(self::OPTION_AUTO_COMPLETE_MAX_ITEMS) == 1) {
            $this->data = $this->getGeoLocationByString($values[$this->name]);
        } else {
            $this->data = explode(',', $values[$this->name]);
            foreach ($this->data as $index => $value) {
                $value = $this->getGeoLocationByString($value);
                if ($value) {
                    $this->data[$index] = $value;
                } else {
                    unset($this->data[$index]);
                }
            }
        }
    }

    /**
     * Sets the data to this row
     * @param mixed $data
     * @return null
     */
    public function setData($data) {
        $this->data = $data;

        if ($this->widget) {
            $this->widget->setValue($this->getStringForGeoLocation($data));
        }
    }

    /**
     * Creates the widget for this row
     * @param string $name
     * @param mixed $default
     * @param array $attributes
     * @return \ride\library\form\widget\Widget
     */
    protected function createWidget($name, $default, array $attributes) {
        $default = $this->getStringForGeoLocation($default);

        return parent::createWidget($name, $default, $attributes);
    }

    /**
     * Gets a string for the provided value
     * @param mixed $value
     * @return string
     */
    protected function getStringForGeoLocation($value) {
        if (!$value) {
            return '';
        }

        if ($this->getOption(self::OPTION_AUTO_COMPLETE_MAX_ITEMS) != 1 && is_array($value)) {
            foreach ($value as $index => $geoLocation) {
                if ($geoLocation instanceof GeoLocationEntry) {
                    $value[$index] = $this->getGeoLocationString($geoLocation);
                }
            }

            $value = implode(',', $value);
        } elseif ($value instanceof GeoLocationEntry) {
            $value = $this->getGeoLocationString($value);
        }

        return $value;
    }

    /**
     * Gets the string representation of the provided geo location
     * @param \ride\application\orm\geo\entry\GeoLocationEntry $geoLocation
     * @return string
     */
    protected function getGeoLocationString(GeoLocationEntry $geoLocation) {
        $locale = $this->getOption(self::OPTION_LOCALE);
        if ($geoLocation->getLocale() != $locale) {
            $geoLocation = $this->model->getById($geoLocation->getId(), $locale);
        }

        return $geoLocation->getName() . ' (' . $geoLocation->getCode() . ')';
    }

    /**
     * Gets the requested GeoLocation based on the submitted value
     * @param string $value
     * @return \ride\application\orm\geo\entry\GeoLocationEntry|null
     */
    protected function getGeoLocationByString($value) {
        if (!$value) {
            return null;
        }

        $options = array(
            'filter' => array(
                'name' => $value,
            ),
        );

        if (substr($value, -1) === ')') {
            $position = strrpos($value, '(');
            if ($position) {
                $options['filter']['name'] = substr($value, 0, $position - 1);
                $options['filter']['code'] = substr($value, $position + 1, -1);
            }
        }

        return $this->model->getBy($options);
    }

}
