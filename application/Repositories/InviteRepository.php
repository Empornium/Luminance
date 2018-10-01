<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\Invite;

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
        return $this->get('Email = ?', [$email]);
    }
}
