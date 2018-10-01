<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\User;

use Luminance\Errors\InputError;

class UserRepository extends Repository {

    protected $entityName = 'User';

    protected function post_load($ID, $user) {
        // Stop here if we haven't received a valid user (e.g. System)
        if ($user === null) {
            return null;
        }

        // References the email repo, as an easy shortcut to send e-mails
        $user->friends = null;

        // Legacy data already loaded, we can stop here
        if ($user->legacy !== null) {
            return $user;
        }

        $user_legacy = null;
        $key = "_entity_User_legacy_{$ID}";

        if ($this->use_cache) {
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
                if ($user->Username === null || !strlen($user->Username)) {
                    $user->Username = $user->legacy['Username'];
                }
                if ($user->Password === null) {
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

    public function isAvailable($username) {
        $user = $this->get_by_username($username);
        if ($user) {
            return false;
        }

        $stmt = $this->db->raw_query("SELECT ID FROM users_main WHERE Username = ?", [$username]);
        $user_legacy = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user_legacy) {
            return false;
        }
        return true;
    }

    public function checkAvailable($username) {
        if (!$this->isAvailable($username))
            throw new InputError("That username is not available.");
    }

    /**
     * Get users by IDs (Eager Loading)
     *
     * @param array $ids
     * @return array
     */
    public function findIn(array $ids) {
        // Invalid SQL if ID list is empty
        if (empty($ids)) {
            return null;
        }

        // Do not pass the ids directly,
        // instead build a prepared statement
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $users =  $this->find("ID IN ({$inQuery}) ORDER BY ID DESC", $ids);

        // Load additional infos about the user (Legacy)
        foreach ($users as &$user) {
            $user = $this->post_load($user->ID, $user);
        }

        return $users;
    }

    /**
     * Get all users invited by a specific user
     *
     * @param int $userID
     * @return array
     */
    public function invitedBy($userID, $page = 1) {
        list($page, $limit) = page_limit(50);
        $ids = $this->db->raw_query("SELECT SQL_CALC_FOUND_ROWS UserID FROM users_info WHERE Inviter = ? ORDER BY UserID DESC LIMIT {$limit}", [$userID])->fetchAll(\PDO::FETCH_COLUMN);
        $results = $this->db->found_rows();
        return [$results, $this->findIn($ids)];
    }
}
