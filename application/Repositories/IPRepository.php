<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

use Luminance\Entities\IP;

use Luminance\Errors\ForbiddenError;
use Luminance\Errors\InternalError;

use IPLib\Factory;
use IPLib\Range\Type;

class IPRepository extends Repository {

    protected $entityName = 'IP';

    protected function postLoad($ID, $IP) {
        if (!($IP instanceof IP)) return null;
        $IP->getCIDR();
        return $IP;
    }

    public function getOrNew($address, $netmask = null) {
        if (!empty($netmask)) {
            list($startAddress, $endAddress) = IP::convertRange($address, $netmask);
            $IP = $this->get(
                '`StartAddress` = ? AND `EndAddress` = ?',
                [$startAddress, $endAddress],
                "ip_{$address}/{$netmask}"
            );
        } else {
            $IP = $this->get(
                '`StartAddress` = INET6_ATON(?) AND `EndAddress` IS NULL',
                [$address],
                "ip_{$address}"
            );
        }

        if (empty($IP) && !empty($address)) {
            $IP = $this->newIP($address, $netmask);
        }

        return $IP;
    }

    public function newIP($address, $netmask = null) {
        try {
            $IP = IP::fromCIDR($address, $netmask);
            $IP->Banned = false;
            $IP->Bans = 0;
            $this->save($IP);
            if (!empty($netmask)) {
                $this->cache->deleteValue("ip_{$address}/{$netmask}");
            } else {
                $this->cache->deleteValue("ip_{$address}");
            }
            return $IP;
        } catch (InternalError $e) {
            return null;
        }
    }

    public function search(IP $IP) {
        $sql = '((`StartAddress` <= ? AND `EndAddress` >= ?) OR (`StartAddress` = ? AND `EndAddress` IS NULL)) AND Banned = true';
        $parameters[] = $IP->StartAddress;
        if ($IP->EndAddress) {
            $parameters[] = $IP->EndAddress;
            $parameters[] = $IP->StartAddress;
        } else {
            $parameters[] = $IP->StartAddress;
            $parameters[] = $IP->StartAddress;
        }
        $search = $this->find($sql, $parameters);

        return $search;
    }

    public function ban($address, $reason = null, $hours = null) {
        if ($address instanceof IP) {
            $range = $address->getRange();
            if (empty($range)) {
                return null;
            }
            if (!($range->getRangeType() === Type::T_PUBLIC)) {
                $type = Type::getName($range->getRangeType());
                $this->master->flasher->error("{$type} {$address} cannot be banned, public addresses only");
                return null;
            }
            $IP = $address;
        } else {
            $address = (string) $address;
            $range = Factory::rangeFromString($address);
            if (empty($range)) {
                return null;
            }
            if (!($range->getRangeType() === Type::T_PUBLIC)) {
                $type = Type::getName($range->getRangeType());
                $this->master->flasher->error("{$type} {$address} cannot be banned, public addresses only");
                return null;
            }
            if (!(strpos($address, '/') === false)) {
                list($address, $netmask) = explode('/', $address);
            } else {
                $netmask = null;
            }
            $IP = $this->getOrNew($address, $netmask);
        }
        $now = new \DateTime();

        # Extend existing ban
        if ($IP->BannedUntil > $now && $IP->Banned === true && !empty($reason)) {
            $IP->Reason = $IP->Reason . ' and ' . $reason;
            if (!is_null($hours)) {
                $IP->BannedUntil->add(new \DateInterval("PT{$hours}H"));
            }

        # Create new ban
        } else {
            $IP->Reason = $reason;
            if (!is_null($hours)) {
                $IP->BannedUntil = $now->add(new \DateInterval("PT{$hours}H"));
            }
            $IP->Bans++;
        }
        $IP->Banned = true;

        if ($this->master->request->user) {
            $IP->ActingUserID = $this->master->request->user->ID;
        }

        $this->save($IP);
        return $IP;
    }

    public function unban(IP $IP) {
        $IP->BannedUntil = null;
        $IP->Banned = false;
        $this->save($IP);
    }

    public function checkBanned($IP) {
        if (!$IP instanceof IP) {
            return false;
        }

        $banned = $this->search($IP);
        $now = new \DateTime();

        foreach ($banned as $ban) {
            # Does the ban really match this IP?
            if (!$IP->match($ban)) continue;

            # Was it a timed ban that has expired?
            if (!is_null($ban->BannedUntil) && $ban->BannedUntil < $now) {
                # Update banned status and return
                $ban->Banned = false;
                $this->save($ban);
                continue;

            # Is it a currently active timed ban?
            } elseif (!is_null($IP->BannedUntil) && $IP->BannedUntil > $now) {
                $diff = time_diff($IP->BannedUntil);
                throw new ForbiddenError("Your IP is banned for another {$diff}");

            # Must be a perma-ban then
            } else {
                throw new ForbiddenError("Your IP has been banned");
            }
        }
    }

    /**
     * Delete IP entity from cache
     * @param int|IP $ip ip to uncache
     *
     */
    public function uncache($ip) {
        $ip = $this->load($ip);
        parent::uncache($ip);
        $this->cache->deleteValue("_query_IP_{$ip}");
    }
}
