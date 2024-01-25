<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

class InviteRepository extends Repository {

    protected $entityName = 'Invite';

    /**
     * Get the pending invites of a specific user
     *
     * @param int $userID
     * @return array
     */
    public function getByInviter($userID) {
        return $this->find('InviterID = ?', [$userID]);
    }

    /**
     * Get a pending invite by its e-mail
     *
     * @param string $email
     * @return mixed
     */
    public function getByAddress($email) {
        return $this->get('Email = ?', [$email], "invite_{$email}");
    }

    /**
     * Delete Invite entity from cache
     * @param int|Invite $invite invite to uncache
     *
     */
    public function uncache($invite) {
        $invite = $this->load($invite);
        parent::uncache($invite);
        $this->cache->deleteValue("_query_Invite_Email_{$invite->Email}");
    }
}
