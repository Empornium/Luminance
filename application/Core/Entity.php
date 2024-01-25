<?php
namespace Luminance\Core;

use Luminance\Errors\InternalError;

abstract class Entity {

    protected static $table;
    protected static $properties = [];
    protected static $indexes    = [];
    protected static $attributes = [];
    protected static $useServices = [];

    protected $master;
    protected $request;

    protected $savedValues   = []; # Values as they exist in the DB
    protected $unsavedValues = []; # Values yet to be saved to the DB
    protected $localValues   = []; # Values not corresponding to defined properties (will never be saved)

    public $casToken = null; # Used to update entity in cache

    const CACHE_EXPIRATION = 0;

    # static functions

    public static function createFromDB($values, &$casToken = null) {
        $instance = new static();
        $instance->setSavedValues($values);
        $instance->casToken = $casToken;
        return $instance;
    }

    public static function getTable() {
        return static::$table;
    }

    public static function getProperties() {
        return static::$properties;
    }

    public static function getIndexes() {
        return static::$indexes;
    }

    public static function getAttributes() {
        return static::$attributes;
    }

    public static function isProperty($name) {
        return array_key_exists($name, static::$properties);
    }

    public static function isPKeyProperty($name) {
        return in_array($name, self::getPKeyProperties());
    }

    public static function getPKeyProperties() {
        return array_keys(array_filter(static::$properties, function ($property) {
            if (array_key_exists('primary', $property)) {
                return true;
            }
        }));
    }

    public static function castForProperty($name, $value) {
        if (array_key_exists('nullable', static::$properties[$name]) && static::$properties[$name]['nullable'] && is_null($value)) {
            return null;
        }
        $type = static::$properties[$name]['type'];
        switch ($type) {
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            case 'str':
                if (is_array($value)) {
                    throw new InternalError;
                }
                return strval($value);
            case 'bool':
                return (int)boolval($value); # Since PHP 5.5
            case 'timestamp':
                if ($value instanceof \DateTime) {
                    return $value->format('Y-m-d H:i:s');
                } else {
                    return strval($value);
                }

            default:
                throw new InternalError("Unable to cast value to type {$type}.");
        }
    }

    # For lack of a better name...
    public static function unflattenFromProperty($name, $value) {
        if (array_key_exists('nullable', static::$properties[$name]) && static::$properties[$name]['nullable'] && is_null($value)) {
            return null;
        }
        $type = static::$properties[$name]['type'];
        switch ($type) {
            case 'bool':
                return boolval($value);
            case 'timestamp':
                if ($value === '0000-00-00 00:00:00') {
                    return null;
                }
                return new \DateTime($value);
            default:
                return $value;
        }
    }

    public static function getAutoIncrementColumn() {
        foreach (static::$properties as $name => $options) {
            if (array_key_exists('auto_increment', $options) && $options['auto_increment']) {
                return $name; # There can only be a single auto_increment column per table
            }
        }
        return null;
    }

    # public functions

    final public function __construct($values = null) {
        global $master;
        $this->master  = &$master;
        $this->request = &$this->master->request;

        $this->setDefaults();
        if (!is_null($values)) {
            $this->setUnsavedValues($values);
        }

        $this->prepareServices();
    }

    protected function prepareServices() {
        foreach (static::$useServices as $localName => $serviceName) {
            $this->{$localName} = $this->master->getService($serviceName);
        }
    }

    public function printState() {
        $print = PHP_EOL;
        $print .= "* Entity of type: ".get_class($this)." stored in table `".static::$table."`".PHP_EOL;
        $print .= "  Exists in DB: ".intval($this->existsInDB()).PHP_EOL;
        $print .= "  needsSaving: ".intval($this->needsSaving()).PHP_EOL;
        $print .= "  Saved values:\n";
        foreach ($this->savedValues as $name => $value) {
            $print .= "  - {$name}: {$value}".PHP_EOL;
        }
        $print .= "  Unsaved values:".PHP_EOL;
        foreach ($this->unsavedValues as $name => $value) {
            $print .= "  - {$name}: {$value}".PHP_EOL;
        }
        return $print;
    }

    public function setDefaults() {
        foreach (static::$properties as $name => $options) {
            if (array_key_exists('default', $options)) {
                $this->unsavedValues[$name] = $options['default'];
            } else {
                $this->unsavedValues[$name] = null;
            }
        }
    }

    public function getSavedValues() {
        return $this->savedValues;
    }

    public function getUnsavedValues() {
        return $this->unsavedValues;
    }

    public function getPKeyValues() {
        $pKeyProperties = self::getPKeyProperties();
        $pKeyValue = [];
        foreach ($pKeyProperties as $pKeyProperty) {
            if (array_key_exists($pKeyProperty, $this->unsavedValues)) {
                $pKeyValue[] = $this->unsavedValues[$pKeyProperty];
            } else {
                $pKeyValue[] = $this->savedValues[$pKeyProperty];
            }
        }
        return $pKeyValue;
    }

    public function existsInDB() {
        # Assume entity exists if it has any saved values.
        return !empty($this->savedValues);

        # The old method (below) would fail for entities which do not use
        # an auto-increment PKey column. :-(
        /*
         * $pKeyProperties = self::getPKeyProperties();
         * foreach ($pKeyProperties as $pKeyProperty) {
         *     if (!isset($this->savedValues[$pKeyProperty])) {
         *         return false;
         *     }
         * }
         * return true;
         */
    }

    public function needsSaving() {
        $this->cleanupUnsavedValues();
        return boolval(count($this->unsavedValues));
    }

    public function cleanupUnsavedValues() {
        foreach ($this->unsavedValues as $name => $value) {
            if (isset($this->savedValues[$name]) && $this->savedValues[$name] === $value) {
                unset($this->unsavedValues[$name]);
            }
        }
    }

    public function setUnsavedValues($values) {
        foreach ($values as $name => $value) {
            if (!self::isProperty($name)) {
                throw new InternalError("Can't set Entity value which isn't a defined property: {$name}.");
            }
            $this->setProperty($name, $value);
        }
    }

    public function setSavedValues($values) {
        foreach ($values as $name => $value) {
            if (!self::isProperty($name)) {
                # Would previously throw an error, but that breaks the site when there are locally created DB columns.
                continue;
            }
            $this->savedValues[$name] = $value;
            if (array_key_exists($name, $this->unsavedValues)) {
                unset($this->unsavedValues[$name]);
            }
        }
    }

    public function __set($name, $value) {
        if (self::isProperty($name)) {
            $this->setProperty($name, $value);
        } else {
            $this->localValues[$name] = $value;
        }
    }

    public function safeSet($name, $value) {
        if (!array_key_exists($name, $this->localValues)) {
            # Don't self-cache null results
            if (is_null($value) === false) {
                self::__set($name, $value);
            } else {
                return null;
            }
        }
        return self::__get($name);
    }

    public function __get($name) {
        if (self::isProperty($name)) {
            return $this->getProperty($name);
        } else {
            if (array_key_exists($name, $this->localValues)) {
                return $this->localValues[$name];
            } else {
                return null;
            }
        }
    }

    public function __isset($name) {
        if (self::isProperty($name)) {
            return $this->issetProperty($name);
        } else {
            return (isset($this->localValues[$name]) || isset($this->$name));
        }
    }

    public function __unset($name) {
        if (self::isProperty($name)) {
            # There will still be some value in the DB (even if it's null), so unsetting it could lead to unexpected results.
            throw new InternalError("Cannot unset defined Entity property: {$name}.");
        } else {
            unset($this->localValues[$name]);
        }
    }

    public function __sleep() {
        return ['savedValues', 'unsavedValues'];
    }

    /**
     * Return meaningful debug information from entity objects
     * @return array Array containing the data related to DB columns
     */
    public function __debugInfo() {
        return [
            'savedValues'   => $this->savedValues,
            'unsavedValues' => $this->unsavedValues,
            'casToken'       => $this->casToken,
        ];
    }

    public function setProperty($name, $value) {
        if (self::isPKeyProperty($name) && $this->existsInDB()) {
            throw new InternalError("Cannot change primary key values for objects in database.");
        }
        $this->unsavedValues[$name] = self::castForProperty($name, $value);
    }

    public function getProperty($name) {
        if (array_key_exists($name, $this->unsavedValues)) {
            $value = $this->unsavedValues[$name];
        } else {
            $value = $this->savedValues[$name];
        }
        return self::unflattenFromProperty($name, $value);
    }

    public function issetProperty($name) {
        return (isset($this->savedValues[$name]) || isset($this->unsavedValues[$name]));
    }

    public function setFlags($flags, $column = 'Flags') {
        # Set bitwise flags, normally specified through class constants defined on the entity
        $current = $this->$column;
        $new = $current | $flags;
        $this->$column = $new;
    }

    public function unsetFlags($flags, $column = 'Flags') {
        $current = $this->$column;
        $new = $current & (~ $flags);
        $this->$column = $new;
    }

    public function setFlagStatus($flag, $status, $column = 'Flags') {
        # Useful for setting a flag's status from an existing bool var
        if (!empty($status)) {
            $this->setFlags($flag, $column);
        } else {
            $this->unsetFlags($flag, $column);
        }
    }

    public function getFlag($flag, $column = 'Flags') {
        $current = $this->$column;
        $value = boolval($current & $flag);
        return $value;
    }

    public function hasExpired($column = 'Expires') {
        # Compare apples to apples
        $threshold = new \DateTime();
        $expires  = $this->$column;

        # Make sure we have a DateTime object
        if (!$expires instanceof \DateTime)
            $expires = new \DateTime($expires);

        return $expires < $threshold;
    }
}
