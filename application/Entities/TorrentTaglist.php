<?php

namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * TorrentTaglist Entity representing rows from the `torrents_taglists` DB table.
 */
class TorrentTaglist extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'torrents_taglists';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary'  => true, 'auto_increment' => true],
        'taglist'  => ['type' => 'str', 'sqltype' => 'MEDIUMTEXT', 'nullable' => false],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'taglist'   => [
            'columns' => ['taglist'],
            'type'    => 'fulltext',
        ],
    ];

    public static $attributes = [
        'engine'    => 'mroonga',
    ];
}
