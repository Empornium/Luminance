<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\Session;
use Luminance\Entities\User;

class SessionRepository extends Repository {

    protected $entityName = 'Session';

    public function getUserLegacySession(User $user) {
        $session = $this->get('`UserID` = ? AND `Flags` & 128', [$user->ID]);
        return $session;
    }

}
