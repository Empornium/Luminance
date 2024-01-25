<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Invite Entity representing rows from the `invites` DB table.
 */
class Invite extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'invites';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'        => [ 'type' => 'int',  'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'InviterID' => [ 'type' => 'int',  'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Email'     => [ 'type' => 'str',  'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Comment'   => [ 'type' => 'str',  'sqltype' => 'VARCHAR(750)', 'nullable' => false, 'default' => '' ],
        'Anon'      => [ 'type' => 'bool', 'sqltype' => 'TINYINT(1)',   'nullable' => false ],
        'Expires'   => [ 'type' => 'timestamp', 'nullable' => true ],
        'Changed'   => [ 'type' => 'timestamp', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'InviterID' => [ 'columns' => [ 'InviterID' ] ],
        'Expires'   => [ 'columns' => [ 'Expires'   ] ],
    ];

    /**
     * readyToResend returns bool showing whether or not the user can request a resend.
     * @return bool True if user can request a resend, false otherwise.
     *
     * @access public
     */
    public function readyToResend() {
        $threshold = new \DateTime('-1 hour');
        return ($this->Changed < $threshold);
    }
}
