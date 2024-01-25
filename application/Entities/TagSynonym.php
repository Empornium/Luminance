<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * TagSynonym Entity representing rows from the `tags_synonyms` DB table.
 */
class TagSynonym extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'tags_synonyms';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'Synonym'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(100)', 'nullable' => false ],
        'TagID'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Synonym'  => [ 'columns' => [ 'Synonym' ], 'type' => 'unique' ],
        'TagID'    => [ 'columns' => [ 'TagID' ] ],
        'UserID'   => [ 'columns' => [ 'UserID' ] ],
    ];
}
