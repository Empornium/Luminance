<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class RequestFlood extends Entity {

    public static $table = 'request_flood';

    public static $properties = [
        'ID'          => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'UserID'      => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'IPID'        => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'Type'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(16)',  'nullable' => false],
        'LastRequest' => [ 'type' => 'timestamp', 'nullable' => true ],
        'Requests'    => [ 'type' => 'int', 'nullable' => true ],
    ];

    public static $indexes = [
        'UserID' => [ 'columns' => [ 'IPID' ] ],
        'IPID'   => [ 'columns' => [ 'IPID' ] ],
        'Type'   => [ 'columns' => [ 'Type' ] ],
    ];
}
