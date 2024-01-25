<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * GeoLite2 Entity representing rows from the `geolite2` DB table.
 */
class GeoLite2 extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'geolite2';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'repos' => 'Repos',
    ];

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'             => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED',    'primary' => true, 'auto_increment' => true ],
        'StartAddress'   => [ 'type' => 'str', 'sqltype' => 'VARBINARY(16)',   'nullable' => false ],
        'EndAddress'     => [ 'type' => 'str', 'sqltype' => 'VARBINARY(16)',   'nullable' => true  ],
        'LocationID'     => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED',    'nullable' => true  ],
        'PostalCode'     => [ 'type' => 'str', 'sqltype' => 'VARCHAR(16)',     'nullable' => true  ],
        'Coordinates'    => [ 'type' => 'str', 'sqltype' => 'POINT',           'nullable' => true  ],
        'AccuracyRadius' => [ 'type' => 'int', 'sqltype' => 'INT(5) UNSIGNED', 'nullable' => true  ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'StartAddress' => [ 'columns' => [ 'StartAddress' ] ],
    ];

    /**
     * __toString object magic function
     * @return string string representation of this IP object.
     *
     * @access public
     */
    public function __toString() {

        if (!empty($this->location->ISOCode)) {
            $isoCode = $this->location->ISOCode;
        } else {
            $isoCode = '_unknown';
        }

        return "{$isoCode}";
    }

    /**
     * __isset returns whether an object property exists or not,
     * this is necessary for lazy loading to function correctly from TWIG
     * @param  string  $name Name of property being checked
     * @return bool          True if property exists, false otherwise
     *
     * @access public
     */
    public function __isset($name) {
        switch ($name) {
            case 'location':
                return true;

            default:
                return parent::__isset($name);
        }
    }

    /**
     * __get returns the property requested, loading it from the DB if necessary,
     * this permits us to perform lazy loading and thus dynamically minimize both
     * memory usage and cache/DB usage.
     * @param  string $name Name of property being accessed
     * @return mixed        Property data (could be anything)
     *
     * @access public
     */
    public function __get($name) {

        switch ($name) {
            case 'location':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->geolite2Locations->load($this->LocationID));
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
