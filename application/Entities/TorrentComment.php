<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * TorrentComment Entity representing rows from the `torrents_comments` DB table.
 */
class TorrentComment extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'torrents_comments';

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
        'ID'            => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'GroupID'       => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => false ],
        'TorrentID'     => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => false ],
        'AuthorID'      => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => false ],
        'AddedTime'     => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',         'nullable' => true, 'default' => null ],
        'Body'          => [ 'type' => 'str',       'sqltype' => 'MEDIUMTEXT',       'nullable' => true, 'default' => null ],
        'EditedUserID'  => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',     'nullable' => true, 'default' => null ],
        'EditedTime'    => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',         'nullable' => true, 'default' => null ],
        'Flags'         => [ 'type' => 'int',       'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false, 'default' => self::TIMELOCKED],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'GroupID'   => [ 'columns' => [ 'GroupID' ] ],
        'AuthorID'  => [ 'columns' => [ 'AuthorID' ] ],
    ];

    const SYSTEM       = 1 << 0;
    const TIMELOCKED   = 1 << 1;
    const EDITLOCKED   = 1 << 2;
    const PINNED       = 1 << 3;
    const TRASHED      = 1 << 4;

    /**
     * canEdit Returns whether user has permission to edit this post.
     * @param  User|int      $user  User object or UserID integer.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canEdit($user) {
        $user = $this->repos->users->load($user);

        if ($this->auth->isAllowed('torrent_post_edit')) {
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
            case 'group':
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

            case 'group':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->torrentGroups->load($this->GroupID));
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
