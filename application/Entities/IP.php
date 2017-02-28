<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class IP extends Entity {

    static $table = 'ips';

    static $properties = [
        'ID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'LastUserID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'ActingUserID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'Address' => ['type' => 'str', 'sqltype' => 'VARBINARY(16)', 'nullable' => false ],
        'Netmask' => [ 'type' => 'int', 'sqltype' => 'TINYINT(3) UNSIGNED', 'nullable' => true ],
        'LoginAttempts' => [ 'type' => 'int', 'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => false ],
        'LastAttempt' => [ 'type' => 'timestamp', 'nullable' => true ],
        'Banned' => [ 'type' => 'bool', 'nullable' => false ],
        'BannedUntil' => [ 'type' => 'timestamp', 'nullable' => true ],
        'Bans' => [ 'type' => 'int', 'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => false ],
        'Reason' => ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
    ];

    static $indexes = [
        'Address' => [ 'columns' => [ 'Address' ] ],
        'Netmask' => [ 'columns' => [ 'Netmask' ] ],
        'LastUserID' => [ 'columns' => [ 'LastUserID' ] ],
        'ActingUserID' => [ 'columns' => [ 'ActingUserID' ] ],
    ];

    public function get_ip() {
        return inet_ntop($this->Address);
    }

    public function set_ip($IP) {
        $this->Address = inet_pton($IP);
    }

    public function is_ipv6() {
        return (strlen($this->Address) == 16);
    }

    public function match($IP) {
        $BinaryIP = inet_pton($IP);
        if (is_null($this->Netmask)) {
            return ($BinaryIP === $this->Address);
        } else {
            # TODO
            throw new InternalError('TODO');
        }
    }

}
