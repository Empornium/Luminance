<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Email extends Entity {

    static $table = 'emails';

    public $legacy;

    static $properties = [
        'ID'      => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'UserID'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Address' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Reduced' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Changed' => [ 'type' => 'timestamp', 'nullable' => false ],
        'Flags'   => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false ],
    ];

    static $indexes = [
        'UserID'  => [ 'columns' => [ 'UserID'  ] ],
        'Address' => [ 'columns' => [ 'Address' ] ],
        'Reduced' => [ 'columns' => [ 'Reduced' ] ],
        'Flags'   => [ 'columns' => [ 'Flags'   ] ],
    ];

    const VALIDATED = 1;
    const CANCELLED = 2;

    public function readyToResend() {
        $treshold = new \DateTime('-1 hour');
        return ($this->Changed < $treshold);
    }

}
