<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\RequestFlood;

class RequestFloodRepository extends Repository {

    protected $entityName = 'RequestFlood';

    public function get_or_new($type, $ip, $user) {
        $flood = $this->get('`Type` = ? AND `IPID` = ?', [$type, $ip->ID]);
        if (!$flood) {
            $flood = new RequestFlood();
            $flood->Type = $type;
            $flood->IPID = $ip->ID;
            $flood->LastRequest = new \DateTime();
            $flood->Reqests = 0;
            if ($user) {
                $flood->UserID = $user->ID;
            }
            $this->save($flood);
        }
        return $flood;
    }
}
