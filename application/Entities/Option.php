<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Option extends Entity {

    static $table = 'options';

    static $properties = [
        'Name'    => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'primary' => true, 'nullable' => false],
        'Value'   => [ 'type' => 'str', 'sqltype' => 'TEXT', 'nullable' => false],
    ];

    static $indexes = [
    ];
}
