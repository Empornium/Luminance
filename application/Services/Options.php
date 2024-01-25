<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Entities\Option;

use Luminance\Errors\InternalError;

class Options extends Service {

    /*
     *  Options array required values:
     * value          - default value for option
     * section        - section in GUI
     * type           - datatype for validation
     * description    - description for GUI
     *
     *  Options array optional values:
     * displayRow     - optional row for GUI
     * displayCol     - optional col for GUI
     * perm           - optional permission check
     * updateTracker  - optional update tracker with change
     * validation     - optional additional validation
     */

    protected static $defaultOptions = [];

    protected static $useServices = [
        'repos' => 'Repos',
    ];

    private $sitewideFreeleech = null;
    private $sitewideDoubleseed = null;

    public function __construct(Master $master) {
        parent::__construct($master);
    }

    public function getSections() {
        $sections = [];
        foreach (static::$defaultOptions as $name => $option) {
            if (!in_array($option['section'], $sections))
                $sections[] = $option['section'];
        }
        sort($sections);
        return $sections;
    }

    public function getSitewideFreeleech() {
        if ($this->sitewideFreeleech === null) {
            if ($this->SitewideFreeleechMode === 'perma') {
                $sitewideFreeleech = true;
            } elseif ($this->SitewideFreeleechMode === 'timed') {
                $freeleechStarted = $this->SitewideFreeleechStartTime < strtotime(sqltime());
                $freeleechEnded   = $this->SitewideFreeleechEndTime < strtotime(sqltime());
                if ($freeleechStarted === true && $freeleechEnded === false && $this->SitewideFreeleechMode === 'timed') {
                    $sitewideFreeleech = $this->SitewideFreeleechEndTime;
                } else {
                    $sitewideFreeleech = false;
                }
            } else {
                $sitewideFreeleech = false;
            }

            $this->sitewideFreeleech = $sitewideFreeleech;
        }

        return $this->sitewideFreeleech;
    }

    public function getSitewideDoubleseed() {
        if ($this->sitewideDoubleseed === null) {
            if ($this->SitewideDoubleseedMode === 'perma') {
                $sitewideDoubleseed = true;
            } elseif ($this->SitewideDoubleseedMode === 'timed') {
                $doubleseedStarted = $this->SitewideDoubleseedStartTime < strtotime(sqltime());
                $doubleseedEnded   = $this->SitewideDoubleseedEndTime < strtotime(sqltime());
                if ($doubleseedStarted === true && $doubleseedEnded === false && $this->SitewideDoubleseedMode === 'timed') {
                    $sitewideDoubleseed = $this->SitewideDoubleseedEndTime;
                } else {
                    $sitewideDoubleseed = false;
                }
            } else {
                $sitewideDoubleseed = false;
            }

            $this->sitewideDoubleseed = $sitewideDoubleseed;
        }

        return $this->sitewideDoubleseed;
    }

    /* static compare function to sort by display: */
    private static function compareDisplay($optiona, $optionb) {
        if ($optiona['displayRow'] === $optionb['displayRow']) {
            if ($optiona['displayCol'] === $optionb['displayCol']) return 0;
            return ($optiona['displayCol'] > $optionb['displayCol']) ? +1 : -1;
        }
        return ($optiona['displayRow'] > $optionb['displayRow']) ? +1 : -1;
    }

    public function getAll($section = null) {
        $options = static::$defaultOptions;
        foreach (array_keys($options) as $name) {
            $options[$name]['value'] = $this->__get($name);
        }

        if (is_null($section)) {
            return $options;
        } else {
            foreach ($options as $name => $option) {
                if (!($option['section'] === $section))
                    unset($options[$name]);
            }
            uasort($options, [self::class, 'compareDisplay']);
            return $options;
        }
    }

    public static function register($pluginOptions) {
        # defaults last so that framework keys take priority
        static::$defaultOptions = array_merge($pluginOptions, static::$defaultOptions);
    }

    public function reset() {
        $this->master->auth->checkAllowed('admin_manage_site_options');
        foreach (static::$defaultOptions as $option => $default) {
            # use set in case tracker needs poked
            $this->__set($option, $default['value']);
        }
    }

    protected function setType($option) {
        if (!array_key_exists($option->Name, static::$defaultOptions)) {
            throw new InternalError("Unknown Option: {$option->Name}");
        }

        $type = static::$defaultOptions[$option->Name]['type'];
        $value = $option->Value;

        switch ($type) {
            case 'enum':
                $value = (string) $value;
                break;
            case 'date':
                #$value = new \DateTime($value);
                break;
            case 'int':
            case 'bool':
            case 'float':
            case 'double':
            case 'string':
                settype($value, $type);
                break;
        }
        return $value;
    }

    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        } else {
            try {
                $option = $this->repos->options->load($name);
                if ($option instanceof Option) {
                    return $this->setType($option);
                } else if (array_key_exists($name, static::$defaultOptions)) {
                    return static::$defaultOptions[$name]['value'];
                } else {
                    throw new InternalError("Unknown Option: {$name}");
                }
            } catch (\PDOException $e) {
                return static::$defaultOptions[$name]['value'];
            }
        }
    }

    public function getType($name) {
        if (array_key_exists($name, static::$defaultOptions)) {
            return static::$defaultOptions['type'];
        }

        return null;
    }

    public function __set($name, $value) {
        if (array_key_exists($name, static::$defaultOptions)) {
            $this->master->auth->checkAllowed('admin_manage_site_options');
            if (static::$defaultOptions[$name]['type'] === 'date')
                $value = strtotime($value);
            if (isset(static::$defaultOptions[$name]['perm'])) {
                $this->master->auth->checkAllowed(static::$defaultOptions[$name]['perm']);
            }
            $option = $this->repos->options->load($name);
            if (static::$defaultOptions[$name]['value'] === $value) {
                if ($option instanceof Option) {
                    $this->repos->options->delete($option);
                }
            } else {
                if (!($option instanceof Option)) {
                    $option = new Option();
                    $option->Name = $name;
                }
                $option->Value = $value;
                $this->repos->options->save($option);
            }

            if (array_key_exists('updateTracker', static::$defaultOptions[$name])) {
                if (static::$defaultOptions[$name]['updateTracker']) {
                    # Cludge, we can't use tracker service in the normal way or
                    # it will introduce a circular dependency
                    $this->master->tracker->options($name, $value);
                }
            }
        } else {
            $this->$name = $value;
        }
    }

    public function __isset($sectionName) {
        if (is_array(static::$defaultOptions[$sectionName])) {
            return true;
        }
        return false;
    }
}
