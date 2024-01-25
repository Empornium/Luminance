<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ForumPollVote Entity representing rows from the `forums_polls_votes` DB table.
 */
class ForumPollVote extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'forums_polls_votes';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'ThreadID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Vote'     => [ 'type' => 'int', 'sqltype' => 'TINYINT(3)',   'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
      'UserID'            => [ 'columns' => [ 'UserID'  ] ],
      'ThreadID'          => [ 'columns' => [ 'ThreadID'  ] ],
    ];
}
