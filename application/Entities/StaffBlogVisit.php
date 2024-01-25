<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * StaffBlogVisit Entity representing rows from the `staff_blog_visits` DB table.
 */
class StaffBlogVisit extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'staff_blog_visits';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'UserID'  => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false ],
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
        'UserID'                     => [ 'columns' => [ 'UserID' ], 'type' => 'unique' ],
        'staff_blog_visits_ibfk_1'   => [ 'columns' => [ 'UserID' ], 'type' => 'foreign', 'references' => [ 'table' => 'users_main', 'columns' => [ 'ID' ], ], 'actions' => [ 'ON DELETE CASCADE' ] ],
    ];
}
