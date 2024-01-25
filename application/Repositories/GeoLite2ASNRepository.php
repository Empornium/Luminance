<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

use Luminance\Entities\IP;

class GeoLite2ASNRepository extends Repository {

    protected $entityName = 'GeoLite2ASN';

    public function resolve($IP) {
        if ($IP instanceof IP) {
            $IP = (string) $IP;
        }

        $key = "_query_{$this->entityName}_{$IP}";

        if ($this->useCache) {
            $entityID = $this->cache->getValue($key);
        } else {
            $entityID = false;
        }

        if ($entityID === false) {
            $cls = "Luminance\\Entities\\{$this->entityName}";
            $table = $cls::$table;
            $entityID = $this->db->rawQuery(
                "SELECT ID FROM (
                     SELECT * FROM {$table}
                      WHERE INET6_ATON(?) >= StartAddress
                   ORDER BY StartAddress DESC
                      LIMIT 1
                 ) AS start
                 WHERE INET6_ATON(?) <= start.EndAddress",
                [$IP, $IP]
            )->fetchColumn();

            if ($entityID === false) {
                $entityID = null;
            }

            if ($this->useCache) {
                $this->cache->cacheValue($key, $entityID, 900);
            }
        }

        if (!empty($entityID)) {
            return $this->load($entityID);
        } else {
            return null;
        }
    }
}
