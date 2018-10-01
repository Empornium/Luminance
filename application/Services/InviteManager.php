<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Entities\User;
use Luminance\Errors\ConfigurationError;
use Luminance\Errors\SystemError;
use Luminance\Errors\UserError;
use Luminance\Errors\AuthError;
use Luminance\Errors\InternalError;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\UnauthorizedError;
use Luminance\Entities\Invite;

class InviteManager extends Service {

    protected static $useRepositories = [
        'invites' => 'InviteRepository',
    ];

    protected static $useServices = [
    ];

    public function newInvite($userID, $email) {
        $inviteKey = $this->master->secretary->getExternalToken($email, 'users.register');
        $expires = new \DateTime('+72 hour');
        $invite = new Invite();
        $invite->InviterID = $userID;
        $invite->InviteKey = $inviteKey;
        $invite->Email = $email;
        $invite->Expires = $expires->format('Y-m-d H:i:s');
        $this->invites->save($invite);
        return $invite;
    }

    /**
     * Increase the invite count of a user
     *
     * @param mixed $user Can be a User entity or an ID
     * @param int $amount
     * @param int $max
     * @return bool whether the count was changed or not
     *
     * @throws InternalError if no user could be found
     */
    public function giveInvite($user, $amount = 1, $max = 4) {
        $user   = $this->master->repos->users->load($user);
        $params = [$max, $amount, $user->ID];
        $result = $this->master->db->raw_query("UPDATE users_main SET Invites = LEAST(?, Invites + ?)  WHERE ID = ?", $params);

        $this->master->repos->users->uncache($user->ID);
        return (bool) $result->rowCount();
    }

    /**
     * Decrease the invite count of a user
     *
     * @param mixed $user Can be a User entity or an ID
     * @param int $amount
     * @param int $min
     * @return bool whether the count was changed or not
     *
     * @throws InternalError if no user could be found
     */
    public function takeInvite($user, $amount = 1, $min = 0) {
        // Do not decrease if user has unlimited invites
        if ($this->master->auth->isAllowed('site_send_unlimited_invites')) {
            return false;
        }

        $user   = $this->master->repos->users->load($user);
        $params = [$min, $amount, $user->ID];
        $result = $this->master->db->raw_query("UPDATE users_main SET Invites = GREATEST(?, Invites - ?)  WHERE ID = ?", $params);

        $this->master->repos->users->uncache($user->ID);
        return (bool) $result->rowCount();
    }
}
