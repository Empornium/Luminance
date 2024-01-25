<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ClientBlacklist Entity representing rows from the `xbt_client_blacklist` DB table.
 */
class ClientBlacklist extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'xbt_client_blacklist';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'id'       => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'peer_id'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(20)', 'default' => null, 'nullable' => true ],
        'vstring'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(200)', 'default' => '', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'peer_id'  => [ 'columns' => [ 'peer_id' ], 'type' => 'unique' ],
    ];
}
