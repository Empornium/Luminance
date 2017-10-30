<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\User;

class UserRepository extends Repository {

    protected $entityName = 'User';

    protected function post_load($ID, $user) {
        $user_legacy = null;
        if ($this->use_cache) {
            $key = "_entity_User_legacy_{$ID}";
            $user_legacy = $this->cache->get_value($key);
        }
        if (!$user_legacy) {
            $user_legacy = $this->db->raw_query("SELECT * FROM users_main AS um LEFT JOIN users_info AS ui ON um.ID = ui.UserID WHERE um.ID = ?", [$ID])->fetch(\PDO::FETCH_ASSOC);

            // User has no legacy content
            if ($this->db->found_rows() == 0) return $user;

            // Fill in some extra bits
            $user_legacy['Class'] = $this->db->raw_query("SELECT Level FROM permissions WHERE ID = ?", [$user_legacy['PermissionID']])->fetch(\PDO::FETCH_COLUMN);

            if ($this->use_cache) {
                $this->cache->cache_value($key, $user_legacy, 3600);
            }
        }
        if ($user_legacy) {
            if (!$user) {
                $user = new User();
                $user->ID = $ID;
            }
            $user->legacy = $user_legacy;
            $user->legacy['RSS_Auth'] = md5($user->ID . $this->master->settings->keys->rss_hash . $user->legacy['torrent_pass']);

            $user->legacy['NoNewsAlerts']     = @unserialize($user->legacy['SiteOptions'])['NoNewsAlerts'];
            $user->legacy['NoBlogAlerts']     = @unserialize($user->legacy['SiteOptions'])['NoBlogAlerts'];
            $user->legacy['NoContestAlerts']  = @unserialize($user->legacy['SiteOptions'])['NoContestAlerts'];

            if ($user->needs_update()) {
                if (is_null($user->Username) || !strlen($user->Username)) {
                    $user->Username = $user->legacy['Username'];
                }
                if (is_null($user->Password)) {
                    $encoded_secret = base64_encode($user->legacy['Secret']);
                    $encoded_hash = base64_encode(hex2bin($user->legacy['PassHash']));
                    $user->Password = "\$salted-md5\${$encoded_secret}\${$encoded_hash}";
                }
                $this->save($user);
            }
        }
        return $user;
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
