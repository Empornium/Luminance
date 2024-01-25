<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ClientUserAgent Entity representing rows from the `client_user_agents` DB table.
 */
class ClientUserAgent extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'client_user_agents';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'String'   => ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Platform' => ['type' => 'str', 'sqltype' => 'VARCHAR(32)', 'nullable' => true ],
        'Browser'  => ['type' => 'str', 'sqltype' => 'VARCHAR(32)', 'nullable' => true ],
        'Version'  => ['type' => 'str', 'sqltype' => 'VARCHAR(32)', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'String'   => [ 'columns' => [ 'String' ] ],
        'Platform' => [ 'columns' => [ 'Platform' ] ],
        'Browser'  => [ 'columns' => [ 'Browser' ] ],
        'Version'  => [ 'columns' => [ 'Version' ] ],
    ];
}
