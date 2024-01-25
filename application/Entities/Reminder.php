<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Reminder Entity representing rows from the `users_reminders` DB table.
 */
class Reminder extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'users_reminders';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'           => [ 'type' => 'int',       'sqltype' => 'INT(11)',          'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'UserID'       => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => false ],
        'StaffLevel'   => [ 'type' => 'int',       'sqltype' => 'INT(11)',          'nullable' => true, 'default' => null],
        'Subject'      => [ 'type' => 'str',       'sqltype' => 'VARCHAR(100)',     'nullable' => false ],
        'Note'         => [ 'type' => 'str',       'sqltype' => 'TEXT',             'nullable' => false ],
        'Created'      => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',         'nullable' => true, 'default' => null],
        'RemindDate'   => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',         'nullable' => false ],
        'Flags'        => [ 'type' => 'int',       'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false, 'default' => '0'],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID'  => [ 'columns' => [ 'UserID' ] ],
    ];


    const CANCELLED  = 1 << 0;
    const TRASHED    = 1 << 1;
    const COMPLETED  = 1 << 2;
    const SHARED     = 1 << 3;

    /**
     * isCancelled Returns whether or not the CANCELLED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isCancelled() {
        return $this->getFlag(self::CANCELLED);
    }

    /**
     * isTrashed Returns whether or not the TRASHED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isTrashed() {
        return $this->getFlag(self::TRASHED);
    }

    /**
     * isCompleted Returns whether or not the COMPLETED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isCompleted() {
        return $this->getFlag(self::COMPLETED);
    }
}
