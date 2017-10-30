<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\IP;

class IPRepository extends Repository {

    protected $entityName = 'IP';

    public function get_or_new($Address, $Netmask = null) {
        $BinaryAddress = @inet_pton($Address);
        $IP = $this->get_by_address($Address, $Netmask);
        if (!$IP) {
            $IP = new IP();
            $IP->Address = $BinaryAddress;
            $IP->Netmask = $Netmask;
            $IP->LoginAttempts = 0;
            $IP->Banned = false;
            $IP->Bans = 0;
            $this->save($IP);
        }
        return $IP;
    }

    public function get_by_address($Address, $Netmask = null) {
        $BinaryAddress = @inet_pton($Address);
        // IP is invalid?
        if ($BinaryAddress === false) return false;
        $IP = $this->get('`Address` = ? AND `Netmask` <=> ?', [$BinaryAddress, $Netmask]);
        return $IP;
    }

    public function ban($address, $reason, $hours = 6) {
        $IP = $this->get_or_new($address);
        $now = new \DateTime();
        if ($IP->BannedUntil < $now) {
            $IP->Reason = $IP->Reason . ' and ' . $reason;
            $IP->BannedUntil = time_plus(60 * 60 * $hours);
        } else {
            $IP->Reason = $reason;
        }
        $IP->Banned = true;

        $this->save($IP);
        return $IP;
    }

    public function unban(IP $IP)
    {
        $IP->BannedUntil = null;
        $IP->Banned = false;
        $this->save($IP);
    }

    public function is_banned($address) {
        $IP = $this->get_by_address($address);
        $now = new \DateTime();
        if (!is_null($IP->BannedUntil) && $IP->BannedUntil > $now) {
          return true;
        }
        return false;
    }

}
