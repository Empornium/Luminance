<?php

namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * SecurityLog Entity representing rows from the `security_logs` DB table.
 */
class SecurityLog extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'security_logs';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary'  => true, 'auto_increment' => true],
        'Event'    => ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false],
        'UserID'   => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'IPID'     => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'AuthorID' => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
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
        'UserID' => ['columns' => ['UserID']],
        'IPID'   => ['columns' => ['IPID']]
    ];
}
