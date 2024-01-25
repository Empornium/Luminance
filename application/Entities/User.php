<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

use Luminance\Entities\Collage;
use Luminance\Entities\CollageCategory;

/**
 * User Entity representing rows from the `users` DB table.
 */
class User extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'users';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'auth'   => 'Auth',
        'db'     => 'DB',
        'render' => 'Render',
        'repos'  => 'Repos',
    ];

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'                => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED',   'primary' => true, 'auto_increment' => true ],
        'EmailID'           => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED',   'nullable' => true  ],
        'IPID'              => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED',   'nullable' => true  ],
        'Username'          => [ 'type' => 'str',                                'nullable' => false ],
        'Password'          => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)',   'nullable' => true  ],
        'twoFactorSecret'   => [ 'type' => 'str', 'sqltype' => 'VARBINARY(255)', 'nullable' => true  ],
        'IRCNick'           => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)',   'nullable' => true  ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'EmailID'       => [ 'columns' => [ 'EmailID'  ] ],
        'IPID'          => [ 'columns' => [ 'IPID'     ] ],
        'Username'      => [ 'columns' => [ 'Username' ], 'type' => 'unique' ],
    ];

    /**
     * needsUpdate Returns wether this user object uses the latest format.
     * @return bool    True if the object requires an update, false otherwise.
     *
     * @access public
     */
    public function needsUpdate() {
        return (is_null($this->Username) || !strlen($this->Username) || is_null($this->Password));
    }

    /**
     * isTwoFactorEnabled Returns wether this user object has a non-null twoFactorSecret parameter.
     * @return bool    True or false.
     *
     * @access public
     */
    public function isTwoFactorEnabled() {
        #TODO We should probably have a more robust check than this
        return !is_null($this->twoFactorSecret);
    }

    /**
     * info Returns array containing the commonly accessed columns from the legacy user_info table.
     * @return array Array containing the data from the legacy user_info table for this object.
     *
     * @access public
     */
    public function info() {
        $l = $this->legacy;
        $userInfo = [
            'ID' => $this->ID,
            'Username' => $this->Username,
            'PermissionID' => $l['PermissionID'],
            'Paranoia' => $l['Paranoia'],
            'Donor' => $l['Donor'],
            'Avatar' => $l['Avatar'],
            'Enabled' => $l['Enabled'],
            'Title' => $l['Title'],
            'CatchupTime' => $l['CatchupTime'],
            'Visible' => $l['Visible'],
            'Signature' => $l['Signature'],
            'TorrentSignature' => $l['TorrentSignature'],
            'GroupPermissionID' => $l['GroupPermissionID'],
            'ipcc' => $l['ipcc'],
        ];
        $noEscape = ['Paranoia', 'Title'];
        foreach ($userInfo as $key => $val) {
            if (!in_array($key, $noEscape)) {
                $userInfo[$key] = display_str($val);
            }
        }
        $userInfo['CatchupTime'] = strtotime($userInfo['CatchupTime']);

        return $userInfo;
    }

    /**
     * heavyInfo Returns array containing all of the columns from the legacy user_info table.
     * @return array Array containing the data from the legacy user_info table for this object.
     *
     * @access public
     */
    public function heavyInfo() {
        $l = $this->legacy;
        $heavyInfo = [
            'Invites'             => $l['Invites'],
            'torrent_pass'        => $l['torrent_pass'],
            'CustomPermissions'   => $l['CustomPermissions'],
            'CanLeech'            => $l['can_leech'],
            'AuthKey'             => $l['AuthKey'],
            'RatioWatchEnds'      => $l['RatioWatchEnds'],
            'RatioWatchDownload'  => $l['RatioWatchDownload'],
            'StyleID'             => $l['StyleID'],
            'SiteOptions'         => $l['SiteOptions'],
            'DownloadAlt'         => $l['DownloadAlt'],
            'LastReadNews'        => $l['LastReadNews'],
            'LastReadBlog'        => $l['LastReadBlog'],
            'LastReadContests'    => $l['LastReadContests'],
            'LastBrowse'          => $l['LastBrowse'],
            'RestrictedForums'    => $l['RestrictedForums'],
            'PermittedForums'     => $l['PermittedForums'],
            'FLTokens'            => $l['FLTokens'],
            'personal_freeleech'  => $l['personal_freeleech'],
            'personal_doubleseed' => $l['personal_doubleseed'],
            'Credits'             => $this->wallet->Balance,
            'SupportFor'          => $l['SupportFor'],
            'BlockPMs'            => $l['BlockPMs'],
            'CommentsNotify'      => $l['CommentsNotify'],
            'TimeZone'            => $l['TimeZone'],
            'SuppressConnPrompt'  => $l['SuppressConnPrompt'],
        ];

        $noEscape = ['CustomPermissions', 'SiteOptions'];
        foreach ($heavyInfo as $key => $val) {
            if (!in_array($key, $noEscape)) {
                $heavyInfo[$key] = display_str($val);
            }
        }
        if (!empty($heavyInfo['CustomPermissions'])) {
            $heavyInfo['CustomPermissions'] = unserialize($heavyInfo['CustomPermissions']);
        } else {
            $heavyInfo['CustomPermissions'] = [];
        }

        if (!empty($heavyInfo['RestrictedForums'])) {
            $restrictedForums = (array)explode(',', $heavyInfo['RestrictedForums']);
        } else {
            $restrictedForums = [];
        }
        unset($heavyInfo['RestrictedForums']);

        if (!empty($heavyInfo['PermittedForums'])) {
            $permittedForums = (array)explode(',', $heavyInfo['PermittedForums']);
        } else {
            $permittedForums = [];
        }
        unset($heavyInfo['PermittedForums']);

        if (!empty($permittedForums) || !empty($restrictedForums)) {
            $heavyInfo['CustomForums'] = [];
            foreach ($restrictedForums as $forumID) {
                $heavyInfo['CustomForums'][$forumID] = 0;
            }
            foreach ($permittedForums as $forumID) {
                $heavyInfo['CustomForums'][$forumID] = 1;
            }
        } else {
            $heavyInfo['CustomForums'] = null;
        }

        $siteOptions = self::$defaultUserOptions;
        if (is_string($heavyInfo['SiteOptions']) === true && empty($heavyInfo['SiteOptions']) === false) {
            $siteOptions = unserialize($heavyInfo['SiteOptions']);
            if (is_array($siteOptions)) {
                $siteOptions = array_merge(self::$defaultUserOptions, $siteOptions);
            }
            unset($heavyInfo['SiteOptions']);
        }

        if (!empty($siteOptions)) {
            $heavyInfo = array_merge($heavyInfo, $siteOptions);
            unset($siteOptions);
        }

        if (!empty($heavyInfo['Badges'])) {
            $heavyInfo['Badges'] = unserialize($heavyInfo['Badges']);
        } else {
            $heavyInfo['Badges'] = [];
        }

        if (empty($heavyInfo['TimeZone']) || $heavyInfo['TimeZone'] === '')
            $heavyInfo['TimeOffset'] = 0;
        else {
            $heavyInfo['TimeOffset'] = get_timezone_offset($heavyInfo['TimeZone']);
        }

        return $heavyInfo;
    }

    /**
     * stats Returns an array containing data for the user stats block.
     * @return array Array containing data for the user stats block.
     *
     * @access public
     */
    public function stats() {
        $l = $this->legacy;
        $userStats = [];
        $userStats['BytesUploaded'] = $l['Uploaded'];
        $userStats['BytesDownloaded'] = $l['Downloaded'];
        $userStats['RequiredRatio'] = $l['RequiredRatio'];
        $userStats['TotalCredits'] = $this->wallet->Balance;
        return $userStats;
    }

    /**
     * onRatiowatch Returns whether or not the user is currently on RatioWatch.
     * @return bool    True if user is on RatioWatch, false otherwise.
     *
     * @access public
     */
    public function onRatiowatch() {
        #$activeUser['RatioWatch'] as a bool to disable things for users on Ratio Watch
        $l = $this->legacy;
        $onRatioWatch = (
            !($l['RatioWatchEnds'] === '0000-00-00 00:00:00') &&
            ($l['Downloaded'] * $l['RequiredRatio']) > $l['Uploaded']
        );
        return $onRatioWatch;
    }


    /**
     * sendEmail Sends an email to this user's default address.
     * @param  string     $subject   Subject line for this email.
     * @param  string     $template  TWIG template identifier.
     * @param  array|null $variables Variables to be passed to TWIG for rendering or null if none applicable.
     *
     * @access public
     */
    public function sendEmail($subject, $template, $variables) {
        $email = $this->repos->emails->load($this->EmailID);
        if ($email instanceof Email) {
            $email->sendEmail($subject, $template, $variables);
        }
    }

    public static $defaultUserOptions = [
        'AutoSubscribe' => 0,
        'CollageCovers' => 0,
        'CollagesPerPage' => 25,
        'DisableAvatars' => 0,
        'DisabledLatestTopics' => [],
        'DisableSignatures' => 0,
        'DisableSmileys' => 0,
        'DumpData' => 0,
        'ExtendedIPSearch' => 0,
        'HideCats' => 0,
        'HideDetailsSidebar' => 0,
        'HideForumSidebar' => 0,
        'HideFloat' => 0,
        'HideTagsInLists' => 0,
        'HideTypes' => 0,
        'HideUserTorrents' => 0,
        'IpsPerPage' => 25,
        'MaxTags' => 100,
        'MessagesPerPage' => 25,
        'NoBlogAlerts' => 0,
        'NoContestAlerts' => 0,
        'NoForumAlerts' => 0,
        'NoNewsAlerts' => 0,
        'NotForceLinks' => 0,
        'NotVoteUpTags' => 0,
        'PostsPerPage' => 25,
        'ShortTitles' => 0,
        'ShowElapsed' => 0,
        'ShowTags' => 0,
        'ShowTorrentChecker' => 0,
        'SplitByDays' => 0,
        'TimeStyle' => 0,
        'TorrentPreviewWidth' => 200,
        'TorrentPreviewWidthForced' => false,
        'TorrentsPerPage' => 25,
    ];

    /**
     * options Returns an array containing the user's SiteOptions.
     * @param null $key The key to return from the options, if none return all options.
     * @param null $default The default value to return if the option is not found.
     * @return mixed|null Array containing the user's SiteOptions.
     *
     * @access public
     */
    public function options($key = null, $default = null) {
        if (is_array($this->legacy['SiteOptions'])) {
            $options = $this->legacy['SiteOptions'];
        } elseif (is_string($this->legacy['SiteOptions'])) {
            $options = @unserialize($this->legacy['SiteOptions']);
        } else {
            return $default;
        }

        if (!is_array($options)) {
            return $default;
        }

        if (empty($key)) {
            return $options;
        }

        if (!array_key_exists($key, $options)) {
            if (is_null($default)) {
                if (array_key_exists($key, self::$defaultUserOptions)) {
                    return self::$defaultUserOptions[$key];
                }
            }
            return $default;
        }

        return $options[$key];
    }

    /**
     * setOption Sets a site option for the user and stores the updated SiteOption in the database.
     * @param string $key    Key of the site option to set.
     * @param mixed  $value  Value to set the option to.
     * @return bool          Always returns true.
     *
     * @access public
     */
    public function setOption(string $key, $value): bool {
        $options = $this->options();

        if (!is_array($options)) {
            $options = [];
        }

        # Set the new value
        $options[$key] = $value;

        # Update DB
        $newOptions = serialize($options);
        $userID = (int) $this->ID;
        $this->master->db->rawQuery(
            "UPDATE users_info
                SET SiteOptions = ?
              WHERE UserID = ?",
            [$newOptions, $userID]
        );

        # Clear cache
        $this->repos->users->uncache($this->ID);

        return true;
    }

    /**
     * unsetOption Unsets a site option for the user and stores the updated SiteOption in the database.
     * @param string  $key    Key of the site option to unset.
     * @return bool           Always returns true.
     *
     * @access public
     */
    public function unsetOption(string $key): bool {
        $options = $this->options();

        if (!is_array($options)) {
            return true;
        }

        # Unset the option
        unset($options[$key]);

        # Update DB
        $newOptions = serialize($options);
        $userID = (int) $this->ID;
        $this->master->db->rawQuery(
            "UPDATE users_info
                SET SiteOptions = ?
              WHERE UserID = ?",
            [$newOptions, $userID]
        );

        # Clear cache
        $this->repos->users->uncache($this->ID);

        return true;
    }

    /**
     * dupes returns HTML for the dupes tables
     * @return string HTML dupes table
     *
     * @access public
     */
    public function dupes() {
        $dupeInfo = $this->db->rawQuery(
            'SELECT d.ID,
                    d.Comments,
                    SHA1(d.Comments) AS CommentHash
               FROM dupe_groups AS d
               JOIN users_dupes AS u ON u.GroupID = d.ID
              WHERE u.UserID = ?',
            [$this->ID]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!empty($dupeInfo)) {
            $dupes = $this->db->rawQuery(
                'SELECT u.ID,
                        u.Username
                   FROM users AS u
                   JOIN users_dupes AS d ON u.ID = d.UserID
                  WHERE d.GroupID = ?
               ORDER BY u.ID ASC',
                [$dupeInfo['ID']]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $dupes = [];
        }

        $params = [
            'user'       => $this,
            'dupes'      => $dupes,
            'dupeInfo'   => $dupeInfo,
        ];

        return $this->render->template('@User/snippets/user_dupes.html.twig', $params);
    }

    /**
     * userGroups returns HTML for the groups.php tables
     * @return string HTML groups table
     *
     * @access public
     */
    public function userGroups() {
        $groups = $this->db->rawQuery(
            'SELECT g.ID,
                    g.Name,
                    Count(u.ID) AS Count
               FROM groups AS g
          LEFT JOIN users_groups AS u ON u.GroupID=g.ID
           GROUP BY g.ID'
        )->fetchAll(\PDO::FETCH_OBJ);

        $joinedGroups = $this->db->rawQuery(
            'SELECT ug.ID,
                    g.Name as GroupName,
                    ug.GroupID,
                    ug.UserID,
                    ug.Comment
               FROM users_groups as ug
               JOIN groups as g ON g.ID = ug.GroupID
              WHERE UserID = ?',
            [$this->ID]
        )->fetchAll(\PDO::FETCH_OBJ);

        $params = [
            'user'         => $this,
            'groups'       => $groups,
            'joinedGroups' => $joinedGroups,
        ];

        return $this->render->template('@User/snippets/user_groups.html.twig', $params);
    }

    /**
     * Retrieve the user's groups
     *
     * @return array   A list of group IDs
     */
    public function getGroups(): array {
        $groups = $this->db->rawQuery(
            "SELECT p.ID
            FROM permissions as p
            JOIN users_main as um ON um.GroupPermissionID = p.ID
            WHERE p.IsUserClass = '0'
            and um.ID = ?",
            [$this->ID]
        )->fetchAll(\PDO::FETCH_COLUMN);

        return $groups;
    }

    /**
     * isFriend Returns whether or not the user is currently in your freinds list.
     * @return bool    True if user is a friend, false otherwise.
     *
     * @access public
     */
    public function isFriend() {
        if (array_key_exists($this->ID, $this->request->user->friends)) {
            return $this->request->user->friends[$this->ID]->Type === 'friends';
        } else {
            return false;
        }
    }

    /**
     * isBlocked Returns whether or not the user is currently in your blocked list.
     * @return bool    True if user is a blocked, false otherwise.
     *
     * @access public
     */
    public function isBlocked() {
        if (array_key_exists($this->ID, $this->request->user->friends)) {
            return $this->request->user->friends[$this->ID]->Type === 'blocked';
        } else {
            return false;
        }
    }

    /**
     * canPM Returns whether or not the logged in user can PM this user.
     * @return bool    True if user can PM this user, false otherwise.
     *
     * @access public
     */
    public function canPM() {
        $user = $this->request->user;

        # staff are never blocked from sending
        if ($user->class->DisplayStaff) {
            return true;
        }

        # check if sender is on recepients blocked list
        if ($this->isBlocked()) {
            return false;
        }

        # Block all but staff
        if ((int)$this->legacy['BlockPMs'] === 2) {
            return false;
        }

        # Block non friends
        if ((int)$this->legacy['BlockPMs'] === 1 && $this->isFriend() === false) {
            return false;
        }

        return true;
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
            case 'legacy':
            case 'apikeys':
            case 'class':
            case 'group':
            case 'defaultEmail':
            case 'allEmails':
            case 'ip':
            case 'inviter':
            case 'inviteTree':
            case 'allRestrictions':
            case 'floods':
            case 'friends':
            case 'passkeys':
            case 'passwords':
            case 'timeOffset':
            case 'wallet':
            case 'style':
            case 'languages':
            case 'collagesStarted':
            case 'collagesContributed':
            case 'personalCollageCount':
            case 'torrentClients':
            case 'connectable':
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
            case 'legacy':
                if (!array_key_exists($name, $this->localValues)) {
                    $legacy = $this->repos->users->loadLegacyUser($this->ID);
                    $paranoia = [];
                    if (array_key_exists('Paranoia', $legacy)) {
                        if (is_string($legacy['Paranoia']) === true && empty($legacy['Paranoia']) === false) {
                            $paranoia = unserialize($legacy['Paranoia']);
                        }

                        if (is_array($legacy['Paranoia'])) {
                            $paranoia = $legacy['Paranoia'];
                        }
                    }
                    $legacy['Paranoia'] = $paranoia;

                    $this->safeSet($name, $legacy);
                }
                break;

            case 'apikeys':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->apiKeys->find('UserID = ?', [$this->ID]));
                }
                break;

            case 'class':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->permissions->load($this->legacy['PermissionID']));
                }
                break;

            case 'group':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->permissions->load($this->legacy['GroupPermissionID']));
                }
                break;

            case 'defaultEmail':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->emails->load($this->EmailID));
                }
                break;

            case 'allEmails':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->emails->find('UserID = ?', [$this->ID]));
                }
                break;

            case 'ip':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->ips->load($this->IPID));
                }
                break;

            case 'inviter':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->legacy['Inviter']));
                }
                break;

            case 'inviteTree':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->inviteTrees->load($this->ID));
                }
                break;

            case 'allRestrictions':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->restrictions->getAllForUser($this));
                }
                break;

            case 'floods':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->requestFloods->find('`UserID` = ?', [$this->ID]));
                }
                break;

            case 'friends':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->userFriends->find('`UserID` = ?', [$this->ID], null, null, "user_friends_{$this->ID}", 'FriendID'));
                }
                break;

            case 'passkeys':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->userHistoryPasskeys->find('`UserID` = ?', [$this->ID], 'Time DESC', null, "user_passkeys_{$this->ID}"));
                }
                break;

            case 'passwords':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->userHistoryPasswords->find('`UserID` = ?', [$this->ID], 'Time DESC', null, "user_passwords_{$this->ID}"));
                }
                break;

            case 'timeOffset':
                if (!array_key_exists($name, $this->localValues)) {
                    $timeZone = $this->legacy['TimeZone'];
                    if (empty($timeZone)) {
                        $timeZone = 'UTC';
                    }
                    $this->safeSet($name, get_timezone_offset($timeZone));
                }
                break;

            case 'wallet':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->repos->userWallets->disableCache();
                    $this->safeSet($name, $this->repos->userWallets->get('UserID = ?', [$this->ID]));
                }
                break;

            case 'style':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->stylesheets->get('ID = ?', [$this->legacy['StyleID']]));
                }
                break;

            case 'languages':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->userLanguages->find('UserID = ?', [$this->ID]));
                }
                break;

            case 'collagesStarted':
                if (!array_key_exists($name, $this->localValues)) {
                    $checkFlags = 0;
                    if ($this->auth->isAllowed('collage_moderate')) {
                        $checkFlags = Collage::TRASHED;
                    }
                    $this->safeSet($name, $this->repos->collages->find('UserID = ? AND Flags & ? = 0', [$this->ID, $checkFlags, Collage::FEATURED], 'Flags & ? DESC, Name ASC'));
                }
                break;

            case 'collagesContributed':
                if (!array_key_exists($name, $this->localValues)) {
                    $torrents = $this->repos->collageTorrents->find('UserID = ?', [$this->ID]);
                    if (!$this->auth->isAllowed('collage_moderate')) {
                        foreach ($torrents as $index => $torrent) {
                            if ($torrent->collage->isTrashed()) {
                                unset($torrents[$index]);
                            }
                        }
                    }
                    $this->safeSet($name, $torrents);
                }
                break;

            case 'personalCollageCount':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet(
                        $name,
                        $this->db->rawQuery(
                            "SELECT COUNT(*)
                               FROM collages AS c
                               JOIN collage_categories AS cc ON c.CategoryID = cc.ID
                              WHERE c.UserID = ?
                                AND c.Flags & ? = 0
                                AND cc.Flags & ? != 0",
                            [$this->ID, Collage::TRASHED, CollageCategory::PERSONAL]
                        )->fetchColumn()
                    );
                }
                break;

            case 'torrentClients':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet(
                        $name,
                        $this->db->rawQuery(
                            "SELECT useragent,
                                    INET6_NTOA(ipv4) AS `ip`,
                                    LEFT(peer_id, 8) AS `clientid`
                               FROM xbt_files_users
                              WHERE uid = ?
                           GROUP BY useragent, ipv4",
                            [$this->ID]
                        )->fetchAll(\PDO::FETCH_OBJ)
                    );
                }
                break;

            case 'connectable':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet(
                        $name,
                        /* Not yet I guess
                        $this->db->rawQuery(
                            "SELECT ucs.Status AS `status`,
                                    INET6_NTOA(ips.StartAddress) AS `ip`,
                                    xbt.port AS `port`,
                                    Max(ucs.Time) AS `timeChecked`
                               FROM users_connectable_status AS ucs
                          LEFT JOIN ips ON ucs.IPID = ips.ID
                          LEFT JOIN xbt_files_users AS xbt ON xbt.uid = ucs.UserID
                                AND xbt.ipv4 = ips.StartAddress
                                AND xbt.Active = '1'
                              WHERE UserID = ?
                           GROUP BY ucs.IPID
                           ORDER BY Max(ucs.Time) DESC LIMIT 100",
                            [$this->ID]
                        )->fetchAll(\PDO::FETCH_OBJ)
                        */

                        $this->db->rawQuery(
                            "SELECT ucs.Status AS `status`,
                                    ucs.IP AS `ip`,
                                    xbt.port AS `port`,
                                    Max(ucs.Time) AS `timeChecked`
                               FROM users_connectable_status AS ucs
                          LEFT JOIN xbt_files_users AS xbt ON xbt.uid = ucs.UserID
                                AND INET6_NTOA(xbt.ipv4) = ucs.IP
                                AND xbt.Active = '1'
                              WHERE UserID = ?
                           GROUP BY ucs.IP
                           ORDER BY Max(ucs.Time) DESC LIMIT 100",
                            [$this->ID]
                        )->fetchAll(\PDO::FETCH_OBJ)
                    );
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
