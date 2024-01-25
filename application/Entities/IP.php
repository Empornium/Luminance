<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;
use Luminance\Errors\InternalError;
use IPLib\Range\RangeInterface;
use IPLib\Factory;

/**
 * IP Entity representing rows from the `ips` DB table.
 */
class IP extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'ips';

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

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Range'        => [ 'columns' => [ 'StartAddress', 'EndAddress' ], 'type' => 'unique' ],
        'StartAddress' => [ 'columns' => [ 'StartAddress'               ] ],
        'EndAddress'   => [ 'columns' => [ 'EndAddress'                 ] ],
        'LastUserID'   => [ 'columns' => [ 'LastUserID'                 ] ],
        'ActingUserID' => [ 'columns' => [ 'ActingUserID'               ] ],
        'BannedUntil'  => [ 'columns' => [ 'BannedUntil'                ] ],
        'Banned'       => [ 'columns' => [ 'Banned'                     ] ],
    ];

    /**
     * $range IPLib object representing this IP as a range.
     * @var RangeInterface
     *
     * @access private
     */
    private $range;

    /**
     * fromCIDR takes a CIDR and returns an IP object representing it
     * @param string|null $address IP address represented as a string or null for empty object.
     * @param string|null $cidr    CIDR netmask as a string or null for single IP.
     * @return IP                  IP object representing the CIDR or address.
     *
     * @throws InternalError If IP held in $address is invlaid.
     *
     * @access public
     */
    public static function fromCIDR($address = null, $cidr = null) {
        $ip = new self();
        $success = false;

        if (is_string($address) === true) {
            $address .= (!is_null($cidr)) ? "/{$cidr}" : "";
            $success = $ip->setCIDR("{$address}");
        } elseif ($address instanceof IP) {
            $success = $ip->setCIDR($address->getCIDR());
        }

        if ($success === false) {
            throw new InternalError("Invalid IP {$address}");
        }

        return $ip;
    }

    /**
     * convertRange takes a CIDR and returns the start and end addresses.
     * @param string $address String representation of an IP.
     * @param string $netmask CIDR netmask as a string or null for single IP.
     *
     * @access public
     */
    public static function convertRange($address, $netmask) {
        $netmask = (!is_null($netmask)) ? "/{$netmask}" : "";
        $range = Factory::rangeFromString("{$address}{$netmask}");
        if (is_null($range)) return false;
        $startAddress = inet_pton($range->getStartAddress());
        if (!($range->getStartAddress() === $range->getEndAddress())) {
            $endAddress = inet_pton($range->getEndAddress());
        } else {
            $endAddress = null;
        }

        return [$startAddress, $endAddress];
    }

    /**
     * setCIDR Sets the Address and CIDR netmask on this object.
     * @param string $IP String representation of an IP or CIDR range.
     * @return bool true on success false otherwise
     *
     * @access public
     */
    public function setCIDR($IP): bool {
        $this->range = Factory::rangeFromString($IP);
        if (is_null($this->range)) return false;
        $this->StartAddress = inet_pton($this->range->getStartAddress());
        if (!($this->range->getStartAddress() === $this->range->getEndAddress())) {
            $this->EndAddress = inet_pton($this->range->getEndAddress());
        }
        return true;
    }

    /**
     * getCIDR Returns the Address and CIDR netmask of this IP object.
     * @return string String containing Address and CIDR.
     *
     * @access public
     */
    public function getCIDR() {
        if (!$this->range) {
            $start = Factory::addressFromBytes($this->unpack($this->StartAddress));
            $end   = Factory::addressFromBytes($this->unpack($this->EndAddress));
            $this->range = Factory::rangeFromBoundaries($start, $end);
        }
        return "{$this->range}";
    }

    /**
     * unpack binary address to array, while making sure the offset starts at 0.
     * @param string $binaryAddress binary address to unpack
     * @return array
     */
    public function unpack($binaryAddress): array {
        if (is_null($binaryAddress)) {
            return [];
        }
        return array_values(unpack('C*', $binaryAddress));
    }

    /**
     * getRange Returns the IPLib range object representing this IP.
     * @return RangeInterface IPLib range object for this IP.
     *
     * @access public
     */
    public function getRange() {
        $this->getCIDR();
        return $this->range;
    }

    /**
     * setRange Sets the IPLib range object representing this IP.
     * @param RangeInterface $range IPLib range object for this IP.
     *
     * @access public
     */
    public function setRange(RangeInterface $range) {
        $this->range = $range;
    }

    /**
     * isIPv4 Returns whether or not this is an IPv4 IP.
     * @return bool True if this is an IPv4 IP, false otherwise.
     *
     * @access public
     */
    public function isIPv4(): bool {
        return (strlen($this->StartAddress) === 4);
    }

    /**
     * isIPv6 Returns whether or not this is an IPv6 IP.
     * @return bool True if this is an IPv6 IP, false otherwise.
     *
     * @access public
     */
    public function isIPv6(): bool {
        return (strlen($this->StartAddress) === 16);
    }

    /**
     * isRange Returns whether or not this is an IP range.
     * @return bool True if this is an IP range, false otherwise.
     *
     * @access public
     */
    public function isRange() {
        $this->getCIDR();
        if ($this->range instanceof RangeInterface) {
            return true;
        }
        return false;
    }

    /**
     * match Compares this object to another IP object and returns the result.
     * @param  IP     $IP IP object to be compared.
     * @return bool       True if this IP contains the passed object, false otherwise.
     *
     * @access public
     */
    public function match(IP $IP) {
        $this->getCIDR();
        if (!($this->range instanceof RangeInterface)) {
            return false;
        }
        if (!($IP instanceof IP)) {
            return false;
        }
        if ($this->range->containsRange($IP->getRange())) {
            return true;
        }
        if ($IP->isRange() === true) {
            if ($IP->range->containsRange($this->getRange())) {
                return true;
            }
        }
        return false;
    }

    /**
     * __toString object magic function
     * @return string string representation of this IP object.
     *
     * @access public
     */
    public function __toString() {
        return $this->getCIDR();
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
            case 'geoip':
                return true;

            case 'network':
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
            case 'geoip':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->geolite2s->resolve($this));
                }
                break;

            case 'network':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->geolite2ASNs->resolve($this));
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
