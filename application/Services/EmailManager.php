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
use Luminance\Entities\Email;

class EmailManager extends Service {

    protected static $useRepositories = [
        'emails' => 'EmailRepository',
        'users'  => 'UserRepository',
    ];

    protected static $useServices = [
        'db'        => 'DB',
        'secretary' => 'Secretary',
        'crypto'    => 'Crypto',
        'tpl'       => 'TPL',
        'settings'  => 'Settings',
        'flasher'   => 'Flasher',
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
        $default = $this->emails->get('UserID=:userID AND Flags & :default != 0',
            [':userID' => $email->UserID, ':default' => Email::IS_DEFAULT]);
        if (is_null($default)) {
            $email->setFlags(Email::IS_DEFAULT);
            $user = $this->users->load($email->userID);
            $user->emailID = $email->ID;
            $this->users->save($user);
        }
        $this->emails->save($email);
    }

    public function checkEmailAvailable($address, $checkLegacy = true) {
        // reduce fannies around with the domain. :(
        //$address = $this->reduceEmail($address);
        $email = $this->emails->get_by_address($address);
        if ($email) {
            return false;
        }

        if (!$checkLegacy) return true;

        $stmt = $this->db->raw_query("SELECT ID FROM users_main WHERE Email = ?", [$address]);
        $user_legacy = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user_legacy) {
            return false;
        }
        return true;
    }

    public function newEmail($userID, $address, $checkLegacy = true) {
        $email = new Email();
        $email->UserID = $userID;
        $email->Address = $address;
        $email->Reduced = $this->reduceEmail($address);
        $email->changed = new \DateTime;
        $email->Flags = 0;

        if (!$this->checkEmailAvailable($address, $checkLegacy))
            throw new UserError("Email address already in use");

        $this->emails->save($email);

        /*
         * This piece of code will update the time of their last email change
         * to the current time *not* the current change.
         */
        if (!empty($this->master->request->user->ID)) {
            $this->db->raw_query(
                "UPDATE users_history_emails
                    SET Time=:time
                  WHERE UserID=:userid
                    AND Time='0000-00-00 00:00:00'",
                [':time' => sqltime(), ':userid' => $userID]);

            $this->db->raw_query(
                "INSERT INTO users_history_emails
                    (UserID, Email, Time, IP, ChangedbyID) VALUES
                    (:userid, :address, '0000-00-00 00:00:00', :changerip, :changerid)",
                    [':userid'    => $userID,
                     ':address'   => $address,
                     ':changerip' => $this->master->request->IP,
                     ':changerid' => $this->master->request->user->ID]
            );
        }

        return $email;
    }

    public function send_confirmation($emailID) {
        $email = $this->emails->load($emailID);
        $token = $this->secretary->getExternalToken($email->Address, 'users.email.confirm');
        $token = $this->crypto->encrypt(['email' => $email->Address, 'token' => $token], 'default', true);

        $subject = 'Confirm email address';
        $email_body = [];
        $email_body['userID']   = $email->UserID;
        $email_body['token']    = $token;
        $email_body['settings'] = $this->settings;
        $email_body['scheme']   = $this->master->request->ssl ? 'https' : 'http';

        if ($this->settings->site->debug_mode) {
            $body = $this->tpl->render('confirm_email.flash', $email_body);
            $this->flasher->notice($body);
        } else {
            $body = $this->tpl->render('confirm_email.email', $email_body);
            $this->secretary->send_email($email->Address, $subject, $body);
            $this->flasher->notice("An e-mail with further instructions has been sent to the provided address.");
        }

        $email->Changed = new \DateTime;
        $this->emails->save($email);

    }
}
