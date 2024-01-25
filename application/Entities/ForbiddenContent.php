<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ForbiddenContent Entity representing rows from the `do_not_upload` DB table.
 */
class ForbiddenContent extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'do_not_upload';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'Name'     => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Comment'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false ],
        'Time'     => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => null, 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Time'     => [ 'columns' => [ 'Time' ] ],
    ];
}
