<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * TorrentGroup Entity representing rows from the `torrents_group` DB table.
 */
class TorrentGroup extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'torrents_group';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'repos' => 'Repos',
    ];

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'            => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'NewCategoryID' => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED', 'nullable' => true,  'default' => null ],
        'Name'          => [ 'type' => 'str',       'sqltype' => 'VARCHAR(300)', 'nullable' => true,  'default' => null ],
        'TagList'       => [ 'type' => 'str',       'sqltype' => 'MEDIUMTEXT',   'nullable' => false, 'default' => ''   ],
        'Time'          => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',     'nullable' => true,  'default' => null ],
        'UserID'        => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED', 'nullable' => true,  'default' => null ],
        'EditedUserID'  => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED', 'nullable' => true,  'default' => null ],
        'EditedTime'    => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',     'nullable' => true,  'default' => null ],
        'Body'          => [ 'type' => 'str',       'sqltype' => 'MEDIUMTEXT',   'nullable' => true,  'default' => null ],
        'Image'         => [ 'type' => 'str',       'sqltype' => 'VARCHAR(255)', 'nullable' => true,  'default' => null ],
        'Thanks'        => [ 'type' => 'str',       'sqltype' => 'TEXT',         'nullable' => false, 'default' => ''   ],
        'SearchText'    => [ 'type' => 'str',       'sqltype' => 'MEDIUMTEXT',   'nullable' => false, 'default' => ''   ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'NewCategoryID' => [ 'columns' => [ 'NewCategoryID' ] ],
        'Name'          => [ 'columns' => [ [ 'name' => 'Name', 'length' => 255] ] ],
        'Time'          => [ 'columns' => [ 'Time' ] ],
    ];

    /**
     * canThank Returns whether user can use the thanks button on torrent pages.
     * @param  User|int      $user  User object or UserID integer.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canThank($user) {
        $user = $this->repos->users->load($user);

        # You can't thank yourself!
        if ($this->isUploader($user)) {
            return false;
        }

        # No thanks yet, so this user can't have thanked already
        if (empty($this->Thanks)) {
            return true;
        }

        return (strpos($this->Thanks, $user->Username) === false);
    }

    /**
     * isUploader Returns whether user is the uploader for this group.
     * @param  User|int      $user  User object or UserID integer.
     * @return bool                 True if true, false otherwise.
     *
     * @access public
     */
    public function isUploader($user) {
        $user = $this->repos->users->load($user);
        return $this->UserID === $user->ID;
    }

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
            case 'thanks':
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
            case 'thanks':
                if (!array_key_exists($name, $this->localValues)) {
                    $thanks = [];
                    $thanks['names'] = $this->Thanks;
                    $thanks['count'] = count(explode(', ', $thanks['names']));
                    $this->safeSet($name, $thanks);
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
