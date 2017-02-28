<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\IP;

class IPRepository extends Repository {

    protected $entityName = 'IP';

    public function get_or_new($Address, $Netmask = null) {
        $BinaryAddress = inet_pton($Address);
        $IP = $this->get('`Address` = ? AND `Netmask` <=> ?', [$BinaryAddress, $Netmask]);
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

}
