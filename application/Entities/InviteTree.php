<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * InviteTree Entity representing rows from the `invite_tree` DB table.
 */
class InviteTree extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'invite_tree';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'UserID'        => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '0', 'primary' => true ],
        'InviterID'     => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true,  'default' => null ],
        'TreePosition'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '1' ],
        'TreeID'        => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '1' ],
        'TreeLevel'     => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '0' ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'InviterID'     => [ 'columns' => [ 'InviterID' ] ],
        'TreePosition'  => [ 'columns' => [ 'TreePosition' ] ],
        'TreeID'        => [ 'columns' => [ 'TreeID' ] ],
        'TreeLevel'     => [ 'columns' => [ 'TreeLevel' ] ],
    ];
}
