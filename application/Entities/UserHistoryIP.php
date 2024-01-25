<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * UserHistoryIP Entity representing rows from the `users_history_ips` DB table.
 */
class UserHistoryIP extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'users_history_ips';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'db'    => 'DB',
        'repos' => 'Repos',
    ];

    #ToDo Make this IPv6 capable using ips table
    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'         => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'UserID'     => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false ],
        'IPID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'StartTime'  => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'nullable' => false, 'default' => '\'0000-00-00 00:00:00\'' ],
        'EndTime'    => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => null, 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID'     => [ 'columns' => [ 'UserID' ] ],
        'IPID'       => [ 'columns' => [ 'IPID' ] ],
        'StartTime'  => [ 'columns' => [ 'StartTime' ] ],
        'EndTime'    => [ 'columns' => [ 'EndTime' ] ],
    ];

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
            case 'ip':
            case 'dupes':
            case 'dupeCount':
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
            case 'ip':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->ips->load($this->IPID));
                }
                break;
            case 'dupes':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->userHistoryIPs->find(
                        'IPID = ? AND UserID != ?',
                        [$this->IPID, $this->UserID],
                        'StartTime',
                        '50'
                    ));
                }
                break;
            case 'dupeCount':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->db->rawQuery(
                        "SELECT COUNT(*)
                           FROM users_history_ips
                          WHERE IPID = ?
                            AND UserID != ?",
                        [$this->IPID, $this->UserID]
                    )->fetchColumn());
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
