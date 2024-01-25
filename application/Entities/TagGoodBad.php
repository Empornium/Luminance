<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * TagGoodBad Entity representing rows from the `tags_goodbad` DB table.
 */
class TagGoodBad extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'tags_goodbad';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'Tag'      => [ 'type' => 'str', 'sqltype' => 'VARCHAR(100)', 'default' => 'NULL', 'nullable' => true ],
        'TagType'  => [ 'type' => 'str', 'sqltype' => 'ENUM(\'bad\',\'good\')', 'nullable' => false, 'default' => '\'bad\'' ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Tag'      => [ 'columns' => [ 'Tag' ], 'type' => 'unique' ],
        'TagType'  => [ 'columns' => [ 'TagType' ] ],
    ];
}
