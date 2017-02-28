<?php
namespace Luminance\Core;

use Luminance\Core\Master;

use Luminance\Errors\InternalError;

abstract class Repository {

    protected $db;
    protected $orm;
    protected $cache;
    protected $entityName;
    protected $use_cache = true;
    protected $internal_cache = [];

    public function __construct(Master $master) {
        $this->master = $master;
        $this->db = $this->master->db;
        $this->orm = $this->master->orm;
        $this->cache = $this->master->cache;
    }

    public function load_from_cache($ID) {
        $key = $this->get_cache_key($ID);
        $values = $this->cache->get_value($key);
        if (is_array($values)) {
            $cls = "Luminance\\Entities\\{$this->entityName}";
            $entity = $cls::create_from_db($values);
            return $entity;
        } else {
            return null;
        }
    }

    public function get($sql, $params = null) {
        $object = $this->orm->get($this->entityName, $sql, $params);
        return $object;
    }

    public function find($sql, $params = null) {
        $objects = $this->orm->find($this->entityName, $sql, $params);
        return $objects;
    }

    public function find_count($sql, $params = null) {
        $result = $this->orm->find_count($this->entityName, $sql, $params);
        return $result;
    }

    public function save_to_cache($entity) {
        if (!$entity->exists_in_db()) {
            throw new InternalError("Attempt to cache non-saved entity.");
        }
        $key = $this->get_cache_key($entity->get_pkey_value());
        $values = $entity->get_saved_values();
        $this->cache->cache_value($key, $values, 0);
    }

    public function load($ID, $post_load = true) {
        if (array_key_exists($ID, $this->internal_cache)) {
            return $this->internal_cache[$ID];
        }
        if ($this->use_cache) {
            $entity = $this->load_from_cache($ID);
        }
        if (!$entity) {
            $entity = $this->orm->load($this->entityName, $ID);
            if ($this->use_cache && $entity) {
                $this->save_to_cache($entity);
            }
        }
        if ($post_load) {
            $entity = $this->post_load($ID, $entity);
        }
        if ($entity) {
            $this->internal_cache[$ID] = $entity;
        }
        return $entity;
    }

    protected function post_load($ID, $entity) {
        return $entity; # Override where needed
    }

    public function save(Entity $entity) {
        if ($this->use_cache && $entity->exists_in_db()) {
            $this->uncache($entity->get_pkey_value());
        }
        $this->orm->save($entity);
    }

    public function get_cache_key($ID) {
        $key = "_entity_{$this->entityName}_{$ID}";
        return $key;
    }

    public function uncache($ID) {
        $key = $this->get_cache_key($ID);
        $this->cache->delete_value($key);
        if (array_key_exists($ID, $this->internal_cache)) {
            unset($this->internal_cache[$ID]);
        }
    }

}
