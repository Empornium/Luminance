<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

use Luminance\Entities\User;
use Luminance\Entities\RequestFlood;

class RequestFloodRepository extends Repository {

    protected $entityName = 'RequestFlood';

    public function getOrNew($type, $ip, $user) {
        $flood = $this->get('`Type` = ? AND `IPID` = ?', [$type, $ip->ID], "flood_{$type}_{$ip}");
        if (!($flood instanceof RequestFlood)) {
            $flood = new RequestFlood();
            $flood->Type = $type;
            $flood->IPID = $ip->ID;
            $flood->LastRequest = new \DateTime();
            $flood->Requests = 0;
            if ($user instanceof User) {
                $flood->UserID = $user->ID;
            }
            $this->save($flood);
            $this->cache->deleteValue("flood_{$type}_{$ip}");
        }
        return $flood;
    }

    /**
     * Delete RequestFlood entity from cache
     * @param int|RequestFlood $flood Request Flood to uncache
     *
     */
    public function uncache($flood) {
        $flood = $this->load($flood);
        parent::uncache($flood);
        $this->cache->deleteValue("_query_RequestFlood_{$flood->Type}_{$flood->ip}");
    }
}
