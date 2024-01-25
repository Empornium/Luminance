<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ClientScreen Entity representing rows from the `client_screens` DB table.
 */
class ClientScreen extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'client_screens';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'         => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Width'      => [ 'type' => 'int', 'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => true ],
        'Height'     => [ 'type' => 'int', 'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => true ],
        'ColorDepth' => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Width'      => [ 'columns' => [ 'Width' ] ],
        'Height'     => [ 'columns' => [ 'Height' ] ],
        'ColorDepth' => [ 'columns' => [ 'ColorDepth' ] ],
    ];
}
