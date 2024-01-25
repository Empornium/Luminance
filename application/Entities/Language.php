<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Language Entity representing rows from the `languages` DB table.
 */
class Language extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'languages';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'        => [ 'type' => 'int', 'sqltype' => 'SMALLINT(3)',   'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'language'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(64)',   'nullable' => false                     ],
        'code'      => [ 'type' => 'str', 'sqltype' => 'CHAR(2)',       'nullable' => false                     ],
        'flag_cc'   => [ 'type' => 'str', 'sqltype' => 'CHAR(2)',       'nullable' => true, 'default' => 'NULL' ],
        'active'    => [ 'type' => 'str', 'sqltype' => "ENUM('0','1')", 'nullable' => false, 'default' => '0'   ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'code'      => [ 'columns' => [ 'code' ], 'type' => 'unique' ],
    ];
}
