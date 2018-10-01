<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Invite extends Entity {

    public static $table = 'invites';

    public $legacy;

    public static $properties = [
        'ID'        => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'InviterID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'InviteKey' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true  ],
        'Email'     => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Expires'   => [ 'type' => 'timestamp', 'nullable' => true ],
    ];

    public static $indexes = [
        'InviterID' => [ 'columns' => [ 'InviterID' ] ],
        'Expires'   => [ 'columns' => [ 'Expires'   ] ],
    ];
}
