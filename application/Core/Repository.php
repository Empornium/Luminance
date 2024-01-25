<?php
namespace Luminance\Core;

use Luminance\Errors\SystemError;
use Luminance\Errors\InternalError;

abstract class Repository {

    protected $db;
    protected $orm;
    protected $cache;
    protected $master;
    protected $entityName;
    protected $useCache = true;
    protected $entityClass = null;

    public function __construct(Master $master) {
        $this->master = $master;
        $this->db = $this->master->db;
        $this->orm = $this->master->orm;
        $this->cache = $this->master->cache;
        $this->entityClass = "Luminance\\Entities\\{$this->entityName}";
    }

    public function loadFromCache($ID) {
        $key = $this->getCacheKey($ID);
        $casToken = 0;
        $values = $this->cache->getValue($key, false, $casToken);
        if (is_array($values)) {
            $entity = $this->entityClass::createFromDB($values, $casToken);
            return $entity;
        } else {
            return $values;
        }
    }

    public function get($sql, array $params = null, $cacheKey = null) {
        # Cache will return false if key does not exist
        $entityID = false;

        if ($this->useCache && !empty($cacheKey)) {
            $entityID = $this->cache->getValue($cacheKey);
        }

        if ($entityID === false) {
            $entityID = $this->orm->get($this->entityName, $sql, $params);

            # Don't cache null results.
            if ($this->useCache && !empty($cacheKey) && !empty($entityID)) {
                $this->cache->cacheValue($cacheKey, $entityID, 0);
            }
        }

        if (!empty($entityID)) {
            return $this->load($entityID);
        } else {
            return null;
        }
    }

    public function getOrCreate(array $where, array $creates, $cacheKey = null) {
        $sql = [];
        $params = [];
        foreach ($where as $column => $value) {
            $sql[] = "{$column} = ?";
            $params[] = $value;
        }
        $sql = implode(" AND ", $sql);
        $entity = $this->get($sql, $params, $cacheKey);
        if (empty($entity)) {
            $creates = array_merge($where, $creates);
            $entity = $this->orm->create($this->entityName, $creates);
        }
        return $entity;
    }
    public function updateOrCreate(array $where, array $updates, $cacheKey = null) {
        $sql = [];
        $params = [];
        foreach ($where as $column => $value) {
            $sql[] = "{$column} = ?";
            $params[] = $value;
        }
        $sql = implode(" AND ", $sql);
        $entity = $this->get($sql, $params, $cacheKey);
        if (empty($entity)) {
            $updates = array_merge($where, $updates);
            $entity = $this->orm->create($this->entityName, $updates);
        } else {
            foreach ($updates as $column => $value) {
                $entity->$column = $value;
            }
            $this->save($entity);
        }
        return $entity;
    }

    public function find($sql = null, array $params = null, $order = null, $limit = null, $cacheKey = null, $indexColumn = null) {
        # Cache handling, cache will return false if key does not exist
        if (!empty($cacheKey) && $this->useCache) {
            $entityIDs = $this->cache->getValue($cacheKey);
        } else {
            $entityIDs = false;
        }

        if ($entityIDs === false) {
            # Load IDs from DB
            $entityIDs = $this->orm->find($this->entityName, $sql, $params, $order, $limit);
            if (!empty($cacheKey) && $this->useCache) {
                $this->cache->cacheValue($cacheKey, $entityIDs, 900);
            }
        }

        if (!empty($entityIDs)) {
            $objects = [];
            # Load entities individually
            foreach ($entityIDs as $entityID) {
                $objects[] = $this->load($entityID);
            }
        } else {
            return [];
        }

        # Reindex resultset by abusing array_column a little
        return array_column($objects, null, $indexColumn);
    }

    public function findCount($sql, array $params = null, $order = null, $limit = null) {
        list($entityIDs, $count) = $this->orm->findCount($this->entityName, $sql, $params, $order, $limit);

        # Shortcut
        if ($count === 0) {
            return [null, 0];
        }

        $objects = [];
        # Load from cache
        foreach ($entityIDs as $entityID) {
            $objects[] = $this->load($entityID);
        }
        return [$objects, $count];
    }

    public function disableCache() {
        $this->useCache = false;
    }

    public function enableCache() {
        $this->useCache = true;
    }

    public function saveToCache($entity) {
        # Check we're dealing with the right kind of object
        if (!($entity instanceof $this->entityClass)) {
            throw new InternalError("Attempt to cache foreign entity.");
        }
        if (!$entity->existsInDB()) {
            throw new InternalError("Attempt to cache non-saved entity.");
        }
        if ($this->useCache) {
            $key = $this->getCacheKey(implode('_', $entity->getPKeyValues()));
            $values = $entity->getSavedValues();
            if (defined($this->entityClass.'::CACHE_EXPIRATION')) {
                $cacheExpiration = $entity::CACHE_EXPIRATION;
            } else {
                $cacheExpiration = 900;
            }
            $this->cache->cacheValue($key, $values, $cacheExpiration, $entity->casToken);
        }
    }

    public function load($ID, $postLoad = true) {
        if ($ID instanceof $this->entityClass) {
            $entity = $ID;
        } elseif (is_null($ID) || is_integer_string($ID) && $ID === 0) {
            return null;
        } else {
            if ($this->useCache) {
                $entity = $this->loadFromCache($ID);
            } else {
                $entity = false;
            }

            if ($entity === false) {
                $entity = $this->orm->load($this->entityName, $ID);
                if ($this->useCache) {
                    if ($entity instanceof Entity) {
                        $this->saveToCache($entity);
                    } else {
                        $key = $this->getCacheKey($ID);
                        $this->cache->cacheValue($key, null, 0);
                    }
                }
            }
        }
        if ($postLoad === true && $entity instanceof Entity) {
            $entity = $this->postLoad($entity->getPKeyValues(), $entity);
        }

        return $entity;
    }

    protected function postLoad($ID, $entity) {
        return $entity; # Override where needed
    }

    #TODO make allowUpdate automatic based on pkey structure
    public function save(Entity &$entity, $allowUpdate = false) {
        # Check we're dealing with the right kind of object
        if (!($entity instanceof $this->entityClass)) {
            throw new InternalError("Attempt to save foreign entity.");
        }

        # First check if there's anything to actually save!
        if (!$entity->needsSaving()) {
            return;
        }

        # Call the ORM to do the save
        try {
            $entity = $this->orm->save($entity, $allowUpdate);
        } catch (SystemError $e) {
            $message  = $e->getMessage();
            $message .= $entity->printState();
            throw new SystemError($message);
        }

        # Ensure we update the cache
        if ($this->useCache && $entity->existsInDB()) {
            if (!empty($entity->casToken)) {
                $this->saveToCache($entity);
            } else {
                $this->uncache(implode('_', $entity->getPKeyValues()));
            }
        }
    }

    public function delete(Entity $entity) {
        if ($this->useCache && $entity->existsInDB()) {
            $this->uncache(implode('_', $entity->getPKeyValues()));
        }
        $this->orm->delete($entity);
    }

    public function getCacheKey($ID) {
        if (is_array($ID)) {
            $ID = implode('_', $ID);
        }
        $key = "_entity_{$this->entityName}_{$ID}";
        return $key;
    }

    public function uncache($ID) {
        if ($ID instanceof Entity) {
            $ID = implode('_', $ID->getPKeyValues());
        }
        $key = $this->getCacheKey($ID);
        $this->cache->deleteValue($key);
    }
}
