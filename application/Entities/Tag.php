<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Tag Entity representing rows from the `tags` DB table.
 */
class Tag extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'tags';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'Name'     => [ 'type' => 'str', 'sqltype' => 'VARCHAR(100)', 'default' => 'NULL', 'nullable' => true ],
        'TagType'  => [ 'type' => 'str', 'sqltype' => 'ENUM(\'genre\', \'other\')', 'nullable' => false, 'default' => '\'other\'' ],
        'Uses'     => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '1' ],
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'default' => 'NULL', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Name'     => [ 'columns' => [ 'Name' ], 'type' => 'unique' ],
        'TagType'  => [ 'columns' => [ 'TagType' ] ],
        'Uses'     => [ 'columns' => [ 'Uses' ] ],
        'UserID'   => [ 'columns' => [ 'UserID' ] ],
    ];
}
