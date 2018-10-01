<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Entities\Invite;
use Luminance\Errors\ConfigurationError;
use Luminance\Errors\InputError;
use Luminance\Errors\SystemError;
use Luminance\Errors\UserError;
use Luminance\Errors\AuthError;
use Luminance\Errors\InternalError;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\UnauthorizedError;
use Luminance\Entities\Email;

class EmailManager extends Service {

    protected static $useRepositories = [
        'emails' => 'EmailRepository',
        'users'  => 'UserRepository',
    ];

    protected static $useServices = [
        'db'        => 'DB',
        'cache'     => 'Cache',
        'secretary' => 'Secretary',
        'crypto'    => 'Crypto',
        'tpl'       => 'TPL',
        'settings'  => 'Settings',
        'flasher'   => 'Flasher',
        'render'    => 'Render',
    ];

    public function reduceEmail($address) {
        $parts = explode('@', $address);
        if (count($parts) != 2) {
            throw new InternalError("Passed invalid e-mail address to reduceEmail()");
        }
        list($user, $domain) = $parts;

        if (strpos($user, '+')) {
            # Handle extension addresses
            $user = explode('+', $user, 2)[0];
        }

        $domainParts = array_reverse(explode('.', $domain));
        foreach ($domainParts as $idx => $part) {
            $domain = $part;
            if (strlen($part) >= 5 && $idx >= 1) {
                break;
            }
        }
        return "{$user}@{$domain}";
    }

    public function validateAddress($address) {
        $email = $this->emails->get('Address=:address', [':address' => $address]);
        if (!$email) throw new UserError("Unknown email.");
        $email->setFlags(Email::VALIDATED);
        $default = $this->emails->get(
            'UserID=:userID AND Flags & :default != 0',
            [':userID' => $email->UserID, ':default' => Email::IS_DEFAULT]
        );
        if (is_null($default)) {
            $email->setFlags(Email::IS_DEFAULT);
            $user = $this->users->load($email->UserID);
            $user->emailID = $email->ID;
            $this->users->save($user);
        }
        $this->emails->save($email);
    }

    public function newEmail($userID, $address) {

        $this->validate($address);

        $email = new Email();
        $email->UserID = $userID;
        $email->Address = $address;
        $email->Reduced = $this->reduceEmail($address);
        $email->Created = new \DateTime;
        $email->Changed = new \DateTime;
        $email->Flags = 0;
        if (!empty($this->master->request->ip)) {
            $email->IPID = $this->master->request->ip->ID;
        } else {
            $email->IPID = 0;
        }

        $this->emails->save($email);

        return $email;
    }

    public function send_confirmation($emailID) {
        $email = $this->emails->load($emailID);
        $token = $this->secretary->getExternalToken($email->Address, 'users.email.confirm');
        $token = $this->crypto->encrypt(['email' => $email->Address, 'token' => $token], 'default', true);

        $subject = 'Confirm email address';
        $variables = [
            'userID'   => $email->UserID,
            'token'    => $token,
            'settings' => $this->settings,
            'scheme'   => $this->master->request->ssl ? 'https' : 'http',
        ];

        $email->send_email($subject, 'confirm_email', $variables);
        $this->flasher->notice("An e-mail with further instructions has been sent to the provided address.");

        $email->Changed = new \DateTime;
        $this->emails->save($email);
    }

    /**
     * Send an arbitrary email to an arbitrary address
     * Use user->send_email() or email->send_email() instead
     *
     * @param string $email
     * @param string $subject
     * @param string $body
     * @return void
     */
    public function send_email($email, $subject, $body) {
        if ($email instanceof Email) {
            $email = $email->Address;
        }
        $headerVariables = [
            'to'          => $email,
            'from'        => 'noreply',
            'contentType' => 'text/plain',
        ];

        $variables['to'] = $email;
        $headers = str_replace("\n", "\r\n", $this->render->render("email/headers.twig", $headerVariables));
        mail($email, $subject, $body, $headers, "-f " . $headerVariables['from'] . "@" . $this->settings->main->mail_domain);
    }

    /**
     * Send an invite e-mail
     *
     * @param Invite $invite
     * @param string $email
     * @param string $username Inviter's username
     * @return void
     */
    public function sendInviteEmail(Invite $invite, $email, $username, $anon = false) {
        $token = $this->crypto->encrypt(['email' => $email, 'inviteID' => $invite->ID, 'token' => $invite->InviteKey], 'default', true);

        $subject = 'You have been invited to '.$this->settings->main->site_name;

        $email_body = [];
        $email_body['username'] = $username;
        $email_body['email']    = $email;
        $email_body['token']    = $token;
        $email_body['anon']     = $anon;
        $email_body['scheme']   = $this->master->request->ssl ? 'https' : 'http';

        if ($this->settings->site->debug_mode) {
            $body = $this->render->render('email/invite_email.flash.twig', $email_body);
            $this->flasher->notice($body);
        } else {
            $body = $this->render->render('email/invite_email.email.twig', $email_body);
            $this->send_email($email, $subject, $body);
        }
    }

    /**
     * Validate an email
     * Note: do not confuse with validateAddress which confirms an Email instance
     *
     * @param string $email
     * @return void
     */
    public function validate($email) {
        $this->emails->checkFormat($email);
        $this->emails->checkAvailable($email);
        $this->emails->checkBlacklisted($email);
    }
}
