<?php
namespace Luminance\Services;

use Luminance\Core\Service;
use Luminance\Entities\Email;
use Luminance\Entities\Invite;
use Luminance\Errors\UserError;
use Luminance\Errors\InternalError;

class EmailManager extends Service {

    protected static $useServices = [
        'db'        => 'DB',
        'cache'     => 'Cache',
        'secretary' => 'Secretary',
        'crypto'    => 'Crypto',
        'tpl'       => 'TPL',
        'settings'  => 'Settings',
        'flasher'   => 'Flasher',
        'render'    => 'Render',
        'repos'     => 'Repos',
    ];

    public function reduceEmail($address) {
        $parts = explode('@', $address);
        if (!(count($parts) === 2)) {
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
        $email = $this->repos->emails->getByAddress($address);
        if (empty($email)) throw new UserError("Unknown email.");
        $email->setFlags(Email::VALIDATED);
        $default = $this->repos->emails->get(
            'UserID=:userID AND Flags & :default != 0',
            [':userID' => $email->UserID, ':default' => Email::IS_DEFAULT]
        );
        if (is_null($default)) {
            $email->setFlags(Email::IS_DEFAULT);
            $user = $this->repos->users->load($email->UserID);
            $user->emailID = $email->ID;
            $this->repos->users->save($user);
        }
        $this->repos->emails->save($email);
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

        $this->repos->emails->save($email);
        $this->cache->deleteValue("address_{$address}");

        return $email;
    }

    public function sendConfirmation($emailID) {
        $email = $this->repos->emails->load($emailID);
        $token = $this->secretary->getExternalToken($email->Address, 'user.email.confirm');
        $token = $this->crypto->encrypt(['email' => $email->Address, 'token' => $token], 'default', true);

        $subject = 'Confirm email address';
        $variables = [
            'userID'   => $email->UserID,
            'token'    => $token,
            'settings' => $this->settings,
            'scheme'   => $this->master->request->ssl ? 'https' : 'http',
        ];

        $email->sendEmail($subject, 'confirm_email', $variables);
        $this->flasher->notice("An e-mail with further instructions has been sent to the provided address.");

        $email->Changed = new \DateTime;
        $this->repos->emails->save($email);
    }

    /**
     * Send an arbitrary email to an arbitrary address
     * Use user->sendEmail() or email->sendEmail() instead
     *
     * @param string $email
     * @param string $subject
     * @param string $body
     * @return void
     */
    public function sendEmail($email, $subject, $body) {
        if ($email instanceof Email) {
            $email = $email->Address;
        }
        $headerVariables = [
            'to'          => $email,
            'from'        => 'noreply',
            'contentType' => 'text/plain',
        ];

        $headers = str_replace("\n", "\r\n", $this->render->template("email/headers.twig", $headerVariables));
        mail($email, $subject, $body, $headers, "-f " . $headerVariables['from'] . "@" . $this->settings->main->mail_domain);
    }

    /**
     * Send an application e-mail
     *
     * @param email $email
     * @return void
     */
    public function sendApplicationEmail($email, $request, $template) {
        $subject = 'Your application for '.$this->settings->main->site_name;

        $emailBody = [];
        $emailBody['email']    = $email;
        $emailBody['request']  = $request;
        $emailBody['scheme']   = $this->master->request->ssl ? 'https' : 'http';

        if ($this->settings->site->debug_mode) {
            $body = $this->render->template($template, $emailBody);
            $this->flasher->notice($body);
        } else {
            $body = $this->render->template($template, $emailBody);
            $this->sendEmail($email, $subject, $body);
        }
    }

    /**
     * Send an invite e-mail
     *
     * @param Invite $invite
     * @return void
     */
    public function sendInviteEmail(Invite $invite) {
        $user = $this->repos->users->load($invite->InviterID);
        $token = $this->crypto->encrypt(['inviteID' => $invite->ID], 'default', true);

        $subject = 'You have been invited to '.$this->settings->main->site_name;

        $emailBody = [];
        $emailBody['username'] = $user->Username;
        $emailBody['email']    = $invite->Email;
        $emailBody['token']    = $token;
        $emailBody['anon']     = $invite->Anon;
        $emailBody['scheme']   = $this->master->request->ssl ? 'https' : 'http';

        if ($this->settings->site->debug_mode) {
            $body = $this->render->template('email/invite_email.flash.twig', $emailBody);
            $this->flasher->notice($body);
        } else {
            $body = $this->render->template('email/invite_email.email.twig', $emailBody);
            $this->sendEmail($invite->Email, $subject, $body);
        }

        $invite->Changed = new \DateTime;
        $this->repos->invites->save($invite);
    }

    /**
     * Send an applicant their invite e-mail
     *
     * @param Invite $invite
     * @return void
     */
    public function sendAcceptEmail(Invite $invite) {
        $user = $this->repos->users->load($invite->InviterID);
        $token = $this->crypto->encrypt(['inviteID' => $invite->ID], 'default', true);

        $subject = 'Your application to '.$this->settings->main->site_name.' has been accepted';

        $emailBody = [];
        $emailBody['username'] = $user->Username;
        $emailBody['email']    = $invite->Email;
        $emailBody['token']    = $token;
        $emailBody['scheme']   = $this->master->request->ssl ? 'https' : 'http';

        if ($this->settings->site->debug_mode) {
            $body = $this->render->template('email/application_accepted.flash.twig', $emailBody);
            $this->flasher->notice($body);
        } else {
            $body = $this->render->template('email/application_accepted.email.twig', $emailBody);
            $this->sendEmail($invite->Email, $subject, $body);
        }

        $invite->Changed = new \DateTime;
        $this->repos->invites->save($invite);
    }

    /**
     * Validate an email
     * Note: do not confuse with validateAddress which confirms an Email instance
     *
     * @param string $email
     * @return void
     */
    public function validate($email) {
        $this->repos->emails->checkFormat($email);
        $this->repos->emails->checkAvailable($email);
        $this->repos->emails->checkBlacklisted($email);
    }
}
