<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Client Entity representing rows from the `clients` DB table.
 */
class Client extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'clients';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'                => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'CID'               => [ 'type' => 'str', 'sqltype' => 'VARBINARY(8)', 'nullable' => false ],
        'UserID'            => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true  ],
        'IPID'              => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Created'           => [ 'type' => 'timestamp', 'nullable' => true ],
        'Updated'           => [ 'type' => 'timestamp', 'nullable' => true ],
        'ClientUserAgentID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'ClientAcceptID'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'ClientScreenID'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'TimezoneOffset'    => [ 'type' => 'int', 'sqltype' => 'TINYINT',      'nullable' => true ], # stored as minutes divided by 15 (signed!)
        'TLSVersion'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(64)',  'nullable' => true ],
        'HTTPVersion'       => [ 'type' => 'str', 'sqltype' => 'VARCHAR(64)',  'nullable' => true ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'CID'               => [ 'columns' => [ 'CID' ] ],
        'UserID'            => [ 'columns' => [ 'UserID' ] ],
        'IPID'              => [ 'columns' => [ 'IPID' ] ],
        'Created'           => [ 'columns' => [ 'Created' ] ],
        'Updated'           => [ 'columns' => [ 'Updated' ] ],
        'ClientUserAgentID' => [ 'columns' => [ 'ClientUserAgentID' ] ],
        'ClientAcceptID'    => [ 'columns' => [ 'ClientAcceptID' ] ],
        'ClientScreenID'    => [ 'columns' => [ 'ClientScreenID' ] ],
        'TimezoneOffset'    => [ 'columns' => [ 'TimezoneOffset' ] ],
        'TLSVersion'        => [ 'columns' => [ 'TLSVersion' ] ],
        'HTTPVersion'       => [ 'columns' => [ 'TLSVersion' ] ],
    ];

    /**
     * matchCID checks whether a ClientID string matches this client object or not.
     * @param  string $matchCID Hexadecimal representation of the CID to match.
     * @return bool             True if CID matches, false otherwise.
     *
     * @access public
     */
    public function matchCID(string $matchCID) {
        $realCID = hex2bin($this->CID);
        $result = (
            strlen($matchCID) === 8 &&
            $matchCID === $realCID
        );
        return $result;
    }
}
