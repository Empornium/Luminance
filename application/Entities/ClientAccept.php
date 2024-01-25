<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ClientAccept Entity representing rows from the `client_accepts` DB table.
 */
class ClientAccept extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'client_accepts';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'             => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Accept'         => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
        'AcceptCharset'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
        'AcceptEncoding' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
        'AcceptLanguage' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Accept'         => [ 'columns' => [ 'Accept' ] ],
        'AcceptCharset'  => [ 'columns' => [ 'AcceptCharset' ] ],
        'AcceptEncoding' => [ 'columns' => [ 'AcceptEncoding' ] ],
        'AcceptLanguage' => [ 'columns' => [ 'AcceptLanguage' ] ],
    ];
}
