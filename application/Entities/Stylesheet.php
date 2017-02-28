<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Stylesheet extends Entity {

    static $table = 'stylesheets';

    static $properties = [
        'ID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Name' => ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Description' => ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Default' => ['type' => 'str', 'sqltype' => "ENUM('0', '1')", 'nullable' => false ],

    ];

    static $indexes = [
    ];

}
