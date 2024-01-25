<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Invite Entity representing rows from the `invites_log` DB table.
 */
class InviteLog extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'invites_log';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => ['type' => 'int',  'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Event'    => ['type' => 'str', 'sqltype'  => 'VARCHAR(255)', 'nullable' => false],
        'Reason'   => ['type' => 'str', 'sqltype'  => 'VARCHAR(750)', 'nullable' => false],
        'Address'  => ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true],
        'UserID'   => ['type' => 'int', 'sqltype'  => 'INT UNSIGNED', 'nullable' => true],
        'AuthorID' => ['type' => 'int', 'sqltype'  => 'INT UNSIGNED', 'nullable' => false],
        'Quantity' => ['type' => 'int', 'sqltype'  => 'INT UNSIGNED', 'nullable' => false],
        'Action'   => ['type' => 'str', 'sqltype'  => "ENUM('grant','remove','sent','cancel','resent')", 'nullable' => false, 'default' => '\'sent\'' ],
        'Entity'   => ['type' => 'str', 'sqltype'  => "ENUM('user','mass')", 'nullable' => false, 'default' => "'user'" ],
        'Date'     => ['type' => 'timestamp', 'nullable' => false]
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'AuthorID' => ['columns' => ['AuthorID' ] ],
    ];
}
