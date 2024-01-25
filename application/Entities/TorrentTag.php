<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * TorrentTag Entity representing rows from the `torrents_tags` DB table.
 */
class TorrentTag extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'torrents_tags';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'TagID'          => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '0', 'primary' => true ],
        'GroupID'        => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '0', 'primary' => true ],
        'PositiveVotes'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '1' ],
        'NegativeVotes'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '1' ],
        'UserID'         => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'default' => 'NULL', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'TagID'          => [ 'columns' => [ 'TagID' ] ],
        'GroupID'        => [ 'columns' => [ 'GroupID' ] ],
        'PositiveVotes'  => [ 'columns' => [ 'PositiveVotes' ] ],
        'NegativeVotes'  => [ 'columns' => [ 'NegativeVotes' ] ],
        'UserID'         => [ 'columns' => [ 'UserID' ] ],
    ];

    /**
     * __isset returns whether an object property exists or not,
     * this is necessary for lazy loading to function correctly from TWIG
     * @param  string  $name Name of property being checked
     * @return bool          True if property exists, false otherwise
     *
     * @access public
     */
    public function __isset($name) {
        switch ($name) {
            case 'upDown':
                return true;

            default:
                return parent::__isset($name);
        }
    }

    /**
     * __get returns the property requested, loading it from the DB if necessary,
     * this permits us to perform lazy loading and thus dynamically minimize both
     * memory usage and cache/DB usage.
     * @param  string $name Name of property being accessed
     * @return mixed        Property data (could be anything)
     *
     * @access public
     */
    public function __get($name) {

        switch ($name) {
            case 'upDown':
                # Don't cache, always recalculate!
                $this->safeSet($name, ($this->PositiveVotes - $this->NegativeVotes));
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
