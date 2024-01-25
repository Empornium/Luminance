<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * CollageGroup Entity representing rows from the `collages_groups` DB table.
 */
class CollageGroup extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'collages_groups';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'        => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'GroupID'   => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'CollageID' => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'AddedTime' => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',     'nullable' => true,  'default' => null ],
        'AddedBy'   => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED', 'nullable' => true,  'default' => null ],
        'Comment'   => [ 'type' => 'str',       'sqltype' => 'TEXT',   'nullable' => true,  'default' => null ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'GroupID'   => [ 'columns' => [ 'GroupID' ] ],
        'CollageID' => [ 'columns' => [ 'CollageID' ] ],
    ];
}
