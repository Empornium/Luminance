<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * TorrentReview Entity representing rows from the `torrents_reviews` DB table.
 */
class TorrentReview extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'torrents_reviews';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'        => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'GroupID'   => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false ],
        'Time'      => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => 'NULL', 'nullable' => true ],
        'ReasonID'  => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false ],
        'UserID'    => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false ],
        'ConvID'    => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'default' => 'NULL', 'nullable' => true ],
        'Status'    => [ 'type' => 'str', 'sqltype' => "ENUM('None','Okay','Warned','Pending')", 'nullable' => false, 'default' => 'None' ],
        'Reason'    => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'default' => 'NULL', 'nullable' => true ],
        'KillTime'  => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => 'NULL', 'nullable' => true ],
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
        'Time'      => [ 'columns' => [ 'Time' ] ],
        'UserID'    => [ 'columns' => [ 'UserID' ] ],
        'Status'    => [ 'columns' => [ 'Status' ] ],
    ];

    /**
     * isMFD Returns whether this torrent group has been MFD'd
     * @return bool                 True if true, false otherwise.
     *
     * @access public
     */
    public function isMFD(): bool {
        if ($this->Status === 'Warned' || $this->Status === 'Pending') {
            return true;
        }
        return false;
    }
}
