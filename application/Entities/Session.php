<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Session extends Entity {

    public static $table = 'sessions';

    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'IPID'     => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'ClientID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Active'   => [ 'type' => 'bool', 'nullable' => false ],
        'Flags'    => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false ],
        'Created'  => [ 'type' => 'timestamp', 'nullable' => true ],
        'Updated'  => [ 'type' => 'timestamp', 'nullable' => true ],
    ];

    public static $indexes = [
        'UserID'   => [ 'columns' => [ 'UserID' ] ],
        'ClientID' => [ 'columns' => [ 'ClientID' ] ],
        'Active'   => [ 'columns' => [ 'Active' ] ],
        'Flags'    => [ 'columns' => [ 'Flags' ] ],
        'Created'  => [ 'columns' => [ 'Created' ] ],
        'Updated'  => [ 'columns' => [ 'Updated' ] ],
    ];

    const KEEP_LOGGED_IN = 1 << 0;
    const IP_LOCKED      = 1 << 1;
    const TWO_FACTOR     = 1 << 2;
    const LEGACY         = 1 << 7;
}
