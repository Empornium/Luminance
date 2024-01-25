<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Option UserFriend representing rows from the `friends` DB table.
 */
class UserFriend extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'friends';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'primary' => true ],
        'FriendID' => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'primary' => true ],
        'Comment'  => [ 'type' => 'str', 'sqltype' => 'TEXT', 'nullable' => false                       ],
        'Type'     => [ 'type' => 'str', 'sqltype' => "ENUM('friends','blocked')", 'nullable' => false  ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Type' => [ 'columns' => [ 'Type' ] ],
    ];
}
