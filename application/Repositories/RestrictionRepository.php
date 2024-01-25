<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Core\Entity;
use Luminance\Entities\User;
use Luminance\Entities\Restriction;
use Luminance\Errors\UnauthorizedError;

class RestrictionRepository extends Repository {

    protected $entityName = 'Restriction';

    public function getExpiry($user, $section) {
        $restrictions = $this->getAllForUser($user);
        $expiry = null;
        foreach ($restrictions as $restriction) {
            if ($restriction->getFlag($section)) {
                if (!$restriction->hasExpired()) {
                    if ($restriction->Expires > $expiry) {
                        $expiry = $restriction->Expires;
                    }
                }
            }
        }
        return $expiry;
    }

    public function isRestricted($user, $section) {
        $restrictions = $this->getAllForUser($user);

        foreach ($restrictions as $restriction) {
            if ($restriction->getFlag($section)) {
                if (!$restriction->hasExpired()) {
                    return true;
                }
            }
        }
        return false;
    }

    public function checkRestricted($user, $section) {
        if ($this->isRestricted($user, $section)) {
            $decode = array_column(Restriction::$decode, null, 'flag');
            $section = $decode[$section];
            throw new UnauthorizedError("Your {$section['name']} rights have been removed");
        }
    }

    public function isWarned($user) {
        return $this->isRestricted($user, Restriction::WARNED);
    }

    public function checkWarned($user) {
        if ($this->isWarned($user)) {
            throw new UnauthorizedError();
        }
    }

    public function getRestrictions($user) {
        $restrictions = $this->getAllForUser($user);
        $flags = 0;

        foreach ($restrictions as $restriction) {
            if (!$restriction->hasExpired()) {
                $flags |= $restriction->Flags;
            }
        }

        $decoded = [];
        foreach (Restriction::$decode as $key => $restrict) {
            if ($flags & !($key === 0))
                $decoded[] = $restrict['name'];
        }
        return $decoded;
    }

    public function searchRestrictions(string $types = null, int $userID = null, int $authorID = null, string $order = 'ID') {
        $pageSize = $this->master->settings->pagination->reports;
        list($page, $limit) = page_limit($pageSize);

        $decode = array_column(Restriction::$decode, null, 'name');
        $flags = 0;
        foreach ($types as $type) {
            $flags = $flags | $decode[$type]['flag'];
        }

        if (empty($flags) && empty($userID) && empty($authorID)) {
            $restrictions = $this->find(null, null, $order, $limit);
        } elseif (!empty($flags) && empty($userID) && empty($authorID)) {
            $restrictions = $this->find('`Flags` = ?', [$flags], $order, $limit);
        } elseif (!empty($userID) && empty($flags) && empty($authorID)) {
            $restrictions = $this->find('`UserID` = ?', [$userID], $order, $limit);
        } elseif (!empty($authorID) && empty($userID) && empty($flags)) {
            $restrictions = $this->find('`StaffID` = ?', [$authorID], $order, $limit);
        } elseif (!empty($flags) && !empty($userID) && empty($authorID)) {
            $restrictions = $this->find('`Flags` = ? AND `UserID` = ?', [$flags, $userID], $order, $limit);
        } elseif (!empty($flags) && !empty($authorID) && empty($userID)) {
            $restrictions = $this->find('`Flags` = ? AND `StaffID` = ?', [$flags, $authorID], $order, $limit);
        } elseif (!empty($userID) && !empty($authorID) && empty($flags)) {
            $restrictions = $this->find('`UserID` = ? AND `StaffID` = ?', [$userID, $authorID], $order, $limit);
        } else {
            $restrictions = $this->find('`Flags` = ? AND `UserID` = ? AND `StaffID` = ?', [$flags, $userID, $authorID], $order, $limit);
        }

        return $restrictions;
    }

    /**
     * Check if a given restriction belongs to a given user
     * @param Restriction $restriction
     * @param int $userID
     * @throws UnauthorizedError
     */
    public function checkUser(Restriction $restriction, $userID) {
        if (!((int) $restriction->UserID === (int) $userID)) {
            throw new UnauthorizedError();
        }
    }

    /**
     * saves the passed entity to the DB and clears metadata caches
     * @param  Entity $entity Restriction entity to be saved
     *
     * @access public
     */
    public function save(Entity &$entity, $allowUpdate = false) {
        # First check if there's anything to actually save!
        if (!$entity->needsSaving()) {
            return;
        }
        $key = "user_restrictions_{$entity->UserID}";
        $this->cache->deleteValue($key);
        parent::save($entity, $allowUpdate);
    }

    /**
     * delete's the passed entity from the DB and clears metadata caches
     * @param  Entity $entity Restriction entity to be deleted
     *
     * @access public
     */
    public function delete(Entity $entity) {
        parent::delete($entity);
        $key = "user_restrictions_{$entity->UserID}";
        $this->cache->deleteValue($key);
    }

    public function getAllForUser($user) {
        if ($user instanceof User) {
            $user = $user->ID;
        }

        if ($this->useCache) {
            $key = "user_restrictions_{$user}";
            $restrictions = $this->cache->getValue($key);
        }

        if (!is_array($restrictions)) {
            $restrictions = $this->find('`UserID` = ?', [$user]);
            if ($this->useCache) {
                $this->cache->cacheValue($key, $restrictions, 0);
            }
        }

        return $restrictions;
    }
}
