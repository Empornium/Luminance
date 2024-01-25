<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Session Entity representing rows from the `sessions` DB table.
 */
class Session extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'sessions';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'IPID'     => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'ClientID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Active'   => [ 'type' => 'bool', 'nullable' => false ],
        'Flags'    => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false ],
        'Created'  => [ 'type' => 'timestamp', 'nullable' => true ],
        'Updated'  => [ 'type' => 'timestamp', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID'   => [ 'columns' => [ 'UserID' ] ],
        'ClientID' => [ 'columns' => [ 'ClientID' ] ],
        'Active'   => [ 'columns' => [ 'Active' ] ],
        'Flags'    => [ 'columns' => [ 'Flags' ] ],
        'Created'  => [ 'columns' => [ 'Created' ] ],
        'Updated'  => [ 'columns' => [ 'Updated' ] ],
    ];

    const KEEP_LOGGED_IN = 1 << 0;
    const IP_LOCKED      = 1 << 1;
    const TWO_FACTOR     = 1 << 2;
    const LEGACY         = 1 << 7;

    /**
     * isTwoFactor Returns wether this session object has a TWO_FACTOR flag set.
     * @return bool    True or false.
     *
     * @access public
     */
    public function isTwoFactor() {
        return $this->getFlag(self::TWO_FACTOR);
    }
}
