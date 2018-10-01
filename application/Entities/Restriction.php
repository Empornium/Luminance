<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Restriction extends Entity {

    public static $table = 'restrictions';

    public static $properties = [
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary'  => true, 'auto_increment' => true ],
        'UserID'   => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'StaffID'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Flags'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Created'  => [ 'type' => 'timestamp', 'nullable' => true ],
        'Expires'  => [ 'type' => 'timestamp', 'nullable' => true ],
        'Comment'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
    ];

    public static $indexes = [
        'UserID'       => [ 'columns' => [ 'UserID' ] ],
        'Expires'      => [ 'columns' => [ 'Expires' ] ],
        'Created'      => [ 'columns' => [ 'Created' ] ],
    ];

    #TODO implement restriction logic for comments (separate from posts)
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


    public static $decode = [
        self::POST              => ['name' => 'post',               'perms' => ['users_disable_posts']],
        self::AVATAR            => ['name' => 'avatar',             'perms' => ['users_disable_any']  ],
        self::INVITE            => ['name' => 'invite',             'perms' => ['users_disable_any']  ],
        self::FORUM             => ['name' => 'forum',              'perms' => ['users_disable_posts']],
        self::COMMENT           => ['name' => 'comment',            'perms' => ['users_disable_posts']],
        self::TAGGING           => ['name' => 'tagging',            'perms' => ['users_disable_any']  ],
        self::UPLOAD            => ['name' => 'upload',             'perms' => ['users_disable_any']  ],
        self::REQUEST           => ['name' => 'request',            'perms' => ['users_disable_any']  ],
        self::PM                => ['name' => 'pm',                 'perms' => ['users_disable_any']  ],
        self::STAFFPM           => ['name' => 'staffpm',            'perms' => ['users_disable_any']  ],
        self::REPORT            => ['name' => 'report',             'perms' => ['users_disable_any']  ],
        self::SIGNATURE         => ['name' => 'signature',          'perms' => ['users_disable_any']  ],
        self::TORRENTSIGNATURE  => ['name' => 'torrent signature',  'perms' => ['users_disable_any']  ],
    ];

    public function is_warning() {
        return ($this->Flags & self::WARNED) != 0;
    }

    public function get_restrictions() {
        $decoded = [];
        foreach (self::$decode as $key => $restrict) {
            if (($this->Flags & $key) != 0)
                $decoded[] = $restrict['name'];
        }
        return $decoded;
    }
}
