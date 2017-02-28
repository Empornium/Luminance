<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class ClientScreen extends Entity {

    static $table = 'client_screens';

    static $properties = [
        'ID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Width' => ['type' => 'int', 'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => true ],
        'Height' => ['type' => 'int', 'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => true ],
        'ColorDepth' => ['type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => true ],
    ];

    static $indexes = [
        'Width' => [ 'columns' => [ 'Width' ] ],
        'Height' => [ 'columns' => [ 'Height' ] ],
        'ColorDepth' => [ 'columns' => [ 'ColorDepth' ] ],
    ];

}
