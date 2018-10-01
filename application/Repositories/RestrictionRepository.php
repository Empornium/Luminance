<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\User;
use Luminance\Entities\Restriction;
use Luminance\Errors\UnauthorizedError;

class RestrictionRepository extends Repository {

    protected $entityName = 'Restriction';

    public function get_expiry($user, $section) {
        if ($user instanceof User) {
            $user = $user->ID;
        }
        $restrictions = $this->find('`UserID` = ?', [$user]);
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

    public function is_restricted($user, $section) {
        if ($user instanceof User) {
            $user = $user->ID;
        }
        $restrictions = $this->find('`UserID` = ?', [$user]);
        foreach ($restrictions as $restriction) {
            if ($restriction->getFlag($section)) {
                if (!$restriction->hasExpired()) {
                    return true;
                }
            }
        }
        return false;
    }

    public function check_restricted($user, $section) {
        if ($this->is_restricted($user, $section)) {
            $section = Restriction::$decode[$section];
            throw new UnauthorizedError("Your {$section['name']} rights have been removed");
        }
    }

    public function is_warned($user) {
        return $this->is_restricted($user, Restriction::WARNED);
    }

    public function check_warned($user) {
        if ($this->is_warned($user)) {
            throw new UnauthorizedError();
        }
    }

    public function get_restrictions($user) {
        $restrictions = $this->find('`UserID` = ? AND Expires >= NOW()', [$user]);
        $flags = 0;
        foreach ($restrictions as $restriction) {
            $flags |= $restriction->Flags;
        }

        $decoded = [];
        foreach (Restriction::$decode as $key => $restrict) {
            if ($flags & $key != 0)
                $decoded[] = $restrict['name'];
        }
        return $decoded;
    }

    /**
     * Check if a given restriction belongs to a given user
     * @param Restriction $restriction
     * @param int $userID
     * @throws UnauthorizedError
     */
    public function check_user(Restriction $restriction, $userID) {
        if ((int) $restriction->UserID !== (int) $userID) {
            throw new UnauthorizedError();
        }
    }
}
