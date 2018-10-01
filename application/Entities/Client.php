<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Client extends Entity {

    public static $table = 'clients';

    public static $properties = [
        'ID'                => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'CID'               => [ 'type' => 'str', 'sqltype' => 'VARBINARY(8)', 'nullable' => false ],
        'IPID'              => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'Created'           => [ 'type' => 'timestamp', 'nullable' => true ],
        'Updated'           => [ 'type' => 'timestamp', 'nullable' => true ],
        'ClientUserAgentID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'ClientAcceptID'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'ClientScreenID'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'TimezoneOffset'    => [ 'type' => 'int', 'sqltype' => 'TINYINT',      'nullable' => true ], # stored as minutes divided by 15 (signed!)
        'TLSVersion'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(64)',  'nullable' => true ],
    ];

    public static $indexes = [
        'CID'               => [ 'columns' => [ 'CID' ] ],
        'IPID'              => [ 'columns' => [ 'IPID' ] ],
        'Created'           => [ 'columns' => [ 'Created' ] ],
        'Updated'           => [ 'columns' => [ 'Updated' ] ],
        'ClientUserAgentID' => [ 'columns' => [ 'ClientUserAgentID' ] ],
        'ClientAcceptID'    => [ 'columns' => [ 'ClientAcceptID' ] ],
        'ClientScreenID'    => [ 'columns' => [ 'ClientScreenID' ] ],
        'TimezoneOffset'    => [ 'columns' => [ 'TimezoneOffset' ] ],
        'TLSVersion'        => [ 'columns' => [ 'TLSVersion' ] ],
    ];

    public function matchCID($matchCID) {
        $realCID = hex2bin($this->CID);
        $result = (
            strlen($matchCID) == 8 &&
            $matchCID === $realCID
        );
        return $result;
    }
}
