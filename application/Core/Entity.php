<?php
namespace Luminance\Core;

use Luminance\Errors\InternalError;

abstract class Entity {

    protected static $table;
    protected static $properties;
    protected static $indexes;

    protected $saved_values = []; # Values as they exist in the DB
    protected $unsaved_values = []; # Values yet to be saved to the DB
    protected $local_values = []; # Values not corresponding to defined properties (will never be saved)

    # static functions

    static function create_from_db($values) {
        $instance = new static();
        $instance->set_saved_values($values);
        return $instance;
    }

    static function get_table() {
        return static::$table;
    }

    static function get_properties() {
        return static::$properties;
    }

    static function get_indexes() {
        return static::$indexes;
    }

    static function is_property($name) {
        return array_key_exists($name, static::$properties);
    }

    static function is_pkey_property($name) {
        $result = (
            self::is_property($name) &&
            array_key_exists('primary', static::$properties[$name]) &&
            static::$properties[$name]
        );
        return $result;
    }

    static function get_pkey_property() {
        foreach (static::$properties as $name => $options) {
            if (self::is_pkey_property($name)) {
                return $name;
            }
        }
        return null;
    }

    static function cast_for_property($name, $value) {
        if (array_key_exists('nullable', static::$properties[$name]) && static::$properties[$name]['nullable'] && is_null($value)) {
            return null;
        }
        $type = static::$properties[$name]['type'];
        switch ($type) {
            case 'int':
                return intval($value);
            case 'str':
                return strval($value);
            case 'bool':
                return boolval($value); # Since PHP 5.5
            case 'timestamp':
                if ($value instanceof \Datetime) {
                    return $value->format('Y-m-d H:i:s');
                } else {
                    return strval($value);
                }

            default:
                throw new InternalError("Unable to cast value to type {$type}.");
        }
    }

    static function unflatten_from_property($name, $value) { # For lack of a better name...
        if (array_key_exists('nullable', static::$properties[$name]) && static::$properties[$name]['nullable'] && is_null($value)) {
            return null;
        }
        $type = static::$properties[$name]['type'];
        switch ($type) {
            case 'timestamp':
                return new \DateTime($value);
            default:
                return $value;
        }
    }


    static function get_auto_increment_column() {
        foreach (static::$properties as $name => $options) {
            if (array_key_exists('auto_increment', $options) && $options['auto_increment']) {
                return $name; # There can only be a single auto_increment column per table
            }
        }
        return null;
    }

    # public functions

    public function __construct($values = null) {
        $this->set_defaults();
        if (!is_null($values)) {
            $this->set_unsaved_values($values);
        }
    }

    public function print_state() {
        print("* Entity of type: ".get_class($this)." stored in table `".static::$table."`\n");
        print("  Exists in DB: ".intval($this->exists_in_db())."\n");
        print("  Needs_saving: ".intval($this->needs_saving())."\n");
        print("  Saved values:\n");
        foreach ($this->saved_values as $name => $value) {
            print("  - {$name}: {$value}\n");
        }
        print("  Unsaved values:\n");
        foreach ($this->unsaved_values as $name => $value) {
            print("  - {$name}: {$value}\n");
        }
        print("  Local values:\n");
        foreach ($this->local_values as $name => $value) {
            print("  - {$name}: {$value}\n");
        }
    }

    public function set_defaults() {
        foreach (static::$properties as $name => $options) {
            $this->unsaved_values[$name] = null; # TODO: configurable defaults & correct values for non-nullable columns
        }
    }

    public function get_saved_values() {
        return $this->saved_values;
    }

    public function get_unsaved_values() {
        return $this->unsaved_values;
    }

    public function get_pkey_value() {
        $pkey_property = static::get_pkey_property();
        $pkey_value = $this->saved_values[$pkey_property];
        return $pkey_value;
    }

    public function exists_in_db() {
        $pkey_property = self::get_pkey_property();
        return isset($this->saved_values[$pkey_property]);
    }

    public function needs_saving() {
        $this->cleanup_unsaved_values();
        return boolval(count($this->unsaved_values));
    }

    public function cleanup_unsaved_values() {
        foreach ($this->unsaved_values as $name => $value) {
            if (isset($this->saved_values[$name]) && $this->saved_values[$name] === $value) {
                unset($this->unsaved_values[$name]);
            }
        }
    }

    public function set_unsaved_values($values) {
        foreach ($values as $name => $value) {
            if (!is_property($name)) {
                throw new InternalError("Can't set Entity value which isn't a defined property: {$name}.");
            }
            $this->unsaved_values[$name] = $value;
        }
    }

    public function set_saved_values($values) {
        foreach ($values as $name => $value) {
            if (!self::is_property($name)) {
                # Would previously throw an error, but that breaks the site when there are locally created DB columns.
                continue;
            }
            $this->saved_values[$name] = $value;
            if (array_key_exists($name, $this->unsaved_values)) {
                unset($this->unsaved_values[$name]);
            }
        }
    }

    public function __set($name, $value) {
        if (self::is_property($name)) {
            $this->set_property($name, $value);
        } else {
            $this->local_values[$name] = $value;
        }
    }

    public function __get($name) {
        if (self::is_property($name)) {
            return $this->get_property($name);
        } else {
            return $this->local_values[$name];
        }
    }

    public function __isset($name) {
        if (self::is_property($name)) {
            return $this->isset_property($name);
        } else {
            return (isset($this->local_values[$name]) || isset($this->$name));
        }
    }

    public function __unset($name) {
        if (self::is_property($name)) {
            # There will still be some value in the DB (even if it's null), so unsetting it could lead to unexpected results.
            throw new InternalError("Cannot unset defined Entity property: {$name}.");
        } else {
            unset($this->local_values[$name]);
        }
    }

    public function set_property($name, $value) {
        if (self::is_pkey_property($name) && $this->exists_in_db()) {
            throw new InternalError("Cannot change primary key values for objects in database.");
        }
        $this->unsaved_values[$name] = self::cast_for_property($name, $value);
    }

    public function get_property($name) {
        if (array_key_exists($name, $this->unsaved_values)) {
            $value = $this->unsaved_values[$name];
        } else {
            $value = $this->saved_values[$name];
        }
        return self::unflatten_from_property($name, $value);
    }

    public function isset_property($name) {
        return (isset($this->saved_values[$name]) || isset($this->unsaved_values[$name]));
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
        # Useful for setting a flag's status from an existing boolean var
        if ($status) {
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

}
