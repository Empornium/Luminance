<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

use Luminance\Entities\Permission;

/**
 * Forum Entity representing rows from the `forums` DB table.
 */
class Forum extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'forums';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'db'    => 'DB',
        'auth'  => 'Auth',
        'cache' => 'Cache',
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
      'ID'               => [ 'type' => 'int', 'sqltype' => 'INT(6)',        'nullable' => false, 'auto_increment' => true, 'primary' => true ],
      'CategoryID'       => [ 'type' => 'int', 'sqltype' => 'TINYINT(2)',    'nullable' => false, 'default' => '0'   ],
      'Sort'             => [ 'type' => 'int', 'sqltype' => 'INT(6)',        'nullable' => false, 'default' => '0'   ],
      'Name'             => [ 'type' => 'str', 'sqltype' => 'VARCHAR(40)',   'nullable' => false, 'default' => ''    ],
      'Description'      => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)',  'nullable' => true,  'default' => ''    ],
      'MinClassRead'     => [ 'type' => 'int', 'sqltype' => 'INT(4)',        'nullable' => false, 'default' => '0'   ],
      'MinClassWrite'    => [ 'type' => 'int', 'sqltype' => 'INT(4)',        'nullable' => false, 'default' => '0'   ],
      'MinClassCreate'   => [ 'type' => 'int', 'sqltype' => 'INT(4)',        'nullable' => false, 'default' => '0'   ],
      'AutoLock'         => [ 'type' => 'str', 'sqltype' => "ENUM('0','1')", 'nullable' => true,  'default' => "'1'" ],
      'Flags'            => [ 'type' => 'int', 'sqltype' => 'TINYINT(3)',    'nullable' => false, 'default' => '0'   ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Sort'         => [ 'columns' => [ 'Sort'         ] ],
        'MinClassRead' => [ 'columns' => [ 'MinClassRead' ] ],
    ];

    const AUTOLOCK = 1 << 0;

    /**
     * overridePermitted Returns whether default user class permissions have been overridden for this forum.
     * @param  User|int      $user  User object or UserID integer.
     * @return bool|null            True if user can access, False if user is barred, null if not overridden.
     *
     * @access protected
     */
    protected function overridePermitted($user) {
        $user = $this->repos->users->load($user);

        $restrictedForums = (array)explode(',', $user->legacy['RestrictedForums']);
        $permittedForums  = (array)explode(',', $user->legacy['PermittedForums']);
        $groupForums      = ($user->group instanceof Permission) ? (array)explode(',', $user->group->Forums) : [];
        if (in_array($this->ID, $groupForums)) {
            return true;
        }
        if (in_array($this->ID, $permittedForums)) {
            return true;
        }
        if (in_array($this->ID, $restrictedForums)) {
            return false;
        }

        return null;
    }

    /**
     * canRead Returns whether user has permission to read threads in this forum.
     * @param  User|int      $user  User object or UserID integer.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canRead($user) {
        $user = $this->repos->users->load($user);
        $permitted = $this->overridePermitted($user);
        if (!is_null($permitted)) {
            return $permitted;
        }
        return $this->auth->isLevel($this->MinClassRead);
    }

    /**
     * canWrite Returns whether user has permission to post in threads in this forum.
     * @param  User|int      $user  User object or UserID integer.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canWrite($user) {
        $user = $this->repos->users->load($user);
        $permitted = $this->overridePermitted($user);
        if (!is_null($permitted)) {
            return $permitted;
        }
        return $this->auth->isLevel($this->MinClassWrite);
    }

    /**
     * canCreate Returns whether user has permission to create threads in this forum.
     * @param  User|int      $user  User object or UserID integer.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canCreate($user) {
        $user = $this->repos->users->load($user);
        $permitted = $this->overridePermitted($user);
        if (!is_null($permitted)) {
            return $permitted;
        }
        return $this->auth->isLevel($this->MinClassCreate);
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
            case 'category':
            case 'lastThread':
            case 'numThreads':
            case 'numPosts':
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
            case 'category':
                if (!array_key_exists($name, $this->localValues)) {
                    $category = $this->repos->forumCategories->load($this->CategoryID);
                    $this->safeSet($name, $category);
                }
                break;

            case 'lastThread':
                if (!array_key_exists($name, $this->localValues)) {
                    $lastThreadID = $this->cache->getValue("forum_last_thread_{$this->ID}");
                    if ($lastThreadID === false) {
                        $lastThreadID = $this->db->rawQuery(
                            "SELECT fp.ThreadID
                               FROM forums_posts AS fp
                               JOIN forums_threads AS ft ON fp.ThreadID=ft.ID
                              WHERE ft.ForumID = ?
                              ORDER BY fp.AddedTime DESC
                              LIMIT 1",
                            [$this->ID]
                        )->fetchColumn();
                        $this->cache->cacheValue("forum_last_thread_{$this->ID}", $lastThreadID, 0);
                    }
                    $this->safeSet($name, $this->repos->forumThreads->load($lastThreadID));
                }
                break;

            case 'numThreads':
                if (!array_key_exists($name, $this->localValues)) {
                    $count = $this->cache->getValue("forum_threads_count_{$this->ID}");
                    if ($count === false) {
                        $count = $this->db->rawQuery(
                            "SELECT COUNT(*) FROM `forums_threads` WHERE ForumID = ?",
                            [$this->ID]
                        )->fetchColumn();
                        $this->cache->cacheValue("forum_threads_count_{$this->ID}", $count, 0);
                    }
                    $this->safeSet($name, $count);
                }
                break;

            case 'numPosts':
                if (!array_key_exists($name, $this->localValues)) {
                    $count = $this->cache->getValue("forum_posts_count_{$this->ID}");
                    if ($count === false) {
                        $count = $this->db->rawQuery(
                            "SELECT COUNT(*) FROM `forums_posts` AS `fp` JOIN `forums_threads` AS `ft` ON `ft`.`ID` = `fp`.`ThreadID` WHERE `ft`.`ForumID` = ?",
                            [$this->ID]
                        )->fetchColumn();
                        $this->cache->cacheValue("forum_posts_count_{$this->ID}", $count, 0);
                    }
                    $this->safeSet($name, $count);
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
