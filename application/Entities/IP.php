<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;
use Luminance\Errors\InternalError;
use IPLib\Range\RangeInterface;
use IPLib\Factory;

class IP extends Entity {

    public static $table = 'ips';

    public static $properties = [
        'ID'            => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'LastUserID'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'ActingUserID'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'StartAddress'  => [ 'type' => 'str', 'sqltype' => 'VARBINARY(16)', 'nullable' => false ],
        'EndAddress'    => [ 'type' => 'str', 'sqltype' => 'VARBINARY(16)', 'nullable' => true ],
        'Banned'        => [ 'type' => 'bool',      'nullable' => false ],
        'BannedUntil'   => [ 'type' => 'timestamp', 'nullable' => true ],
        'Bans'          => [ 'type' => 'int', 'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => false ],
        'Reason'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true ],
    ];

    public static $indexes = [
        'StartAddress' => [ 'columns' => [ 'StartAddress' ] ],
        'EndAddress'   => [ 'columns' => [ 'EndAddress' ] ],
        'LastUserID'   => [ 'columns' => [ 'LastUserID' ] ],
        'ActingUserID' => [ 'columns' => [ 'ActingUserID' ] ],
        'BannedUntil'  => [ 'columns' => [ 'BannedUntil' ] ],
        'Banned'       => [ 'columns' => [ 'Banned' ] ],
    ];

    private $range;

    public function __construct($address = null, $netmask = null) {
        parent::__construct();
        if ($address) {
            $netmask = (!is_null($netmask)) ? "/{$netmask}" : "";
            $this->set_cidr("{$address}{$netmask}");

            if (is_null($this->range)) {
                throw new InternalError("Invalid IP {$address}");
            }
        }
    }

    public function __toString() {
        return $this->get_cidr();
    }

    public function set_cidr($IP) {
        $this->range = Factory::rangeFromString($IP);
        if (is_null($this->range)) return false;
        $this->StartAddress = inet_pton($this->range->getStartAddress());
        if ($this->range->getStartAddress() !== $this->range->getEndAddress()) {
            $this->EndAddress = inet_pton($this->range->getEndAddress());
        }
    }

    public function get_cidr() {
        if (!$this->range) {
            if ($this->EndAddress) {
                $this->range = Factory::rangeFromBoundaries(inet_ntop($this->StartAddress), inet_ntop($this->EndAddress));
            } else {
                $this->range = Factory::rangeFromString(inet_ntop($this->StartAddress));
            }
        }
        return "{$this->range}";
    }

    public function get_range() {
        $this->get_cidr();
        return $this->range;
    }

    public function set_range(RangeInterface $range) {
        $this->range = $range;
    }

    public function is_ipv6() {
        return (strlen($this->StartAddress) == 16);
    }

    public function match(IP $IP) {
        $this->get_cidr();
        if ($this->range->containsRange($IP->get_range())) {
            return true;
        }
        return false;
    }
}
