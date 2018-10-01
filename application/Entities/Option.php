<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Option extends Entity {

    public static $table = 'options';

    public static $properties = [
        'Name'    => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'primary' => true, 'nullable' => false],
        'Value'   => [ 'type' => 'str', 'sqltype' => 'TEXT', 'nullable' => false],
    ];

    public static $indexes = [
    ];
}
