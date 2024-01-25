<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Torrent Entity representing rows from the `torrents` DB table.
 */
class Torrent extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'torrents';

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
        'ID'                => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',      'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'GroupID'           => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',      'nullable' => false, 'default' => 0    ],
        'UserID'            => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',      'nullable' => true,  'default' => null ],
        'info_hash'         => [ 'type' => 'str',       'sqltype' => 'BLOB',              'nullable' => false, 'default' => 0    ],
        'FileCount'         => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',      'nullable' => false, 'default' => 0    ],
        'FileList'          => [ 'type' => 'str',       'sqltype' => 'MEDIUMTEXT',        'nullable' => false, 'default' => ''   ],
        'FilePath'          => [ 'type' => 'str',       'sqltype' => 'VARCHAR(255)',      'nullable' => false, 'default' => ''   ],
        'Size'              => [ 'type' => 'int',       'sqltype' => 'BIGINT UNSIGNED',   'nullable' => false, 'default' => 0    ],
        'Leechers'          => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',      'nullable' => false, 'default' => 0    ],
        'Seeders'           => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',      'nullable' => false, 'default' => 0    ],
        'AverageSeeders'    => [ 'type' => 'float',     'sqltype' => 'FLOAT',             'nullable' => false, 'default' => 0.0  ],
        'last_action'       => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',          'nullable' => true,  'default' => null ],
        'FreeTorrent'       => [ 'type' => 'str',       'sqltype' => "ENUM('0','1','2')", 'nullable' => false, 'default' => '0'  ],
        'DoubleTorrent'     => [ 'type' => 'str',       'sqltype' => "ENUM('0','1')",     'nullable' => false, 'default' => '0'  ],
        'Time'              => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',          'nullable' => true,  'default' => null ],
        'Anonymous'         => [ 'type' => 'str',       'sqltype' => "ENUM('0','1')",     'nullable' => false, 'default' => '0'  ],
        'Snatched'          => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',      'nullable' => false, 'default' => 0    ],
        'balance'           => [ 'type' => 'int',       'sqltype' => 'BIGINT',            'nullable' => false, 'default' => 0    ],
        'LastReseedRequest' => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',          'nullable' => true,  'default' => null ],
        'ExtendedGrace'     => [ 'type' => 'str',       'sqltype' => "ENUM('0','1')",     'nullable' => false, 'default' => '0'  ],
        'Tasted'            => [ 'type' => 'str',       'sqltype' => "ENUM('0','1')",     'nullable' => false, 'default' => '0'  ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'InfoHash'       => [ 'columns' => [ [ 'name' => 'info_hash', 'length' => 40] ], 'type' => 'unique' ],
        'GroupID'        => [ 'columns' => [ 'GroupID' ] ],
        'UserID'         => [ 'columns' => [ 'UserID' ] ],
        'FileCount'      => [ 'columns' => [ 'FileCount' ] ],
        'Size'           => [ 'columns' => [ 'Size' ] ],
        'Seeders'        => [ 'columns' => [ 'Seeders' ] ],
        'Leechers'       => [ 'columns' => [ 'Leechers' ] ],
        'Snatched'       => [ 'columns' => [ 'Snatched' ] ],
        'last_action'    => [ 'columns' => [ 'last_action' ] ],
        'Time'           => [ 'columns' => [ 'Time' ] ],
        'FreeTorrent'    => [ 'columns' => [ 'FreeTorrent' ] ],
        'AverageSeeders' => [ 'columns' => [ 'AverageSeeders' ] ],
    ];

    // Cache for 5 mins only!
    const CACHE_EXPIRATION = 300;

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
            case 'uploader':
            case 'allReports':
            case 'allReportCount':
            case 'openReports':
            case 'openReportCount':
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
            case 'uploader':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->UserID));
                }
                break;

            case 'allReports':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->reports->find('TorrentID = ?', [$this->ID]));
                }
                break;

            case 'allReportCount':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, count($this->allReports));
                }
                break;

            case 'openReports':
                if (!array_key_exists($name, $this->localValues)) {
                    $openReports = [];
                    foreach ($this->allReports as $report) {
                        if ($report->Status === 'Resolved') {
                            continue;
                        }
                        if ($report->Type === 'edited') {
                            continue;
                        }
                        $openReports[] = $report;
                    }
                    $this->safeSet($name, $openReports);
                }
                break;

            case 'openReportCount':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, count($this->openReports));
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
