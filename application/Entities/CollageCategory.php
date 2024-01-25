<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * CollageCategory Entity representing rows from the `categories` DB table.
 */
class CollageCategory extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'collage_categories';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'              => [ 'type' => 'int', 'sqltype' => 'INT(10)',          'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'Name'            => [ 'type' => 'str', 'sqltype' => 'VARCHAR(64)',      'nullable' => false                   ],
        'Description'     => [ 'type' => 'str', 'sqltype' => 'VARCHAR(128)',     'nullable' => false                   ],
        'MinClassView'    => [ 'type' => 'int', 'sqltype' => 'INT(4)',           'nullable' => false, 'default' => '0' ],
        'MinClassCreate'  => [ 'type' => 'int', 'sqltype' => 'INT(4)',           'nullable' => false, 'default' => '0' ],
        'Image'           => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)',     'nullable' => false                   ],
        'Sort'            => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED',     'nullable' => false, 'default' => '0' ],
        'Flags'           => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false                   ],
    ];

    const LOCKED    = 1 << 0;
    const PERSONAL  = 1 << 1;

    /**
     * isLocked Returns whether or not the LOCKED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isLocked(): bool {
        return $this->getFlag(self::LOCKED);
    }

    /**
     * isPersonal Returns whether or not the PERSONAL flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isPersonal(): bool {
        return $this->getFlag(self::PERSONAL);
    }
}
