<?php
namespace Luminance\Services;

use Luminance\Core\Master;
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

    public function newInvite($userID, $inviteKey, $email) {
        $expires = new \DateTime('+72 hour');
        $invite = new Invite();
        $invite->InviterID = $userID;
        $invite->InviteKey = $inviteKey;
        $invite->Email = $email;
        $invite->Expires = $expires->format('Y-m-d H:i:s');
        $this->invites->save($invite);
        return $invite;
    }
}
