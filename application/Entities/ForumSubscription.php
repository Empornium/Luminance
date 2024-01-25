<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ForumSubscription Entity representing rows from the `forums_subscriptions` DB table.
 */
class ForumSubscription extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'forums_subscriptions';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'primary' => true ],
        'ThreadID' => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'primary' => true ],
    ];
}
