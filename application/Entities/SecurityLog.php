<?php

namespace Luminance\Entities;

use Luminance\Core\Entity;

class SecurityLog extends Entity
{
    public static $table = 'security_logs';

    public static $properties = [
        'ID'       => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary'  => true, 'auto_increment' => true],
        'Event'    => ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false],
        'UserID'   => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'IPID'     => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'AuthorID' => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'Date'     => ['type' => 'timestamp', 'nullable' => false]
    ];

    public static $indexes = [
        'UserID' => ['columns' => ['UserID']],
        'IPID'   => ['columns' => ['IPID']]
    ];
}
