<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class ClientScreen extends Entity {

    public static $table = 'client_screens';

    public static $properties = [
        'ID'         => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Width'      => [ 'type' => 'int', 'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => true ],
        'Height'     => [ 'type' => 'int', 'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => true ],
        'ColorDepth' => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => true ],
    ];

    public static $indexes = [
        'Width'      => [ 'columns' => [ 'Width' ] ],
        'Height'     => [ 'columns' => [ 'Height' ] ],
        'ColorDepth' => [ 'columns' => [ 'ColorDepth' ] ],
    ];
}
