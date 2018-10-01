<?php

namespace Luminance\Services;

use Luminance\Core\Service;
use Luminance\Entities\User;
use Luminance\Entities\Restriction;
use Luminance\Errors\InternalError;

class UserManager extends Service
{

    protected static $useRepositories = [
        'users'        => 'UserRepository',
        'restrictions' => 'RestrictionRepository'
    ];

    protected static $useServices = [
        'auth'     => 'Auth',
        'options'  => 'Options',
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
            if (!$user = $this->users->load($user)) {
                throw new InternalError("Unable to render user.");
            }
        }

        $user_limit = (int) $this->options->UsersLimit;

        if ($this->restrictions->is_restricted($user, Restriction::INVITE) || $user->on_ratiowatch() || !$user->legacy['can_leech'] ||
            ((int) $user->legacy['Invites'] === 0 && !$this->auth->isAllowed('site_send_unlimited_invites')) ||
            (user_count() >= $user_limit && $user_limit !== 0 && !$this->auth->isAllowed('site_can_invite_always'))) {
            return false;
        }

        return true;
    }
}
