<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * UserPMConversations Entity representing rows from the `pm_conversations` DB table.
 */
class UserInboxSubject extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'pm_conversations';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT(12)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'Subject'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'default' => 'NULL', 'nullable' => true ],
    ];
}
