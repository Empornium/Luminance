<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * GeoLite2Location Entity representing rows from the `geolite2_locations` DB table.
 */
class GeoLite2Location extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'geolite2_locations';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'          => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary'  => true  ],
        'ISOCode'     => [ 'type' => 'str', 'sqltype' => 'CHAR(2)',      'nullable' => false ],
        'CountryName' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(50)',  'nullable' => false ],
        'RegionName'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(50)',  'nullable' => true  ],
        'CityName'    => [ 'type' => 'str', 'sqltype' => 'VARCHAR(50)',  'nullable' => true  ],
        'MetroCode'   => [ 'type' => 'str', 'sqltype' => 'VARCHAR(50)',  'nullable' => true  ],
    ];

    /**
     * __toString object magic function
     * @return string string representation of this IP object.
     *
     * @access public
     */
    public function __toString() {

        if (!empty($this->CityName)) {
            $city = "{$this->CityName}, ";
        } else {
            $city = '';
        }

        if (!empty($this->RegionName)) {
            $region = "{$this->RegionName}, ";
        } else {
            $region = '';
        }

        if (!empty($this->CountryName)) {
            $country = "{$this->CountryName}";
        } else {
            $country = 'unknown';
        }

        return "{$city}{$region}{$country}";
    }
}
