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

    protected function post_load($ID, $IP) {
        if (!$IP) return null;
        $IP->get_cidr();
        $IP->geoip = geoip((string)$IP);
        return $IP;
    }

    public function get_or_new($address, $netmask = null) {
        $binaryAddress = @inet_pton($address);
        if ($netmask) {
            $IP = new IP($address, $netmask);
            $IP = $this->get('`StartAddress` = ? AND `EndAddress` = ?', [$IP->StartAddress, $IP->EndAddress]);
        } else {
            $IP = $this->get('`StartAddress` = ? AND `EndAddress` IS NULL', [$binaryAddress]);
        }

        if (empty($IP)) {
            $IP = $this->newIP($address, $netmask);
        }

        return $IP;
    }

    public function newIP($address, $netmask = null) {
        try {
            $IP = new IP($address, $netmask);
            $IP->Banned = false;
            $IP->Bans = 0;
            $this->save($IP);
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

    public function ban($address, $reason, $hours = null) {
        if ($address instanceof IP) {
            $range = $address->get_range();
            if (empty($range)) return null;
            if ($range->getRangeType() !== Type::T_PUBLIC) {
                $type = Type::getName($range->getRangeType());
                $this->master->flasher->error("{$type} {$address} cannot be banned, public addresses only");
                return null;
            }
            $IP = $address;
        } else {
            $address = (string) $address;
            $range = Factory::rangeFromString($address);
            if (empty($range)) return null;
            if ($range->getRangeType() !== Type::T_PUBLIC) {
                $type = Type::getName($range->getRangeType());
                $this->master->flasher->error("{$type} {$address} cannot be banned, public addresses only");
                return null;
            }
            if (strpos($address, '/') !== false) {
                list($address, $netmask) = explode('/', $address);
            } else {
                $netmask = null;
            }
            $IP = $this->get_or_new($address, $netmask);
        }
        $now = new \DateTime();

        // Extend existing ban
        if ($IP->BannedUntil > $now && $IP->Banned == true) {
            $IP->Reason = $IP->Reason . ' and ' . $reason;
            if (!is_null($hours)) {
                $IP->BannedUntil->add(new \DateInterval("PT{$hours}H"));
            }

        // Create new ban
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

    public function check_banned($IP) {

        if (!$IP instanceof IP) {
            return false;
        }

        $Banned = $this->search($IP);
        $now = new \DateTime();

        foreach ($Banned as $Ban) {
            // Does the ban really match this IP?
            if (!$IP->match($Ban)) continue;

            // Was it a timed ban that has expired?
            if (!is_null($Ban->BannedUntil) && $Ban->BannedUntil < $now) {
                // Update banned status and return
                $Ban->Banned = false;
                $this->save($Ban);
                continue;

            // Is it a currently active timed ban?
            } elseif (!is_null($IP->BannedUntil) && $IP->BannedUntil > $now) {
                $diff = time_diff($IP->BannedUntil);
                throw new ForbiddenError("Your IP is banned for another {$diff}");

            // Must be a perma-ban then
            } else {
                throw new ForbiddenError("Your IP has been banned");
            }
        }
    }
}
