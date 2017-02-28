<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class ClientUserAgent extends Entity {

    static $table = 'client_user_agents';

    static $properties = [
        'ID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'String' => ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Platform' => ['type' => 'str', 'sqltype' => 'VARCHAR(32)', 'nullable' => true ],
        'Browser' => ['type' => 'str', 'sqltype' => 'VARCHAR(32)', 'nullable' => true ],
        'Version' => ['type' => 'str', 'sqltype' => 'VARCHAR(32)', 'nullable' => true ],
    ];

    static $indexes = [
        'String' => [ 'columns' => [ 'String' ] ],
        'Platform' => [ 'columns' => [ 'Platform' ] ],
        'Browser' => [ 'columns' => [ 'Browser' ] ],
        'Version' => [ 'columns' => [ 'Version' ] ],
    ];

}
