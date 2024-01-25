<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * CollageSubscription Entity representing rows from the `collages_subscriptions` DB table.
 */
class CollageSubscription extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'collages_subscriptions';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'UserID'     => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'primary' => true ],
        'CollageID'  => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'primary' => true ],
        'LastVisit'  => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => 'NULL', 'nullable' => true ],
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
        'CollageID'  => [ 'columns' => [ 'CollageID' ] ],
    ];
}
