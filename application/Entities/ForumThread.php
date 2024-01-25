<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;
use Luminance\Entities\ForumPost;

/**
 * ForumThread Entity representing rows from the `forums_threads` DB table.
 */
class ForumThread extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'forums_threads';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'auth'  => 'Auth',
        'db'    => 'DB',
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
        'ID'               => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Title'            => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'AuthorID'         => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'ForumID'          => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'NumViews'         => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'default' => '0' ],
        'Notes'            => [ 'type' => 'str', 'sqltype' => 'MEDIUMTEXT',   'nullable' => true,  'default' => null ],
        'Flags'            => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false, 'default' => '0' ],
        'IsLocked'         => [ 'type' => 'str', 'sqltype' => "ENUM('0','1')", 'nullable' => false, 'default' => '0' ],
        'IsSticky'         => [ 'type' => 'str', 'sqltype' => "ENUM('0','1')", 'nullable' => false, 'default' => '0' ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'AuthorID'          => [ 'columns' => [ 'AuthorID'              ] ],
        'ForumID'           => [ 'columns' => [ 'ForumID'               ] ],
        'Title'             => [ 'columns' => [ 'Title'                 ] ],
        'IsSticky'          => [ 'columns' => [ 'IsSticky'              ] ],
    ];

    const PINNED  = 1 << 0;
    const LOCKED  = 1 << 1;
    const TRASHED = 1 << 2;

    /**
    * lastRead Returns last post the user read or null.
    * @param  User|int  $user  User object or userID of the user to check against
    * @return ForumLastRead|null    ForumLastRead object of the latest post the user loaded, null otherwise.
    *
    * @access public
     */
    public function lastRead($user) {
        $user = $this->repos->users->load($user);

        # Store in a local cache for a slight performance boost
        if (!array_key_exists('lastReadPost', $this->localValues)) {
            $this->safeSet('lastReadPost', $this->repos->forumLastReads->load([$this->ID, $user->ID]));
        }

        # Just return from the parent
        return parent::__get('lastReadPost');
    }

    /**
    * unread Returns the number of posts the user has not read yet.
    * @param  User|int  $user  User object or userID of the user to check against
    * @return int              Integer number of unread posts
    *
    * @access public
     */
    public function unread($user) {
        $user = $this->repos->users->load($user);
        $lastRead = $this->lastRead($user);
        $unreadPosts = null;

        $catchupTime = new \DateTime($user->legacy['CatchupTime']);
        $since = $catchupTime;

        if ($lastRead instanceof ForumLastRead) {
            if ($lastRead->post instanceof ForumPost) {
                if (empty($user->legacy['CatchupTime'])) {
                    $since = $lastRead->post->AddedTime;
                } else {
                    # Finally, if the new post is newer than the user's catchup time (catchup all)
                    if ($lastRead->post->AddedTime > $catchupTime) {
                        $since = $lastRead->post->AddedTime;
                    }
                }
            }
        }

        $unreadPosts = $this->db->rawQuery(
            'SELECT COUNT(*)
               FROM forums_posts
              WHERE ThreadID = ?
                AND AddedTime > ?',
            [$this->ID, $since->format('Y-m-d H:i:s')]
        )->fetchColumn();

        return $unreadPosts;
    }

    /**
     * hasUnread Returns whether user has read all posts in this thread or not.
     * @param  User|int  $user  User object or userID of the user to check against
     * @return bool             True if user has read all posts, false otherwise.
     *
     * @access public
     */
    public function hasUnread($user) {
        return $this->unread($user) > 0;
    }

    /**
    * subscription Returns ForumSubscription object for user or null.
    * @param  User|int  $user  User object or userID of the user to check against
    * @return ForumSubscription|null    ForumSubscription object if the user is subscribed, null otherwise.
    *
    * @access public
     */
    public function subscription($user) {
        $user = $this->repos->users->load($user);

        # Store in a local cache for a slight performance boost
        if (!array_key_exists('subscription', $this->localValues)) {
            $this->safeSet('subscription', $this->repos->forumSubscriptions->load([$user->ID, $this->ID]));
        }

        # Just return from the parent
        return parent::__get('subscription');
    }

    /**
    * isSubscribed Returns whether user has subscribed to this thread or not.
    * @param  User|int  $user  User object or userID of the user to check against
    * @return bool      True if user has subscribed to this thread, false otherwise.
    *
    * @access public
     */
    public function isSubscribed($user): bool {
        return ($this->subscription($user) instanceof ForumSubscription);
    }

    /**
     * isTrashed Returns whether or not the PINNED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isPinned(): bool {
        return $this->getFlag(self::PINNED);
    }

    /**
     * isTrashed Returns whether or not the LOCKED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isLocked(): bool {
        return $this->getFlag(self::LOCKED);
    }

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
     * __isset returns whether an object property exists or not,
     * this is necessary for lazy loading to function correctly from TWIG
     * @param  string  $name Name of property being checked
     * @return bool          True if property exists, false otherwise
     *
     * @access public
     */
    public function __isset($name) {
        switch ($name) {
            case 'poll':
            case 'forum':
            case 'lastPost':
            case 'numPosts':
            case 'numPostsInFlow':
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
            case 'poll':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->forumPolls->get('ThreadID = ?', [$this->ID]));
                }
                break;

            case 'forum':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->forums->load($this->ForumID));
                }
                break;

            case 'lastPost':
                if (!array_key_exists($name, $this->localValues)) {
                    if ($this->auth->isAllowed('forum_post_trash')) {
                        $checkFlags = 0;
                    } else {
                        $checkFlags = ForumPost::TRASHED;
                    }

                    $lastPostID = (array) $this->cache->getValue("thread_last_post_{$this->ID}");
                    if (($lastPostID[$checkFlags] ?? false) === false) {
                        $lastPostID[$checkFlags] = $this->db->rawQuery(
                            "SELECT ID
                               FROM forums_posts
                              WHERE ThreadID = ?
                                AND Flags & ? = 0
                              ORDER BY AddedTime DESC
                              LIMIT 1",
                            [$this->ID, $checkFlags]
                        )->fetchColumn();
                        $this->cache->cacheValue("thread_last_post_{$this->ID}", $lastPostID, 0);
                    }
                    $this->safeSet($name, $this->repos->forumPosts->load($lastPostID[$checkFlags]));
                }
                break;

            case 'numPosts':
                if (!array_key_exists($name, $this->localValues)) {
                    $count = $this->cache->getValue("forum_posts_count_{$this->ID}");
                    if ($count === false) {
                        $count = $this->db->rawQuery(
                            "SELECT COUNT(*) FROM `forums_posts` WHERE ThreadID = ?",
                            [$this->ID]
                        )->fetchColumn();
                        $this->cache->cacheValue("forum_posts_count_{$this->ID}", $count, 0);
                    }
                    $this->safeSet($name, $count);
                }
                break;

            case 'numPostsInFlow':
                if (!array_key_exists($name, $this->localValues)) {
                    # We need to modify flags based on user or staff viewing
                    if ($this->auth->isAllowed('forum_moderate')) {
                        $checkFlags = ForumPost::PINNED;
                    } else {
                        $checkFlags = ForumPost::PINNED | ForumPost::TRASHED;
                    }

                    $count = (array) $this->cache->getValue("forum_posts_flow_count_{$this->ID}");
                    if (($count[$checkFlags] ?? false) === false) {
                        $count[$checkFlags] = $this->db->rawQuery(
                            "SELECT COUNT(*) FROM `forums_posts` WHERE ThreadID = ? AND Flags & ? = 0",
                            [$this->ID, $checkFlags]
                        )->fetchColumn();
                        $this->cache->cacheValue("forum_posts_flow_count_{$this->ID}", $count, 0);
                    }
                    $this->safeSet($name, $count[$checkFlags]);
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
