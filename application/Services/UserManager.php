<?php

namespace Luminance\Services;

use Luminance\Core\Service;
use Luminance\Entities\User;
use Luminance\Entities\Restriction;
use Luminance\Errors\InternalError;

class UserManager extends Service {

    protected static $useServices = [
        'db'       => 'DB',
        'cache'    => 'Cache',
        'auth'     => 'Auth',
        'options'  => 'Options',
        'settings' => 'Settings',
        'repos'    => 'Repos',
    ];

    /**
     * Check if a user can invite people
     *
     * @param $user
     * @return bool
     *
     * @throws InternalError if the user cannot be loaded
     */
    public function canInvite($user) {
        if (!$user instanceof User) {
            if (!$user = $this->repos->users->load($user)) {
                throw new InternalError("Unable to render user.");
            }
        }

        $userLimit = (int) $this->options->UsersLimit;

        # Has basic invite privs?
        if (!$this->auth->isAllowed('site_can_invite')) {
            return false;
        }

        # Has an invite to send?
        if ((int) $user->legacy['Invites'] === 0 && !$this->auth->isAllowed('site_send_unlimited_invites')) {
            return false;
        }

        # User limit reached?
        if (user_count() >= $userLimit && !($userLimit === 0) && !$this->auth->isAllowed('site_can_invite_always')) {
            return false;
        }

        # Check for account restrictions
        if ($this->repos->restrictions->isRestricted($user, Restriction::INVITE) || $user->onRatiowatch() || !$user->legacy['can_leech']) {
            return false;
        }

        if (!($user->isTwoFactorEnabled() || $this->settings->site->debug_mode)) {
            return false;
        }

        # Passed all checks so they can invite. :-)
        return true;
    }

    /**
     * Check if a user has sent an invite in the past
     *
     * @param $user
     * @return bool
     *
     * @throws InternalError if the user cannot be loaded
     */
    public function hasInvited($user) {
        if (!$user instanceof User) {
            if (!$user = $this->repos->users->load($user)) {
                throw new InternalError("Unable to render user.");
            }
        }

        $pending = $this->cache->getValue("pending_invites_{$user->ID}");
        if ($pending === false) {
            $pending = $this->db->rawQuery(
                "SELECT COUNT(*)
                   FROM invites
                  WHERE InviterID = ?",
                [$user->ID]
            )->fetchColumn();
            $this->cache->cacheValue("pending_invites_{$user->ID}", $pending, 0);
        }

        $invitees = $this->cache->getValue("invitees_{$user->ID}");
        if ($invitees === false) {
            $invitees = $this->db->rawQuery(
                "SELECT COUNT(*)
                   FROM users_info
                  WHERE Inviter = ?",
                [$user->ID]
            )->fetchColumn();
            $this->cache->cacheValue("invitees_{$user->ID}", $invitees, 0);
        }

        # Nothing pending or accepted?
        if ($pending === 0 && $invitees === 0) {
            return false;
        }

        return true;
    }
}
