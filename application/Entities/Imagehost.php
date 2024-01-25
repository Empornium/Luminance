<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Imagehost Entity representing rows from the `imagehost_whitelist` DB table.
 */
class Imagehost extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'imagehost_whitelist';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'         => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'Imagehost'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Link'       => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Comment'    => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'UserID'     => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false ],
        'Time'       => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'nullable' => false ],
        'Hidden'     => [ 'type' => 'str', 'sqltype' => "ENUM('0','1')", 'nullable' => false, 'default' => '0' ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Time'       => [ 'columns' => [ 'Time' ] ],
        'Hidden'     => [ 'columns' => [ 'Hidden' ] ],
    ];
}
