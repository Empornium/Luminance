<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Stylesheet Entity representing rows from the `stylesheets` DB table.
 */
class Stylesheet extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'stylesheets';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'          => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Path'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Name'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Description' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Default'     => [ 'type' => 'str', 'sqltype' => "ENUM('0', '1')", 'nullable' => false ],
    ];
}
