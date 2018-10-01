<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class ClientAccept extends Entity {

    public static $table = 'client_accepts';

    public static $properties = [
        'ID'             => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Accept'         => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
        'AcceptCharset'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
        'AcceptEncoding' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
        'AcceptLanguage' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
    ];

    public static $indexes = [
        'Accept'         => [ 'columns' => [ 'Accept' ] ],
        'AcceptCharset'  => [ 'columns' => [ 'AcceptCharset' ] ],
        'AcceptEncoding' => [ 'columns' => [ 'AcceptEncoding' ] ],
        'AcceptLanguage' => [ 'columns' => [ 'AcceptLanguage' ] ],
    ];
}
