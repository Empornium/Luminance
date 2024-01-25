<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\Permission;

class PermissionRepository extends Repository {

    protected $entityName = 'Permission';

    private $classes;
    private $groups;
    private $minUserLevel;
    private $minUserClassID;
    private $minStaffLevel;

    public function getLegacyPermission($ID) {
        $permission = $this->load($ID);
        if ($permission instanceof Permission) {
            return $permission->getLegacy();
        } else {
            $p = [];
            $p['Class'] = null;
            $p['Permissions'] = [];
            $p['MaxSigLength'] = 0;
            $p['MaxAvatarWidth'] = 0;
            $p['MaxAvatarHeight'] = 0;
            $p['DisplayStaff'] = '0';
            return $p;
        }
    }

    public function getMinUserLevel() {
        if (!$this->minUserLevel) {
            $minUserLevel = $this->cache->getValue('min_user_level');
            if ($minUserLevel===false) {
                $minUserLevel = $this->db->rawQuery("SELECT MIN(Level) FROM permissions WHERE isAutoPromote='1' AND IsUserClass='1'")->fetchColumn();
                $this->cache->cacheValue('min_user_level', $minUserLevel);
            }
            $this->minUserLevel = $minUserLevel;
        }
        return $this->minUserLevel;
    }

    public function getMinUserClassID() {
        if (!$this->minUserClassID) {
            $minUserClassID = $this->cache->getValue('min_user_classid');
            if ($minUserClassID===false) {
                $minUserClassID = $this->db->rawQuery("SELECT ID FROM permissions WHERE isAutoPromote='1' AND IsUserClass='1' ORDER BY Level ASC LIMIT 1")->fetchColumn();
                $this->cache->cacheValue('min_user_classid', $minUserClassID);
            }
            $this->minUserClassID = $minUserClassID;
        }
        return $this->minUserClassID;
    }

    public function getMinStaffLevel() {
        if (!$this->minStaffLevel) {
            $minStaffLevel = $this->cache->getValue('min_staff_level');
            if ($minStaffLevel===false) {
                $minStaffLevel = $this->db->rawQuery("SELECT MIN(Level) FROM permissions WHERE DisplayStaff='1'")->fetchColumn();
                $this->cache->cacheValue('min_staff_level', $minStaffLevel);
            }
            $this->minStaffLevel = $minStaffLevel;
        }
        return $this->minStaffLevel;
    }

    public function getMinClassPermission($perm = "") {
        $classes = $this->db->rawQuery("SELECT ID, `Values` FROM permissions ORDER BY Level")->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_GROUP);
        foreach ($classes as $class => $permissions) {
            if (is_null($permissions)) continue;
            $permissions = unserialize($permissions[0]);
            if (!array_key_exists($perm, $permissions)) continue;
            if ($permissions[$perm] === 1) return $this->load($class);
        }
        return false;
    }

    public function getClassByLevel($level) {
        $classLevels = $this->getLevels();
        foreach ($classLevels as $classLevel => $class) {
            if ($classLevel >= $level) {
                return $class;
            }
        }
    }

    public function getClassByID($ID) {
        return $this->getClasses()[$ID];
    }

    public function getClassesByIDs($IDs = []) {
        $return = [];
        foreach ($IDs as $ID) {
            if (!is_integer_string($ID)) {
                continue;
            }
            $return[] = $this->getClassByID($ID);
        }
        return $return;
    }

    public function isStaff($ID) {
        $permission = $this->load($ID);
        if ($permission->DisplayStaff === '1') {
            return true;
        }

        return false;
    }

    public function getClasses() {
        if (!$this->classes) {
            $classes = $this->cache->getValue('classes');
            if ($classes===false) {
                $classes = $this->db->rawQuery(
                    "SELECT ID,
                            Name,
                            Description,
                            Level,
                            Color,
                            IsUserClass,
                            MaxAvatarWidth,
                            MaxAvatarHeight,
                            LOWER(REPLACE(Name,' ','')) AS ShortName
                       FROM permissions
                   ORDER BY IsUserClass, Level"
                )->fetchAll(\PDO::FETCH_ASSOC);
                $this->cache->cacheValue('classes', $classes);
            }
            $this->classes = $classes;
        }

        # Reindex resultset by abusing array_column a little
        return array_column($this->classes, null, 'ID');
    }

    public function getGroups() {
        if (!$this->groups) {
            $groups = $this->cache->getValue('groups');
            if ($groups===false) {
                $groups = $this->db->rawQuery(
                    "SELECT ID,
                            Name,
                            Description,
                            Level,
                            Color,
                            IsUserClass,
                            MaxAvatarWidth,
                            MaxAvatarHeight,
                            LOWER(REPLACE(Name,' ','')) AS ShortName
                       FROM permissions
                      WHERE IsUserClass = '0'
                   ORDER BY Level"
                )->fetchAll(\PDO::FETCH_ASSOC);
                $this->cache->cacheValue('groups', $groups);
            }
            $this->groups = $groups;
        }

        # Reindex resultset by abusing array_column a little
        return array_column($this->groups, null, 'ID');
    }

    public function getLevels() {
        # Reindex classes by abusing array_column a little
        return array_column($this->getCLasses(), null, 'Level');
    }

    public function getShortNames() {
        # Reindex classes by abusing array_column a little
        return array_column($this->getCLasses(), null, 'ShortName');
    }

    # for now we will just override uncache($ID) and put these here, when we add something to replace the classes arrays this might move --mifune
    public function uncache($ID) {
        $this->cache->deleteValue('min_user_level');
        $this->cache->deleteValue('min_user_classid');
        $this->cache->deleteValue('min_staff_level');
        parent::uncache($ID);
    }
}
