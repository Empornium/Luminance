<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Invite extends Entity {

    static $table = 'invites';

    public $legacy;

    static $properties = [
        'ID'        => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'InviterID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'InviteKey' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true  ],
        'Email'     => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Expires'   => [ 'type' => 'str', 'sqltype' => 'DATETIME',     'nullable' => false ],
    ];

    static $indexes = [
        'InviterID' => [ 'columns' => [ 'InviterID' ] ],
        'Expires'   => [ 'columns' => [ 'Expires'   ] ],
    ];

    public function hasExpired() {
        // Compare to apples to apples
        $treshold = new \DateTime();
        $expires  = new \DateTime($this->Expires);
        return ($expires < $treshold);
    }

}
