<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * UserHistoryASN Entity representing rows from the `users_history_asns` DB table.
 */
class UserHistoryASN extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'users_history_asns';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'UserID'     => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'primary' => true ],
        'ASN'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false, 'primary' => true ],
        'StartTime'  => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => null, 'nullable' => true ],
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
        'ASN'        => [ 'columns' => [ 'ASN' ] ],
        'StartTime'  => [ 'columns' => [ 'StartTime' ] ],
        'EndTime'    => [ 'columns' => [ 'EndTime' ] ],
    ];
}
