<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * APIKey Entity representing rows from the `api_keys` DB table.
 */
class APIKey extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'api_keys';

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
        'ID'           => [ 'type' => 'int',       'sqltype' => 'INT(11)',          'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'UserID'       => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => false ],
        'IPID'         => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => false ],
        'Key'          => [ 'type' => 'str',       'sqltype' => 'CHAR(32)',         'nullable' => false ],
        'Description'  => [ 'type' => 'str',       'sqltype' => 'VARCHAR(100)',     'nullable' => true, 'default' => null],
        'Created'      => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',         'nullable' => true, 'default' => null],
        'Flags'        => [ 'type' => 'int',       'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID'  => [ 'columns' => [ 'UserID' ] ],
        'Key'     => [ 'columns' => [ 'Key' ], 'type' => 'unique' ],
    ];


    const CANCELLED  = 1 << 0;

    /**
     * isCancelled Returns whether or not the CANCELLED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isCancelled() {
        return $this->getFlag(self::CANCELLED);
    }

    /**
     * __toString object magic function
     * @return string API key represented by this object as a string
     *
     * @access public
     */
    public function __toString() {
        return $this->Key;
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
            case 'user':
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
     *
     * @access public
     */
    public function __get($name) {

        switch ($name) {
            case 'user':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->UserID));
                }
                break;

            case 'ip':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->ips->load($this->IPID));
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
