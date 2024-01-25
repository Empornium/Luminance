<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * RequestFlood Entity representing rows from the `request_flood` DB table.
 */
class RequestFlood extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'request_flood';

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
        'ID'          => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'UserID'      => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'IPID'        => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'Type'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(16)',  'nullable' => false],
        'LastRequest' => [ 'type' => 'timestamp', 'nullable' => true ],
        'Requests'    => [ 'type' => 'int', 'nullable' => true ],
        'IPBans'      => [ 'type' => 'int', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID' => [ 'columns' => [ 'IPID' ] ],
        'IPID'   => [ 'columns' => [ 'IPID' ] ],
        'Type'   => [ 'columns' => [ 'Type' ] ],
    ];


    /**
     * __isset returns whether an object property exists or not,
     * this is necessary for lazy loading to function correctly from TWIG
     * @param  string  $name Name of property being checked
     * @return bool          True if property exists, false otherwise
     */
    public function __isset($name) {
        switch ($name) {
            case 'ip':
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
     */
    public function __get($name) {
        switch ($name) {
            case 'ip':
                return $this->repos->ips->load($this->IPID);

            default:
                return parent::__get($name);
        }
    }
}
