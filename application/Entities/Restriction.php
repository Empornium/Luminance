<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Restriction Entity representing rows from the `restrictions` DB table.
 */
class Restriction extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'restrictions';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary'  => true, 'auto_increment' => true ],
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'StaffID'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Flags'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Created'  => [ 'type' => 'timestamp', 'nullable' => true ],
        'Expires'  => [ 'type' => 'timestamp', 'nullable' => true ],
        'Comment'  => [ 'type' => 'str', 'sqltype' => 'TEXT', 'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID'       => [ 'columns' => [ 'UserID' ] ],
        'Expires'      => [ 'columns' => [ 'Expires' ] ],
        'Created'      => [ 'columns' => [ 'Created' ] ],
    ];

    const WARNED            = 1 <<  0;
    const POST              = 1 <<  1;
    const AVATAR            = 1 <<  2;
    const INVITE            = 1 <<  3;
    const FORUM             = 1 <<  4;
    const COMMENT           = 1 <<  5;
    const TAGGING           = 1 <<  6;
    const UPLOAD            = 1 <<  7;
    const REQUEST           = 1 <<  8;
    const PM                = 1 <<  9;
    const STAFFPM           = 1 << 10;
    const REPORT            = 1 << 11;
    const SIGNATURE         = 1 << 12;
    const TORRENTSIGNATURE  = 1 << 13;
    const API               = 1 << 14;

    /**
     * $decode Array mapping the Restriction flags to required permissions to set and human readable names.
     * @var array
     *
     * @access public
     * @static
     */
    public static $decode = [
        ['flag' => self::POST,             'name' => 'post',               'key' => 'DisablePost',       'permission' => ['users_disable_posts']],
        ['flag' => self::AVATAR,           'name' => 'avatar',             'key' => 'DisableAvatar',     'permission' => ['users_disable_any']  ],
        ['flag' => self::INVITE,           'name' => 'invite',             'key' => 'DisableInvite',     'permission' => ['users_disable_any']  ],
        ['flag' => self::FORUM,            'name' => 'forum',              'key' => 'DisableForum',      'permission' => ['users_disable_posts']],
        ['flag' => self::COMMENT,          'name' => 'comment',            'key' => 'DisableComments',   'permission' => ['users_disable_posts']],
        ['flag' => self::TAGGING,          'name' => 'tagging',            'key' => 'DisableTagging',    'permission' => ['users_disable_any']  ],
        ['flag' => self::UPLOAD,           'name' => 'upload',             'key' => 'DisableUpload',     'permission' => ['users_disable_any']  ],
        ['flag' => self::REQUEST,          'name' => 'request',            'key' => 'DisableRequest',    'permission' => ['users_disable_any']  ],
        ['flag' => self::PM,               'name' => 'pm',                 'key' => 'DisablePM',         'permission' => ['users_disable_any']  ],
        ['flag' => self::STAFFPM,          'name' => 'staffpm',            'key' => 'DisableStaffPM',    'permission' => ['users_disable_any']  ],
        ['flag' => self::REPORT,           'name' => 'report',             'key' => 'DisableReport',     'permission' => ['users_disable_any']  ],
        ['flag' => self::SIGNATURE,        'name' => 'signature',          'key' => 'DisableSignature',  'permission' => ['users_disable_any']  ],
        ['flag' => self::TORRENTSIGNATURE, 'name' => 'torrent signature',  'key' => 'DisableTorrentSig', 'permission' => ['users_disable_any']  ],
        ['flag' => self::API,              'name' => 'api access',         'key' => 'DisableAPI',        'permission' => ['users_disable_any']  ],
    ];

    /**
     * isWarning Returns whether this restriction is a user warning.
     * @return bool True if this is a warning, false otherwise.
     *
     * @access public
     */
    public function isWarning() {
        return !(($this->Flags & self::WARNED) === 0);
    }

    /**
     * getRestrictions Returns a decoded array of restrictions this object represents.
     * @return array Array containing the set restrictions.
     *
     * @access public
     */
    public function getRestrictions() {
        $decoded = [];
        $decode = array_column(self::$decode, null, 'flag');
        foreach ($decode as $key => $restrict) {
            if (!(($this->Flags & $key) === 0)) {
                $decoded[] = $restrict['name'];
            }
        }
        return $decoded;
    }
}
