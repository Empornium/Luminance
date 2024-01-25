<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ForumPost Entity representing rows from the `forums_posts` DB table.
 */
class ForumPost extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'forums_posts';

    /**
     * $useServices represents a mapping of the Luminance services which
     * should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'auth'    => 'Auth',
        'options' => 'Options',
        'repos'   => 'Repos',
    ];

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'           => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'primary'  => true, 'auto_increment' => true ],
        'ThreadID'     => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => false ],
        'AuthorID'     => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => false ],
        'AddedTime'    => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',         'nullable' => true, 'default' => null ],
        'Body'         => [ 'type' => 'str',       'sqltype' => 'MEDIUMTEXT',       'nullable' => true, 'default' => null ],
        'EditedUserID' => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => true, 'default' => null ],
        'EditedTime'   => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',         'nullable' => true, 'default' => null ],
        'Flags'        => [ 'type' => 'int',       'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false, 'default' => self::TIMELOCKED],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
      'ThreadID'          => [ 'columns' => [ 'ThreadID'  ] ],
      'AuthorID'          => [ 'columns' => [ 'AuthorID'  ] ],
      'AddedTime'         => [ 'columns' => [ 'AddedTime' ] ],
      'Thread_AddedTime'  => [ 'columns' => [ 'ThreadID', 'AddedTime'          ] ],
      'Thread_Flags'      => [ 'columns' => [ 'ThreadID', 'Flags'              ] ],
      'Thread_Flags_Time' => [ 'columns' => [ 'ThreadID', 'Flags', 'AddedTime' ] ],
      //'Body'              => [ 'columns' => [ 'Body' ], 'type' => 'fulltext' ],
    ];

    const SYSTEM       = 1 << 0;
    const TIMELOCKED   = 1 << 1;
    const EDITLOCKED   = 1 << 2;
    const PINNED       = 1 << 3;
    const TRASHED      = 1 << 4;

    /**
     * canEdit Returns whether user has permission to edit this post.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canEdit(): bool {
        $user = $this->master->request->user;

        if ($this->auth->isAllowed('forum_post_edit')) {
            return true; # moderators can edit anything
        }

        # Normal users can't edit other's posts
        if (!($this->AuthorID === $user->ID)) {
            return false;
        }

        # Users can't edit after a moderator does
        if ($this->getFlag(self::EDITLOCKED)) {
            return false;
        }

        # Is the user exempt from timelock?
        if ($this->auth->isAllowed('site_edit_own_posts')) {
            return true;
        }

        # Is the post exempt from timelock?
        if ($this->getFlag(self::TIMELOCKED) && $this->options->EditTimelockEnable) {
            # Okay, now we need to compare timestamps to determine if the post
            # is locked or not.
            $now = new \DateTime();
            $timeLimit = "PT{$this->options->EditTimelockMins}M";
            $isAddedBeforeLimit =  ($now < $this->AddedTime->add(new \DateInterval($timeLimit)));
            if ($this->EditedTime instanceof \DateTime) {
                $isEditedBeforeLimit = ($now < $this->EditedTime->add(new \DateInterval($timeLimit)));
            } else {
                $isEditedBeforeLimit = false;
            }

            return $isAddedBeforeLimit || $isEditedBeforeLimit;
        }

        return true;
    }

    /**
    * unread Returns whether user has read this post or not.
    * @param  Entity  $user object to check against
    * @return bool True if user has read this post, false otherwise.
    *
    * @access public
     */
    public function unread($user) {
        $user = $this->repos->users->load($user);

        # This should never happen... ever.
        if (!($this->thread instanceof ForumThread)) {
            return false;
        }

        $lastRead = $this->thread->lastRead($user);

        if (!$this->thread->IsLocked || $this->thread->IsSticky) {
            $unreadPosts = false;
            if ($lastRead instanceof ForumLastRead) {
                if ($lastRead->post instanceof ForumPost) {
                    $unreadPosts = $lastRead->post->AddedTime < $this->AddedTime;
                }
            }

            # If the thread was never read or there are unread posts
            if (empty($lastRead) || $unreadPosts) {
                # If user never caught up
                if (empty($user->legacy['CatchupTime'])) {
                    return true;
                } else {
                    $catchupTime = new \DateTime($user->legacy['CatchupTime']);
                    # Finally, if the new post is newer than the user's catchup time (catchup all)
                    if ($this->AddedTime > $catchupTime) {
                        return true;
                    }
                }
            }
        }
        return false;
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
            case 'author':
            case 'editor':
            case 'thread':
                return true;

            default:
                return parent::__isset($name);
        }
    }

    /**
     * isTrash Returns whether or not the TRASHED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isTrashed() {
        return $this->getFlag(self::TRASHED);
    }

    /**
     * isPinned Returns whether or not the PINNED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isPinned() {
        return $this->getFlag(self::PINNED);
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
            case 'author':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->AuthorID));
                }
                break;

            case 'editor':
                if (!array_key_exists($name, $this->localValues)) {
                    $authorID = $this->AuthorID;
                    if (!empty($this->EditedUserID)) {
                        $authorID = $this->EditedUserID;
                    }
                    $this->safeSet($name, $this->repos->users->load($authorID));
                }
                break;

            case 'thread':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->forumThreads->load($this->ThreadID));
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
