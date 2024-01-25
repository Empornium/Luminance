<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * TorrentTagVote Entity representing rows from the `torrents_tags_votes` DB table.
 */
class TorrentTagVote extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'torrents_tags_votes';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'GroupID'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'primary' => true ],
        'TagID'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'primary' => true ],
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'primary' => true ],
        'Way'      => [ 'type' => 'str', 'sqltype' => 'ENUM(\'up\', \'down\')', 'nullable' => false, 'default' => '\'up\'', 'primary' => true ],
        'Power'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => 1 ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID'   => [ 'columns' => [ 'UserID' ] ],
    ];
}
