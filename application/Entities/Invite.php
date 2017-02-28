<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Invite extends Entity {

    static $table = 'invites';

    public $legacy;

    static $properties = [
        'InviterID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'InviteKey' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'primary'  => true  ],
        'Email'     => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Token'     => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true  ],
        'Expires'   => [ 'type' => 'str', 'sqltype' => 'DATETIME',     'nullable' => false ],
    ];

    static $indexes = [
        'InviterID' => [ 'columns' => [ 'InviterID' ] ],
        'Token'     => [ 'columns' => [ 'Token'     ] ],
        'Expires'   => [ 'columns' => [ 'Expires'   ] ],
    ];

    public function hasExpired() {
        // Compare to apples to apples
        $treshold = new \DateTime();
        $expires  = new \DateTime($this->Expires);
        return ($expires < $treshold);
    }

}
