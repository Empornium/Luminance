<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class User extends Entity {

    public static $table = 'users';

    protected static $useRepositories = [
        'emails' => 'EmailRepository',
        'users' => 'UserRepository',
    ];

    public $legacy;

    public static $properties = [
        'ID'                => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'EmailID'           => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'IPID'              => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'Username'          => [ 'type' => 'str', 'nullable' => false ],
        'Password'          => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
        'twoFactorSecret'   => [ 'type' => 'str', 'sqltype' => 'VARBINARY(255)', 'nullable' => true ],
//        'StatusFlags'   => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false ],
//        'SecurityFlags' => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false ],
    ];

    public static $indexes = [
        'EmailID'       => [ 'columns' => [ 'EmailID' ] ],
        'Username'      => [ 'columns' => [ 'Username' ] ],
//        'StatusFlags'   => [ 'columns' => [ 'StatusFlags' ] ],
//        'SecurityFlags' => [ 'columns' => [ 'SecurityFlags' ] ],
    ];

    public function needs_update() {
        return (is_null($this->Username) || !strlen($this->Username) || is_null($this->Password));
    }

    public function info() {
        $l = $this->legacy;
        $UserInfo = [
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
        $NoEscape = ['Paranoia', 'Title'];
        foreach ($UserInfo as $Key => $Val) {
            if (!in_array($Key, $NoEscape)) {
                $UserInfo[$Key] = display_str($Val);
            }
        }
        $UserInfo['CatchupTime'] = strtotime($UserInfo['CatchupTime']);
        $UserInfo['Paranoia'] = unserialize($UserInfo['Paranoia']);
        if ($UserInfo['Paranoia'] === false) {
            $UserInfo['Paranoia'] = array();
        }

        return $UserInfo;
    }

    public function heavy_info() {
        $l = $this->legacy;
        $HeavyInfo = [
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
            'Credits'             => $l['Credits'],
            'SupportFor'          => $l['SupportFor'],
            'BlockPMs'            => $l['BlockPMs'],
            'CommentsNotify'      => $l['CommentsNotify'],
            'TimeZone'            => $l['TimeZone'],
            'SuppressConnPrompt'  => $l['SuppressConnPrompt'],
        ];

        $NoEscape = ['CustomPermissions', 'SiteOptions'];
        foreach ($HeavyInfo as $Key => $Val) {
            if (!in_array($Key, $NoEscape)) {
                $HeavyInfo[$Key] = display_str($Val);
            }
        }
        if (!empty($HeavyInfo['CustomPermissions'])) {
            $HeavyInfo['CustomPermissions'] = unserialize($HeavyInfo['CustomPermissions']);
        } else {
            $HeavyInfo['CustomPermissions'] = array();
        }

        if (!empty($HeavyInfo['RestrictedForums'])) {
            $RestrictedForums = (array)explode(',', $HeavyInfo['RestrictedForums']);
        } else {
            $RestrictedForums = array();
        }
        unset($HeavyInfo['RestrictedForums']);

        if (!empty($HeavyInfo['PermittedForums'])) {
            $PermittedForums = (array)explode(',', $HeavyInfo['PermittedForums']);
        } else {
            $PermittedForums = array();
        }
        unset($HeavyInfo['PermittedForums']);

        if (!empty($PermittedForums) || !empty($RestrictedForums)) {
            $HeavyInfo['CustomForums'] = array();
            foreach ($RestrictedForums as $ForumID) {
                $HeavyInfo['CustomForums'][$ForumID] = 0;
            }
            foreach ($PermittedForums as $ForumID) {
                $HeavyInfo['CustomForums'][$ForumID] = 1;
            }
        } else {
            $HeavyInfo['CustomForums'] = null;
        }

        $HeavyInfo['SiteOptions'] = unserialize($HeavyInfo['SiteOptions']);
        if (!empty($HeavyInfo['SiteOptions'])) {
            $HeavyInfo = array_merge($HeavyInfo, $HeavyInfo['SiteOptions']);
        }
        unset($HeavyInfo['SiteOptions']);

        //if (!isset($HeavyInfo['MaxTags'])) $HeavyInfo['MaxTags'] = 16;

        if (!empty($HeavyInfo['Badges'])) {
            $HeavyInfo['Badges'] = unserialize($HeavyInfo['Badges']);
            //$HeavyInfo = array_merge($HeavyInfo, $HeavyInfo['Badges']);
        } else {
            $HeavyInfo['Badges'] = array();
        }

        if (empty($HeavyInfo['TimeZone']) || $HeavyInfo['TimeZone'] == '')
            $HeavyInfo['TimeOffset'] = 0;
        else {
            $HeavyInfo['TimeOffset'] = get_timezone_offset($HeavyInfo['TimeZone']);
        }

        if (!isset($HeavyInfo['MaxTags'])) $HeavyInfo['MaxTags'] = 100;

        return $HeavyInfo;
    }

    public function stats() {
        $l = $this->legacy;
        $UserStats = [];
        $UserStats['BytesUploaded'] = $l['Uploaded'];
        $UserStats['BytesDownloaded'] = $l['Downloaded'];
        $UserStats['RequiredRatio'] = $l['RequiredRatio'];
        $UserStats['TotalCredits'] = $l['Credits'];
        return $UserStats;
    }

    public function on_ratiowatch() {
        //$LoggedUser['RatioWatch'] as a bool to disable things for users on Ratio Watch
        $l = $this->legacy;
        $OnRatioWatch = (
            $l['RatioWatchEnds'] != '0000-00-00 00:00:00' &&
            ($l['Downloaded'] * $l['RequiredRatio']) > $l['Uploaded']
        );
        return $OnRatioWatch;
    }

    public function send_email($subject, $template, $variables) {
        $email = $this->emails->load($this->EmailID);
        $email->send_email($subject, $template, $variables);
    }

    /**
     * @param null $key The key to return from the options, if none return all options
     * @param null $default The default value to return if the option is not found
     * @return mixed|null
     */
    public function options($key = null, $default = null) {
        $options = @unserialize($this->legacy['SiteOptions']);

        if (!$options) {
            return $default;
        }

        if (!$key) {
            return $options;
        }

        if (!array_key_exists($key, $options)) {
            return $default;
        }

        return $options[$key];
    }

    /**
     * @param string $key
     * @param $value
     * @return bool
     */
    public function setOption(string $key, $value): bool {
        $options = @unserialize($this->legacy['SiteOptions']);

        if (!$options) {
            $options = [];
        }

        // Set the new value
        $options[$key] = $value;

        // Update DB
        $new_options = db_string(serialize($options));
        $userID = (int) $this->ID;
        $this->master->db->raw_query("UPDATE users_info SET SiteOptions = '{$new_options}' WHERE UserID = {$userID}");

        // Clear cache
        $this->users->uncache($this->ID);

        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function unsetOption(string $key) {
        $options = @unserialize($this->legacy['SiteOptions']);

        if (!$options) {
            return true;
        }


        // Unset the option
        unset($options[$key]);

        // Update DB
        $new_options = db_string(serialize($options));
        $userID = (int) $this->ID;
        $this->master->db->raw_query("UPDATE users_info SET SiteOptions = '{$new_options}' WHERE UserID = {$userID}");

        // Clear cache
        $this->users->uncache($this->ID);

        return true;
    }
}
