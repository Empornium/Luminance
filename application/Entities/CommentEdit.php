<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * CommentsEdit Entity representing rows from the `comments_edits` DB table.
 */
class CommentEdit extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'comments_edits';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Page'     => [ 'type' => 'str', 'sqltype' => "ENUM('forums','collages','requests','torrents','staffpm','articles','descriptions')", 'default' => null, 'nullable' => true ],
        'PostID'   => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'default' => null, 'nullable' => true ],
        'EditUser' => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'default' => null, 'nullable' => true ],
        'EditTime' => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => null, 'nullable' => true ],
        'Body'     => [ 'type' => 'str', 'sqltype' => 'MEDIUMTEXT', 'default' => null, 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Page'     => [ 'columns' => [ 'Page', 'PostID' ] ],
        'EditUser' => [ 'columns' => [ 'EditUser' ] ],
    ];
}
