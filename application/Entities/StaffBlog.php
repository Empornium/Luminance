<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * StaffBlog Entity representing rows from the `staff_blog` DB table.
 */
class StaffBlog extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'staff_blog';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'      => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'UserID'  => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false ],
        'Title'   => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Body'    => [ 'type' => 'str', 'sqltype' => 'MEDIUMTEXT', 'nullable' => false ],
        'Time'    => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => 'NULL', 'nullable' => true ],
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
        'Time'    => [ 'columns' => [ 'Time' ] ],
    ];
}
