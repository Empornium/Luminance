<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

use Luminance\Entities\User;

use Luminance\Errors\InputError;
use Luminance\Errors\InternalError;

class UserRepository extends Repository {

    protected $entityName = 'User';

    /**
     * Load legacy user array from cache/DB
     * @param int|Entity $user user to load legacy data for
     *
     */
    public function loadLegacyUser($ID) {
        # UserID may be an array with a single element.
        if (is_array($ID)) {
            # Implode array, with dashes in case we need to debug
            # should implode to a single numeric ID though
            $ID = implode('_', $ID);
        }

        $userLegacy = null;
        $key = "_entity_User_legacy_{$ID}";

        if ($this->useCache) {
            $userLegacy = $this->cache->getValue($key);
        }

        if (empty($userLegacy)) {
            $userLegacy = $this->db->rawQuery(
                "SELECT *
                   FROM users_main AS um
              LEFT JOIN users_info AS ui ON um.ID = ui.UserID
                  WHERE um.ID = ?",
                [$ID]
            )->fetch(\PDO::FETCH_ASSOC);

            # User has no legacy content
            if ($this->db->foundRows() === 0) {
                throw new InternalError("Could not load legacy tables for user: {$ID}");
            }

            $userLegacy['AuthKey'] = $this->master->secretary->getToken("AuthKey");

            if ($this->useCache) {
                $this->cache->cacheValue($key, $userLegacy, 3600);
            }
        }
        $userLegacy['RSS_Auth'] = md5($ID . $this->master->settings->keys->rss_hash . $userLegacy['torrent_pass']);

        return $userLegacy;
    }

    /**
     * Delete User entity from cache
     * @param int|Entity $user user to uncache
     *
     */
    public function uncache($userID) {
        $user = $this->load($userID);
        if ($user instanceof User) {
            parent::uncache($user);
            $usernameMD5 = md5($user->Username);
            $this->cache->deleteValue("_entity_User_legacy_{$user->ID}");
            $this->cache->deleteValue("enabled_{$user->ID}");
            $this->cache->deleteValue("user_info_{$user->ID}");
            $this->cache->deleteValue("user_info_heavy_{$user->ID}");
            $this->cache->deleteValue("user_stats_{$user->ID}");
            $this->cache->deleteValue("user_friends_{$user->ID}");
            $this->cache->deleteValue("user_passkeys_{$user->ID}");
            $this->cache->deleteValue("user_passwords_{$user->ID}");
            $this->cache->deleteValue("_query_User_Username_{$usernameMD5}");
        }
    }

    public function getByUsername($username) {
        # Usernames can have UTF8 in them!
        # Use MD5 just to ensure an ASCII key
        $usernameMD5 = md5($username);
        $user = $this->get('`Username` = ?', [$username], "_query_User_Username_{$usernameMD5}");
        return $user;
    }

    public function isAvailable($username) {
        $user = $this->getByUsername($username);
        if ($user instanceof User) {
            return false;
        }

        return true;
    }

    public function isValid($username) {
        if (strlen($username) === 0) {
            return false;
        }

        return true;
    }

    public function checkAvailable($username) {
        if (!$this->isAvailable($username))
            throw new InputError("That username is not available.");
    }

    public function checkValid($username) {
        if (!$this->isValid($username))
            throw new InputError("That username is not valid.");
    }

    /**
     * Get users by IDs (Eager Loading)
     *
     * @param array $ids
     * @return array
     */
    public function findIn(array $ids) {
        # Invalid SQL if ID list is empty
        if (empty($ids)) {
            return null;
        }

        # Do not pass the ids directly,
        # instead build a prepared statement
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $users =  $this->find("ID IN ({$inQuery}) ORDER BY ID DESC", $ids);

        # Load additional infos about the user (Legacy)
        foreach ($users as &$user) {
            $user = $this->postLoad($user->ID, $user);
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
        $ids = $this->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS
                    UserID
               FROM users_info
              WHERE Inviter = ?
           ORDER BY UserID DESC
              LIMIT {$limit}",
            [$userID]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $results = $this->db->foundRows();
        return [$results, $this->findIn($ids)];
    }

    /**
     * Clear all sessions for a given user
     *
     * @param User $user
     */
    public function clearSessions(User $user) {
        $this->db->rawQuery('DELETE FROM sessions WHERE UserID = ?', [$user->ID]);
    }
}
