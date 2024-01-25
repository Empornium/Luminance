<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Article Entity representing rows from the `articles` DB table.
 */
class Article extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'articles';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'           => [ 'type' => 'int', 'sqltype' => 'INT(11)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'Category'     => [ 'type' => 'int', 'sqltype' => 'INT(11)', 'nullable' => false ],
        'SubCat'       => [ 'type' => 'int', 'sqltype' => 'INT(4)', 'nullable' => false, 'default' => '1' ],
        'TopicID'      => [ 'type' => 'str', 'sqltype' => 'VARCHAR(20)', 'nullable' => false ],
        'MinClass'     => [ 'type' => 'int', 'sqltype' => 'SMALLINT(4)', 'nullable' => false, 'default' => '0' ],
        'Title'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(50)', 'nullable' => false ],
        'Description'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(100)', 'nullable' => false ],
        'Body'         => [ 'type' => 'str', 'sqltype' => 'MEDIUMTEXT', 'nullable' => false ],
        'Time'         => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => null, 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'TopicID'      => [ 'columns' => [ 'TopicID' ], 'type' => 'unique' ],
        'Category'     => [ 'columns' => [ 'Category' ] ],
        'SubCat'       => [ 'columns' => [ 'SubCat' ] ],
        'MinClass'     => [ 'columns' => [ 'MinClass' ] ],
        'Search'       => [ 'columns' => [ 'Title', 'Description', 'Body' ], 'type' => 'fulltext' ],
    ];
}
