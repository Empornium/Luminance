<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\User;

class UserRepository extends Repository {

    protected $entityName = 'User';

    protected function post_load($ID, $User) {
        $user_legacy = null;
        if ($this->use_cache) {
            $key = "_entity_User_legacy_{$ID}";
            $user_legacy = $this->cache->get_value($key);
        }
        if (!$user_legacy) {
            $stmt = $this->db->raw_query("SELECT * FROM users_main AS um LEFT JOIN users_info AS ui ON um.ID = ui.UserID WHERE um.ID = ?", [$ID]);
            $user_legacy = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($this->use_cache) {
                $this->cache->cache_value($key, $user_legacy, 3600);
            }
        }
        if ($user_legacy) {
            if (!$User) {
                $User = new User();
                $User->ID = $ID;
            }
            $User->legacy = $user_legacy;
            $User->legacy['RSS_Auth'] = md5($User->ID . $this->master->settings->keys->rss_hash . $User->legacy['torrent_pass']);
            if ($User->needs_update()) {
                if (is_null($User->Username) || !strlen($User->Username)) {
                    $User->Username = $User->legacy['Username'];
                }
                if (is_null($User->Password)) {
                    $encoded_secret = base64_encode($User->legacy['Secret']);
                    $encoded_hash = base64_encode(hex2bin($User->legacy['PassHash']));
                    $User->Password = "\$salted-md5\${$encoded_secret}\${$encoded_hash}";
                }
                $this->save($User);
            }
        }
        return $User;
    }

    public function uncache($ID) {
        parent::uncache($ID);
        $this->cache->delete_value('_entity_User_legacy_' . $ID);
        $this->cache->delete_value('enabled_' . $ID);
        $this->cache->delete_value('user_info_' . $ID);
        $this->cache->delete_value('user_info_heavy_' . $ID);
        $this->cache->delete_value('user_stats_' . $ID);
    }

    public function get_by_username($Username) {
        $user = $this->get('`Username` = ?', [$Username]);
        return $user;
    }
}
