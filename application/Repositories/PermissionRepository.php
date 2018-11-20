<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\Permission;

class PermissionRepository extends Repository
{

    protected $entityName = 'Permission';

    private $minUserLevel;
    private $minUserClassID;
    private $minStaffLevel;

    public function getLegacyPermission($ID)
    {
        $permission = $this->load($ID);
        if ($permission) {
            return $permission->getLegacy();
        } else {
            return ['Permissions' => []];
        }
    }

    public function getMinUserLevel()
    {
        if (!$this->minUserLevel) {
            $MinUserLevel = $this->cache->get_value('min_user_level');
            if ($MinUserLevel===false) {
                $MinUserLevel = $this->db->raw_query("SELECT MIN(Level) FROM permissions WHERE isAutoPromote='1' AND IsUserClass='1'")->fetchColumn();
                $this->cache->cache_value('min_user_level', $MinUserLevel);
            }
            $this->minUserLevel = $MinUserLevel;
        }
        return $this->minUserLevel;
    }

    public function getMinUserClassID()
    {
        if (!$this->minUserClassID) {
            $MinUserClassID = $this->cache->get_value('min_user_classid');
            if ($MinUserClassID===false) {
                $MinUserClassID = $this->db->raw_query("SELECT ID FROM permissions WHERE isAutoPromote='1' AND IsUserClass='1' ORDER BY Level ASC LIMIT 1")->fetchColumn();
                $this->cache->cache_value('min_user_classid', $MinUserClassID);
            }
            $this->minUserClassID = $MinUserClassID;
        }
        return $this->minUserClassID;
    }

    public function getMinStaffLevel()
    {
        if (!$this->minStaffLevel) {
            $MinStaffLevel = $this->cache->get_value('min_staff_level');
            if ($MinStaffLevel===false) {
                $MinStaffLevel = $this->db->raw_query("SELECT MIN(Level) FROM permissions WHERE DisplayStaff='1'")->fetchColumn();
                $this->cache->cache_value('min_staff_level', $MinStaffLevel);
            }
            $this->minStaffLevel = $MinStaffLevel;
        }
        return $this->minStaffLevel;
    }

    public function getMinClassPermission($perm = "")
    {
        $Classes = $this->db->raw_query("SELECT ID, `Values` FROM permissions ORDER BY Level")->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_GROUP);
        foreach ($Classes as $Class => $Permissions) {
            if (is_null($Permissions)) {
                continue;
            }
            $Permissions = unserialize($Permissions[0]);
            if (!array_key_exists($perm, $Permissions)) {
                continue;
            }
            if ($Permissions[$perm] == 1) {
                return $this->load($Class);
            }
        }
        return false;
    }

    // for now we will just override uncache($ID) and put these here, when we add something to replace the classes arrays this might move --mifune
    public function uncache($ID)
    {
        $this->cache->delete_value('min_user_level');
        $this->cache->delete_value('min_user_classid');
        $this->cache->delete_value('min_staff_level');
        parent::uncache($ID);
    }
}
