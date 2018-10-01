<?php
namespace Luminance\Plugins\Users;

use Luminance\Core\Master;
use Luminance\Core\Plugin;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\InternalError;
use Luminance\Errors\InputError;
use Luminance\Errors\NotFoundError;
use Luminance\Errors\UserError;
use Luminance\Entities\Email;
use Luminance\Entities\Invite;
use Luminance\Entities\Session;
use Luminance\Entities\User;
use Luminance\Entities\Restriction;

use Luminance\Responses\Response;
use Luminance\Responses\Redirect;
use Luminance\Responses\Rendered;

use Luminance\Legacy\UserRank;

class UsersPlugin extends Plugin {

    public $routes = [
        # [method] [path match] [target function] [auth level] <extra arguments>
        [ 'GET',  'register',              0, 'user_register_form'     ],
        [ 'POST', 'register',              0, 'user_register'          ],
        [ 'GET',  'create',                0, 'user_create_form'       ],
        [ 'POST', 'create',                0, 'user_create'            ],
        [ 'GET',  'recover',               0, 'password_recover_form'  ],
        [ 'POST', 'recover',               0, 'password_recover'       ],
        [ 'GET',  'security',              2, 'generic_redirect'       ],

        [ 'GET',  '*/invite',              2, 'invite_form'            ],
        [ 'POST', '*/invite/send',         2, 'invite_send'            ],
        [ 'POST', '*/invite/delete',       2, 'invite_delete'          ],

        [ 'GET',  '*/security',            2, 'security_form'          ],
        [ 'POST', '*/password/change',     2, 'password_change'        ],
        [ 'GET',  '*/email/confirm',       0, 'email_confirm'          ],
        [ 'POST', '*/email/add',           2, 'email_add'              ],
        [ 'POST', '*/email/restore',       2, 'email_restore'          ],
        [ 'POST', '*/email/delete',        2, 'email_delete'           ],
        [ 'POST', '*/email/resend',        2, 'email_resend'           ],
        [ 'POST', '*/email/default',       2, 'email_default'          ],
        [ 'GET',  '*/sessions',            3, 'sessions_form'          ],
        [ 'GET',  '*/twofactor/enable',    2, 'twofactor_enable'       ],
        [ 'GET',  '*/twofactor/disable',   2, 'twofactor_disable_form' ],
        [ 'POST', '*/twofactor/disable',   2, 'twofactor_disable'      ],
        [ 'POST', '*/twofactor/confirm',   2, 'twofactor_confirm'      ],
        [ 'POST', '*/restriction/delete',  2, 'restriction_delete'     ],
        [ 'POST', '*/restriction/cancel',  2, 'restriction_cancel'     ],
    ];

    protected static $useRepositories = [
        'ips'           => 'IPRepository',
        'emails'        => 'EmailRepository',
        'invites'       => 'InviteRepository',
        'sessions'      => 'SessionRepository',
        'users'         => 'UserRepository',
        'permissions'   => 'PermissionRepository',
        'restrictions'  => 'RestrictionRepository',
    ];

    protected static $useServices = [
        'auth'          => 'Auth',
        'guardian'      => 'Guardian',
        'crypto'        => 'Crypto',
        'emailManager'  => 'EmailManager',
        'inviteManager' => 'InviteManager',
        'userManager'   => 'UserManager',
        'settings'      => 'Settings',
        'security'      => 'Security',
        'flasher'       => 'Flasher',
        'secretary'     => 'Secretary',
        'tpl'           => 'TPL',
        'orm'           => 'ORM',
        'db'            => 'DB',
    ];

    public static function register(Master $master) {
        parent::register($master);
        # This registers the plugin and has nothing to do with account creation!
        $master->prependRoute([ '*', 'users/**', 0, 'plugin', 'Users' ]);
    }

    public function legacy_redirect($userID) {
        $userID = intval($userID);
        return new Redirect("/user.php?id={$userID}");
    }

    /**
     * Function to redirect users to their own specific page, for example
     * /users/security should redirect to /users/1234/security.
     *
     * @return Redirect
     */
    public function generic_redirect() {
        $userID = $this->request->user->ID;
        $path   = implode('/', array_slice($this->request->path, 1));
        return new Redirect("/users/{$userID}/{$path}");
    }

    public function sessions_form($userID) {
        if ($userID != $this->request->user->ID) {
            $this->auth->checkAllowed('users_mod');
            $this->auth->checkLevel($userID);
        }
        list($sessions, $sessionCount) = $this->sessions->find_count('UserID = :userID', [':userID' => intval($userID)]);
        foreach ($sessions as $session) {
            $session->IP = $this->ips->load($session->IPID);
        }
        return new Rendered('@Users/user_sessions.html.twig', ['Sessions' => $sessions, 'SessionCount' => $sessionCount]);
    }

    public function twofactor_enable($userID) {
        if ($userID != $this->request->user->ID) {
            throw new ForbiddenError();
        }
        $user = $this->users->load($userID);
        $secret = $this->auth->twofactor_createSecret();
        $protected = $this->crypto->encrypt($secret, 'default', true);
        return new Rendered('@Users/twofactor_enable.html.twig', ['user' => $user, 'secret' => $secret, 'protected' => $protected]);
    }

    public function twofactor_confirm($userID) {
        if ($userID != $this->request->user->ID) {
            throw new ForbiddenError();
        }
        $token = $this->request->post['token'];
        $protected = $this->request->post['protected'];
        $user = $this->users->load($userID);
        $secret = $this->crypto->decrypt($protected, 'default', true);
        $this->secretary->checkToken($token, 'users.twofactor.enable', 600);
        $code = $this->request->post['confirm_code'];
        if ($this->auth->twofactor_enable($user, $secret, $code)) {
            $this->security->log->twoFactorEnabling($user->ID);
            $this->flasher->notice('Two factor authentication enabled');
        } else {
            $this->flasher->error('Invalid or expired two factor confirmation code!');
        }
        return new Redirect("/users/{$userID}/security");
    }

    public function twofactor_disable_form($userID) {
        if ($userID != $this->request->user->ID) {
            $this->auth->checkAllowed('users_edit_2fa');
            $this->auth->checkLevel($userID);
        }
        $user = $this->users->load($userID);
        return new Rendered('@Users/twofactor_disable.html.twig', ['user' => $user]);
    }

    public function twofactor_disable($userID) {
        if ($userID != $this->request->user->ID) {
            $this->auth->checkAllowed('users_edit_2fa');
            $this->auth->checkLevel($userID);
        }
        $user = $this->users->load($userID);
        $token = $this->request->post['token'];
        $code = $this->request->post['confirm_code'];
        $this->secretary->checkToken($token, 'users.twofactor.disable', 600);
        if ($this->auth->twofactor_disable($user, $code)) {
            $this->security->log->twoFactorDisabling($user->ID);
            $this->flasher->notice('Two factor authentication disabled');
        } else {
            $this->flasher->error('Invalid or expired two factor confirmation code!');
        }
        return new Redirect("/users/{$userID}/security");
    }

    public function security_form($userID) {
        if ($userID != $this->request->user->ID) {
            $this->auth->checkAllowed('users_view_email');
            $this->auth->checkLevel($userID);
        }
        if ($userID == $this->request->user->ID) {
            $ownProfile = true;
            $logs = false;
        } else {
            $ownProfile = false;
            // Get security logs for that user
            $logs = $this->security->getLogs($userID);
        }
        if (($userID == $this->request->user->ID) || $this->auth->isAllowed('users_edit_email')) {
            $controls['email'] = true;
        }
        if (($userID == $this->request->user->ID) || $this->auth->isAllowed('users_edit_2fa')) {
            $controls['tfa'] = true;
        }

        $user = $this->users->load(intval($userID));
        $emails = $this->emails->find('UserID = :userID', [':userID'=>intval($userID)]);

        foreach ($emails as $email) {
            $email->IP = $this->ips->load($email->IPID);
        }

        $bscripts = ['jquery', 'jquery.modal'];
        $data = [
            'emails'     => $emails,
            'user'       => $user,
            'controls'   => $controls,
            'ownProfile' => $ownProfile,
            'bscripts'   => $bscripts,
            'logs'       => $logs
        ];
        return new Rendered('@Users/user_security.html.twig', $data);
    }

    public function password_recover_form() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        if (!empty($this->request->getString('token'))) {
            // Decode existing token
            $token=$this->request->getString('token');
            $fullToken = $this->crypto->decrypt($token, 'default', true);
            $this->secretary->checkExternalToken($fullToken['token'], $fullToken['userID'], 'users.password.recover', 600);
            $user = $this->users->load($fullToken['userID']);
            return new Rendered('@Users/password_change.html.twig', ['token' => $token, 'user' =>$user]);
        } else {
            return new Rendered('@Users/password_recover.html.twig', []);
        }
    }

    public function password_recover() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        try {
            if (!empty($this->request->getString('email'))) {
                $this->secretary->checkToken($this->request->getString('token'), 'users.password.recover', 600);
                $address = $this->request->getString('email');

                // Try to find the email, and if we do - load it fully
                if ($email = $this->emails->get('Address = :address', [':address'=>$address])) {
                    $email = $this->emails->load($email->ID);
                }

                if (is_null($email)) {
                    $this->guardian->log_attempt('recover', 0);
                } else {
                    $this->guardian->log_attempt('recover', $email->UserID);
                }

                // Silently fail for unregistered or cancelled email addresses
                if (is_null($email)) {
                    $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                    return new Redirect("/");
                } elseif ($email->getFlag(Email::CANCELLED)) {
                    $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                    return new Redirect("/");
                }

                $user = $this->users->load(intval($email->UserID));
                if (!$user) {
                    $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                    return new Redirect("/");
                }

                // Populate email_body stuff first
                $subject = 'Forgotten Password';
                $variables = [];
                $variables['user']     = $user;
                $variables['scheme']   = $this->request->ssl ? 'https' : 'http';

                // Use the current host if we can find it.
                if (empty($this->request->host)) {
                    $variables['host']     = $this->settings->main->site_url;
                } else {
                    $variables['host']     = $this->request->host;
                }

                if ($user->legacy['Enabled'] != '1') {
                    $email->send_email($subject, 'disabled_reset', $variables);
                    write_user_log($email->UserID, 'Someone requested email password recovery, disabled email sent');
                } else {
                    # Gen token after checks to prevent excessive load
                    $token = $this->secretary->getExternalToken($user->ID, "users.password.recover");
                    $token = $this->crypto->encrypt(['userID' => $user->ID, 'emailID' => $email->ID, 'token' => $token], 'default', true);

                    $variables['token'] = $token;
                    $email->send_email($subject, 'password_reset', $variables);
                    write_user_log($email->UserID, 'Someone requested email password recovery, recovery email sent');
                }

                # *ALWAYS* say we sent an email.
                $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                return new Redirect("/");
            } else {
                $token = $this->request->getString('token');
                $fullToken = $this->crypto->decrypt($token, 'default', true);
                $this->secretary->checkExternalToken($fullToken['token'], $fullToken['userID'], 'users.password.recover', 600);
                $user = $this->users->load($fullToken['userID']);
                $password = $this->request->getString('password');
                $checkPassword = $this->request->getString('check_password');

                // Silently verify email on reset
                $email = $this->emails->load($fullToken['emailID']);
                if (!$email->getFlag(Email::VALIDATED)) {
                    $email->setFlags(Email::VALIDATED);
                    $this->emails->save($email);
                }
                if (is_null($user)) {
                    throw new UserError("Account could not be found!");
                }
                if ($user->legacy['Enabled'] != '1') {
                    throw new UserError("Account has been disabled.");
                }
                if (!strlen($password) || !strlen($checkPassword)) {
                    throw new UserError("Please enter new password, twice");
                }
                if ($password !== $checkPassword) {
                    throw new UserError("Passwords don't match");
                }

                $this->security->checkPasswordStrength($password);

                if ($this->security->passwordIsPwned($password, $user)) {
                    throw new UserError('This password has been found in different site breaches. You must choose another one.');
                }

                $this->guardian->log_reset($user->ID);

                $this->auth->set_password($user, $password);
                $this->users->save($user);
                $this->security->log->passwordReset($user->ID);
                $this->flasher->notice("Password updated, please login now.");

                write_user_log($user->ID, 'User reset password using email recovery link');

                return new Redirect("/login");
            }
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/recover");
        }
    }

    public function password_change($userID) {
        if ($userID != $this->request->user->ID) {
            $this->auth->checkAllowed('users_mod');
            $this->auth->checkLevel($userID);
        }
        try {
            $this->secretary->checkToken($this->request->getString('token'), 'users.password.change', 600);
            $user = $this->users->load(intval($userID));
            $password = $this->request->getString('password');
            $checkPassword = $this->request->getString('check_password');
            $oldPassword = $this->request->getString('old_password');
            if (!strlen($password) || !strlen($checkPassword)) {
                throw new UserError("Please enter new password, twice");
            }
            if ($password !== $checkPassword) {
                throw new UserError("Passwords don't match");
            }
            if (!$this->auth->check_password($user, $oldPassword)) {
                throw new UserError("Incorrect old password entered");
            }

            $this->security->checkPasswordStrength($password);

            $this->auth->set_password($user, $password);
            $this->users->save($user);
            $this->security->log->passwordChange($user->ID);
            $this->flasher->notice("Password updated");
            return new Redirect("/users/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/{$userID}/security");
        }
    }


    /**
     * The invites page of a specific user
     *
     * @param $userID
     * @return Rendered
     */
    public function invite_form($userID) {
        // Owner & staff only
        if ($userID != $this->request->user->ID) {
            $this->auth->checkAllowed('users_view_invites');
            $this->auth->checkLevel($userID);
        }

        $page = $this->request->get_int('page');
        if (!$page) {
            $page = 1;
        }

        $canInvite = $this->userManager->canInvite($userID);
        $pendingInvites = $this->invites->getByInviter($userID);
        list($results, $invitees) = $this->users->invitedBy($userID, $page);

        $pages=get_pages($page, $results, 50, 13);

        $vars = compact('pendingInvites', 'invitees', 'userID', 'canInvite', 'pages');
        return new Rendered('@Users/invite.html.twig', $vars);
    }

    /**
     * Create a new invite
     *
     * @param $userID
     * @return Redirect
     */
    public function invite_send($userID) {
        // Owner only
        if ($userID != $this->request->user->ID) {
            return new Redirect("/users/{$this->request->user->ID}/invite");
        }

        // CSRF check
        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'users.invite.send', 600);

        // Check if the user can invite people
        if (!$this->userManager->canInvite($userID)) {
            $this->flasher->error('You cannot invite anyone');
            return new Redirect("/users/{$userID}/invite");
        }

        $email = trim($this->request->getString('email'));
        $this->emailManager->validate($email);

        // Check if no invite already exists for that e-mail
        if ($this->invites->getByAddress($email) instanceof Invite) {
            $this->flasher->error('An invite already exists with that e-mail');
            return new Redirect("/users/{$userID}/invite");
        }

        // Create the invite instance and take it back from user's count
        $invite = $this->inviteManager->newInvite($userID, $email);
        $this->inviteManager->takeInvite($userID);

        // Send a new invite e-mail
        $this->emailManager->sendInviteEmail(
            $invite,
            $email,
            $this->request->user->Username,
            !empty($this->request->getString('anon'))
        );

        $this->flasher->success("The invite was successfully sent");
        return new Redirect("/users/{$userID}/invite");
    }

    /**
     * Delete a user's pending invite
     *
     * @param $userID
     * @return Redirect
     */
    public function invite_delete($userID) {
        // Owner & staff only
        if ($userID != $this->request->user->ID) {
            $this->auth->checkAllowed('users_view_invites');
            $this->auth->checkLevel($userID);
        }

        // CSRF check
        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'users.invite.delete', 600);

        $inviteID = $this->request->getString('inviteID');
        $invite = $this->invites->load($inviteID);

        // Check that the invite belongs to the user
        if (!$invite || $invite->InviterID != $userID) {
            $this->flasher->error("Invalid invite !");
            return new Redirect("/users/{$userID}/invite");
        }

        $this->invites->delete($invite);

        // Give the user's invite back if he's limited
        if (!$this->auth->isAllowed('site_send_unlimited_invites')) {
            $this->inviteManager->giveInvite($userID);
        }

        $this->flasher->success("The invite was successfully deleted !");
        return new Redirect("/users/{$userID}/invite");
    }

    public function email_add($userID) {
        if ($userID != $this->request->user->ID) {
            $this->auth->checkAllowed('users_edit_email');
            $this->auth->checkLevel($userID);
        }
        try {
            $this->secretary->checkToken($this->request->getString('token'), 'users.email.add', 600);
            $address = $this->request->getString('address');
            $email = $this->emails->get('Address=:address', [':address' => $address]);
            if ($email) {
                if ($email->UserID != $userID) throw new UserError("This email is already registered");
                if ($email->getFlag(Email::CANCELLED)) {
                    $email->unsetFlags(Email::CANCELLED);
                    $this->emails->save($email);
                }
            } else {
                $email = $this->emailManager->newEmail(intval($userID), $address);
                $this->emailManager->send_confirmation($email->ID);
            }
            $this->security->log->newEmail((int) $userID, $address);
            return new Redirect("/users/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/{$userID}/security");
        }
    }

    public function email_resend($userID) {
        try {
            if ($userID != $this->request->user->ID) {
                $this->auth->checkAllowed('users_edit_email');
                $this->auth->checkLevel($userID);
            }
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'users.email.resend', 600);

            $emailID = $this->request->get_int('emailID');
            $email = $this->emails->load($emailID);

            // Checking and validation
            if ($userID != $email->UserID)  throw new UserError("Email does not belong to user");
            if (!$email->ready_to_resend()) throw new UserError("Cannot resend so quickly");

            $this->emailManager->send_confirmation($email->ID);

            return new Redirect("/users/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/{$userID}/security");
        }
    }

    public function email_confirm($userID) {
        try {
            $token=$this->request->getString('token');
            $fullToken = $this->crypto->decrypt($token, 'default', true);
            $this->secretary->checkExternalToken($fullToken['token'], $fullToken['email'], 'users.email.confirm');

            $this->emailManager->validateAddress($fullToken['email']);

            if ($this->request->user) {
                return new Redirect("/users/{$userID}/security");
            } else {
                return new Redirect("/");
            }
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            if ($this->request->user) {
                return new Redirect("/users/{$userID}/security");
            } else {
                return new Redirect("/");
            }
        }
    }

    public function email_restore($userID) {
        try {
            $this->auth->checkAllowed('users_edit_email');
            $this->auth->checkLevel($userID);
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'users.email.restore', 600);

            $emailID = $this->request->get_int('emailID');
            $email = $this->emails->load($emailID);

            // Checking and validation
            if ($userID != $email->UserID)  throw new UserError("Email does not belong to user");

            $email->unsetFlags(Email::CANCELLED);
            $this->emails->save($email);
            $this->security->log->restoreEmail((int) $userID, $email->Address);

            return new Redirect("/users/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/{$userID}/security");
        }
    }

    public function email_delete($userID) {
        try {
            if ($userID != $this->request->user->ID) {
                $this->auth->checkAllowed('users_edit_email');
                $this->auth->checkLevel($userID);
            }
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'users.email.delete', 600);

            $emailID = $this->request->get_int('emailID');
            $email = $this->emails->load($emailID);

            // Checking and validation
            if ($userID != $email->UserID)  throw new UserError("Email does not belong to user");
            if ($email->getFlag(Email::IS_DEFAULT)) throw new UserError("Cannot delete default email");
            if ($this->auth->isAllowed('users_edit_email') && $email->getFlag(Email::CANCELLED)) {
                $this->emails->delete($email);
                $this->security->log->deleteEmail((int) $userID, $email->Address);
            } else {
                $email->setFlags(Email::CANCELLED);
                $this->emails->save($email);
                $this->security->log->removeEmail((int) $userID, $email->Address);
            }

            return new Redirect("/users/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/{$userID}/security");
        }
    }

    public function email_default($userID) {
        try {
            if ($userID != $this->request->user->ID) {
                $this->auth->checkAllowed('users_edit_email');
                $this->auth->checkLevel($userID);
            }
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'users.email.default', 600);

            $emailID = $this->request->get_int('emailID');
            $email = $this->emails->load($emailID);

            // Checking and validation
            if ($userID != $email->UserID)  throw new UserError("Email does not belong to user");
            if ($email->getFlag(Email::CANCELLED)) throw new UserError("Can't set a deleted email to default");
            if (!$this->auth->isAllowed('users_edit_email') && !$email->getFlag(Email::VALIDATED)) throw new UserError("Can't set an unverified email to default");
            // Do the stupid shuffle
            $user = $this->users->load($userID);
            $email_default = $this->emails->load($user->EmailID);
            if ($email_default) {
                $email_default->unsetFlags(Email::IS_DEFAULT);
                $this->emails->save($email_default);
            }
            $email->setFlags(Email::IS_DEFAULT);
            $user->EmailID = $email->ID;
            $this->emails->save($email);
            $this->users->save($user);

            $this->db->raw_query("UPDATE users_main SET Email=:email WHERE ID=:userID", [':userID' => $user->ID, ':email' => $email->Address]);

            return new Redirect("/users/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/{$userID}/security");
        }
    }

    public function user_register_form() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        if (!empty($this->request->getString('invite'))) {
            $token=$this->request->getString('invite');
            $fullToken = $this->crypto->decrypt($token, 'default', true);
            $this->secretary->checkExternalToken($fullToken['token'], $fullToken['email'], 'users.register', 259200); // 3 days

            $invite = $this->invites->get('InviteKey = :invite', [':invite'=>$fullToken['token']]);
            // First invite only!
            if (empty($invite) || $invite->hasExpired() || getUserEnabled($invite->InviterID) != 1) {
                $this->flasher->error("Invite is invalid or expired.");
                return new Redirect("/");
            }

            // Generate a new token, should be considered valid for 1 day.
            $token = $this->secretary->getExternalToken($fullToken['email'], 'users.register');
            $token = $this->crypto->encrypt(['email' => $fullToken['email'], 'token' => $token, 'invite' => $invite->ID], 'default', true);

            return new Redirect("/users/create?token={$token}");
        }

        return new Rendered('@Users/user_register.html.twig', []);
    }

    public function user_register() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        if ($this->settings->site->open_registration) {
            $email = $this->request->getString('email');

            $this->emails->checkAvailable($email);
            $token = $this->secretary->getExternalToken($email, 'users.register');
            $token = $this->crypto->encrypt(['email' => $email, 'token' => $token], 'default', true);

            $subject = 'New account confirmation';
            $variables = [];
            $variables['token']    = $token;
            $variables['settings'] = $this->settings;
            $variables['scheme']   = $this->request->ssl ? 'https' : 'http';

            if ($this->settings->site->debug_mode) {
                $body = $this->tpl->render('email/new_registration.flash.twig', $variables);
                $this->flasher->notice($body);
            } else {
                $body = $this->tpl->render('email/new_registration.email.twig', $variables);
                $this->emailManager->send_email($email, $subject, $body);
                $this->flasher->notice("An e-mail with further instructions has been sent to the provided address.");
            }
        }
        return new Redirect("/");
    }

    public function user_create_form() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        try {
            $token=$this->request->getString('token');
            $fullToken = $this->crypto->decrypt($token, 'default', true);
            $this->secretary->checkExternalToken($fullToken['token'], $fullToken['email'], 'users.register', 86400); // 1 days
            return new Rendered('@Users/user_create.html.twig', ['email' => $fullToken['email'], 'token' => $token]);
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/");
        }
    }

    public function user_create() {
        $inviter = 0;
        $token   = $this->request->getString('token');
        $fullToken = $this->crypto->decrypt($token, 'default', true);
        $this->secretary->checkExternalToken($fullToken['token'], $fullToken['email'], 'users.register', 86400);
        $email   = $fullToken['email'];

        if (array_key_exists('invite', $fullToken)) {
            // Check the invite is valid, first invite only!
            // Force load from DB
            $this->invites->disable_cache();
            $invite = $this->invites->load($fullToken['invite']);
            if (!$invite || $invite->hasExpired() || getUserEnabled($invite->InviterID) != 1) {
                $this->flasher->error("Invite is invalid or expired.");
                return new Redirect("/");
            }
            $inviter = $invite->InviterID;
        } else {
            $inviter = 0;
        }

        try {
            $username = $this->request->getString('username');
            $password = $this->request->getString('password');
            $password_check = $this->request->getString('password_check');
            if ($password !== $password_check) {
                throw new InputError("Passwords do not match.");
            }

            $this->security->checkPasswordStrength($password);

            $user  = $this->auth->createUser($username, $password, $email, $inviter);
            if ($invite) $this->orm->delete($invite);
            return new Redirect("/login");
        } catch (InputError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/create?email={$email}&token={$token}");
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/");
        }
    }

    public function restriction_delete($userID) {
        $this->auth->checkAllowed('users_disable_any');
        $this->auth->checkLevel($userID);

        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'users.restriction.delete', 600);

        $restrictionID = $this->request->get_int('restrictionID');
        $restriction = $this->restrictions->load($restrictionID);

        if (!$restriction) {
            throw new NotFoundError('Restriction ID could not be found');
        }

        // Check that the restriction belongs to the user
        $this->restrictions->check_user($restriction, $userID);

        $this->restrictions->delete($restriction);
        return new Redirect("/user.php?id={$userID}#restrictionsdiv");
    }

    public function restriction_cancel($userID) {
        $this->auth->checkAllowed('users_disable_any');
        $this->auth->checkLevel($userID);

        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'users.restriction.cancel', 600);

        $restrictionID = $this->request->get_int('restrictionID');
        $restriction = $this->restrictions->load($restrictionID);

        if (!$restriction) {
            throw new NotFoundError('Restriction ID could not be found');
        }

        // Check that the restriction belongs to the user
        $this->restrictions->check_user($restriction, $userID);

        $restriction->Expires = new \DateTime();
        $this->restrictions->save($restriction);

        return new Redirect("/user.php?id={$userID}#restrictionsdiv");
    }
}
