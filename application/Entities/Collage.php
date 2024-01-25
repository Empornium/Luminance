<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;
use Luminance\Errors\UserError;

use Luminance\Entities\Permission;

/**
 * Collage Entity representing rows from the `collages` DB table.
 */
class Collage extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'collages';

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

    #TODO Create migration script to transfer StartDate
    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
      'ID'               => [ 'type' => 'int',       'sqltype' => 'INT(6)',       'nullable' => false, 'auto_increment' => true, 'primary' => true ],
      'Name'             => [ 'type' => 'str',       'sqltype' => 'VARCHAR(100)', 'nullable' => false, 'default' => ''   ],
      'Description'      => [ 'type' => 'str',       'sqltype' => 'MEDIUMTEXT',   'nullable' => false, 'default' => ''   ],
      'UserID'           => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED', 'nullable' => true,  'default' => null ],
      'Permissions'      => [ 'type' => 'int',       'sqltype' => 'INT(4)',       'nullable' => false, 'default' => '0'  ],
      'CategoryID'       => [ 'type' => 'int',       'sqltype' => 'TINYINT(2)',   'nullable' => false,                   ],
      'TagList'          => [ 'type' => 'str',       'sqltype' => 'MEDIUMTEXT',   'nullable' => false, 'default' => ''   ],
      'MaxGroups'        => [ 'type' => 'int',       'sqltype' => 'INT(10)',      'nullable' => false, 'default' => '0'  ],
      'MaxGroupsPerUser' => [ 'type' => 'int',       'sqltype' => 'INT(10)',      'nullable' => false, 'default' => '0'  ],
      'StartDate'        => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',     'nullable' => true,  'default' => null ],
      'Flags'            => [ 'type' => 'int',       'sqltype' => 'TINYINT(3)',   'nullable' => false, 'default' => '0'  ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Name'       => [ 'columns' => [ 'Name'  ], 'type' => 'unique' ],
        'UserID'     => [ 'columns' => [ 'UserID' ] ],
        'CategoryID' => [ 'columns' => [ 'CategoryID' ] ],
    ];

    const TRASHED   = 1 << 0;
    const LOCKED    = 1 << 1;
    const FEATURED  = 1 << 2;

    /**
     * isTrashed Returns whether or not the TRASHED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isTrashed() {
        return $this->getFlag(self::TRASHED);
    }

    /**
     * isFeatured Returns whether or not the LOCKED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isLocked(): bool {
        return $this->getFlag(self::LOCKED);
    }

    /**
     * isFeatured Returns whether or not the FEATURED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isFeatured() {
        return $this->getFlag(self::FEATURED);
    }

    /**
     * isPersonal Returns whether or not the PERSONAL flag is set on category object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isPersonal(): bool {
        return $this->category->isPersonal();
    }

    /**
     * isFull Returns whether or not the collage has reached it's MaxGroups/MaxGroupsPerUser
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isFull() {
        $user = $this->master->request->user;

        if (($this->MaxGroups > 0 && $this->count >= $this->MaxGroups)  ||
            ($this->MaxGroupsPerUser > 0 && $this->users[$user->ID] >= $this->MaxGroupsPerUser)
        ) {
            return true;
        }

        return false;
    }

    /**
     * canRename Returns whether user has permission to rename this collage.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canRename(): bool {
        $user = $this->master->request->user;

        if ($this->auth->isAllowed('collage_moderate')) {
            return true; # moderators can edit anything
        }

        if ($this->isLocked()) {
            return false; # can't edit a locked collage
        }

        if ($this->auth->isAllowed('collage_renamepersonal')) {
            if ($this->UserID === $user->ID) {
                if ($this->isPersonal()) {
                    return true; # creator can edit if it's not locked
                }
            }
        }

        return false;
    }

    /**
     * canEdit Returns whether user has permission to edit this collage.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canEdit(): bool {
        $user = $this->master->request->user;
        if ($this->auth->isAllowed('collage_moderate')) {
            return true; # moderators can edit anything
        }

        if ($this->isLocked()) {
            return false; # can't edit a locked collage
        }
        if ($this->UserID === $user->ID) {
              return true; # creator can edit if it's not locked
        }

        return false;
    }

    /**
     * canManage Returns whether or not the collage can be added to or not
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function canManage(): bool {
        $user = $this->master->request->user;
        if ($this->canEdit()) {
            return true; # if you can edit you can manage
        }

        if ($this->Permissions > 0) {
            if ($user->class->Level >= $this->Permissions) {
                return true; # User is above class limit
            }
        }

        if ($user->group instanceof Permission) {
            if (array_key_exists($user->group->ID, $this->groups)) {
                return true; # User is a member of an extra permitted group
            }
        }

        return false;
    }

    /**
     * canAdd Returns whether or not the collage can be added to or not
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function canAdd(): bool {
        $user = $this->master->request->user;
        if ($this->auth->isAllowed('collage_moderate')) {
            return true; # moderators can edit anything
        }

        if ($this->isLocked()) {
            return false;
        }

        if ($this->isFull()) {
            return false;
        }

        if ($this->UserID === $user->ID) {
              return true; # creator can add if it's not locked or full
        }

        if ($this->Permissions > 0) {
            if ($user->class->Level >= $this->Permissions) {
                return true; # User is above class limit
            }
        }

        if ($user->group instanceof Permission) {
            if (array_key_exists($user->group->ID, $this->groups)) {
                return true; # User is a member of an extra permitted group
            }
        }

        return false;
    }

    /**
     * addTorrent adds a torrent to this collage
     * @param  int      $groupID  torrent group ID to be added
     *
     * @access public
     */
    public function addTorrent($groupID) {
        $user = $this->master->request->user;

        $sort = $this->db->rawQuery(
            "SELECT MAX(Sort)
               FROM collages_torrents
              WHERE CollageID = ?",
            [$this->ID]
        )->fetchColumn();

        if (!is_integer_string($sort)) {
            $sort = 0;
        }

        $sort += 10;

        $groupIDs = $this->db->rawQuery(
            "SELECT GroupID
               FROM collages_torrents
              WHERE CollageID = ?",
            [$this->ID]
        )->fetchAll(\PDO::FETCH_COLUMN);

        if (!in_array($groupID, $groupIDs)) {
            $timestamp = new \DateTime;
            $torrent = new CollageTorrent([
                'CollageID'   => $this->ID,
                'GroupID'     => $groupID,
                'UserID'      => $user->ID,
                'Sort'        => $sort,
                'AddedOn'     => $timestamp,
            ]);

            $this->repos->collageTorrents->save($torrent);
            $this->repos->collages->save($this);

            $subscriptions = $this->subscriptions();
            foreach ($subscriptions as $subscription) {
                $this->cache->deleteValue('collage_subs_user_new_'.$subscription->UserID);
            }
        }
    }

    /**
     * subscriptions Returns all CollageSubscription objects for this collage
     * @return Array|null    CollageSubscription objects
     *
     * @access public
     */
    public function subscriptions() {
        # Store in a local cache for a slight performance boost
        if (!array_key_exists('subscriptions', $this->localValues)) {
            $this->safeSet(
                'subscriptions',
                $this->repos->collageSubscriptions->find('CollageID = ?', [$this->ID], null, null, null, 'UserID')
            );
        }

        # Just return from the parent
        return parent::__get('subscriptions');
    }

    /**
     * subscription Returns CollageSubscription object for user or null.
     * @param  User|int  $user  User object or userID of the user to check against
     * @return CollageSubscription|null    CollageSubscription object if the user is subscribed, null otherwise.
     *
     * @access public
     */
    public function subscription($user) {
        $user = $this->repos->users->load($user);

        $subscriptions = $this->subscriptions();
        if (empty($subscriptions)) {
            return;
        }

        if (array_key_exists($user->ID, $subscriptions)) {
            return $subscriptions[$user->ID];
        }
    }

    /**
     * isSubscribed Returns whether user has subscribed to this collage or not.
     * @param  User|int  $user  User object or userID of the user to check against
     * @return bool      True if user has subscribed to this thread, false otherwise.
     *
     * @access public
     */
    public function isSubscribed($user = null): bool {
        # Load user or use currently logged in user
        if (is_null($user)) {
            $user = $this->master->request->user;
        } else {
            $user = $this->repos->users->load($user);
        }

        return ($this->subscription($user) instanceof CollageSubscription);
    }


    /**
     * isBookmarked Returns whether or not the collage has been bookmarked by a specific user
     * @param  User|int      $user  User object or UserID integer.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isBookmarked($user = null): bool {
        $user = $this->repos->users->load($user);
        $bookmarked = $this->db->rawQuery(
            "SELECT TRUE
               FROM bookmarks_collages
              WHERE CollageID=? AND UserID=?",
            [$this->ID, $user->ID]
        )->fetchColumn();
        return boolval($bookmarked);
    }

    /**
     * getGroupAccess returns an array which identifies which groups and have access to this collage.
     * @return array           List of all groups, including access information
     *
     * @access public
     */
    public function getGroupAccess(): array {
        $groups = $this->repos->permissions->getGroups();

        foreach ($groups as &$group) {
            $group['Access'] = array_key_exists($group['ID'], $this->groups);
        }

        return $groups;
    }

    /**
     * Remove access for some groups for a given collage.
     *
     * @param array $groupIDs   List of groups to remove.
     *
     * @throws InternalError If IP held in $address is invlaid.
     *
     * @access public
     */
    public function deleteGroupsAccess(array $groupIDs) {
        foreach ($this->groups as $group) {
            if (in_array($group->GroupID, $groupIDs)) {
                $this->repos->collageGroups->delete($group);
            }
        }
    }

    /**
     * Give access to some group for a given collage.
     * @param array $groupIDs    The ID of the group to add.
     *
     */
    public function addGroupAccess(array $groupIDs) {
        $timestamp = new \DateTime;
        $user = $this->master->request->user;

        foreach ($groupIDs as $groupID) {
            $group = $this->repos->permissions->load($groupID);
            if (!($group instanceof Permission)) {
                throw new UserError("Unknown group");
            }

            if ($group->IsUserClass === '1') {
                throw new UserError("Cannot assign class as group");
            }

            $group = new CollageGroup([
                'GroupID'   => $groupID,
                'CollageID' => $this->ID,
                'AddedTime' => $timestamp,
                'AddedBy'   => $user->ID,
            ]);
            $this->repos->collageGroups->save($group);
        }
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
            case 'user':
            case 'tags':
            case 'size':
            case 'count':
            case 'commentCount':
            case 'users':
            case 'userCount':
            case 'subCount':
            case 'groups':
            case 'torrents':
            case 'category':
            case 'lastAdded':
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
            case 'user':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->UserID));
                }
                break;

            case 'tags':
                if (!array_key_exists($name, $this->localValues)) {
                    $tags = $this->db->rawQuery(
                        "SELECT t.Name AS name,
                                COUNT(*) count
                           FROM collages_torrents AS ct
                           JOIN torrents_tags AS tt ON ct.GroupID = tt.GroupID
                           JOIN tags AS t ON t.ID = tt.TagID
                          WHERE ct.CollageID = ?
                       GROUP BY tt.TagID
                       ORDER BY count DESC
                          LIMIT 10",
                        [$this->ID]
                    )->fetchAll(\PDO::FETCH_OBJ);
                    $this->safeSet($name, $tags);
                }
                break;

            case 'size':
                if (!array_key_exists($name, $this->localValues)) {
                    $size = $this->db->rawQuery(
                        "SELECT SUM(t.Size) AS Size
                           FROM collages_torrents AS ct
                           JOIN torrents AS t ON ct.GroupID = t.GroupID
                          WHERE ct.CollageID = ?",
                        [$this->ID]
                    )->fetchColumn();
                    $this->safeSet($name, $size);
                }
                break;

            case 'count':
                if (!array_key_exists($name, $this->localValues)) {
                    $count = $this->db->rawQuery(
                        "SELECT COUNT(*)
                           FROM collages_torrents
                          WHERE CollageID = ?",
                        [$this->ID]
                    )->fetchColumn();
                    $this->safeSet($name, $count);
                }
                break;

            case 'commentCount':
                if (!array_key_exists($name, $this->localValues)) {
                    $count = $this->db->rawQuery(
                        "SELECT COUNT(*)
                           FROM collages_comments
                          WHERE CollageID = ?",
                        [$this->ID]
                    )->fetchColumn();
                    $this->safeSet($name, $count);
                }
                break;

            case 'users':
                if (!array_key_exists($name, $this->localValues)) {
                    $users = $this->db->rawQuery(
                        "SELECT ct.UserID AS userID,
                                u.Username AS name,
                                COUNT(ct.GroupID) AS count
                           FROM collages_torrents AS ct
                           JOIN users AS u ON ct.UserID = u.ID
                          WHERE CollageID = ?
                       GROUP BY ct.UserID
                       ORDER BY count DESC",
                        [$this->ID]
                    )->fetchAll(\PDO::FETCH_ASSOC|\PDO::FETCH_UNIQUE);
                    $this->safeSet($name, $users);
                }
                break;

            case 'userCount':
                if (!array_key_exists($name, $this->localValues)) {
                    $userCount = count($this->users);
                    $this->safeSet($name, $userCount);
                }
                break;

            case 'subCount':
                if (!array_key_exists($name, $this->localValues)) {
                    $subCount = $this->cache->getValue('collage_'.$this->ID.'_subscribers');
                    if ($subCount === false) {
                        $subCount = $this->db->rawQuery(
                            'SELECT COUNT(UserID)
                               FROM collages_subscriptions
                              WHERE CollageID = ?',
                            [$this->ID]
                        )->fetchColumn();
                        $this->cache->cacheValue('collage_'.$this->ID.'_subscribers', $subCount, 0);
                    }
                    $this->safeSet($name, $subCount);
                }
                break;

            case 'groups':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->collageGroups->find('CollageID = ?', [$this->ID], null,  null,  null, 'GroupID'));
                }
                break;

            case 'torrents':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->collageTorrents->find('CollageID = ?', [$this->ID], 'Sort'));
                }
                break;

            case 'category':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->collageCategories->load($this->CategoryID));
                }
                break;

            case 'lastAdded':
                if (!array_key_exists($name, $this->localValues)) {
                    $lastAdded = $this->db->rawQuery(
                        "SELECT Max(AddedOn)
                           FROM collages_torrents
                          WHERE CollageID = ?",
                        [$this->ID]
                    )->fetchColumn();
                    $this->safeSet($name, $lastAdded);
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
