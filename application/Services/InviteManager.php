<?php
namespace Luminance\Services;

use Luminance\Core\Service;
use Luminance\Entities\Invite;

class InviteManager extends Service {

    protected static $defaultOptions = [
        'InviteValidPeriod'  => [
            'value' => '72',
            'section' => 'invites',
            'displayRow' => 1,
            'displayCol' => 1,
            'type' => 'int',
            'description' => 'Invite valid period in hours'
        ],
    ];

    protected static $useServices = [
        'cache'   => 'Cache',
        'options' => 'Options',
        'repos'   => 'Repos',
    ];

    public function newInvite($userID, $email, $anon, $comment) {
        $expires = new \DateTime('+'.$this->options->InviteValidPeriod.' hour');
        $invite = new Invite();
        $invite->InviterID = $userID;
        $invite->Email     = $email;
        $invite->Anon      = $anon;
        $invite->Comment   = $comment;
        $invite->Expires   = $expires->format('Y-m-d H:i:s');
        $this->cache->deleteValue("invite_{$email}");
        $this->repos->invites->save($invite);
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
        $user   = $this->repos->users->load($user);
        $params = [$max, $amount, $user->ID];
        $result = $this->master->db->rawQuery("UPDATE users_main SET Invites = LEAST(?, Invites + ?)  WHERE ID = ?", $params);

        $this->repos->users->uncache($user->ID);
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
        # Do not decrease if user has unlimited invites
        if ($this->master->auth->isAllowed('site_send_unlimited_invites')) {
            return false;
        }

        $user   = $this->repos->users->load($user);
        $params = [$min, $amount, $user->ID];
        $result = $this->master->db->rawQuery("UPDATE users_main SET Invites = GREATEST(?, Invites - ?)  WHERE ID = ?", $params);

        $this->repos->users->uncache($user->ID);
        return (bool) $result->rowCount();
    }
}
