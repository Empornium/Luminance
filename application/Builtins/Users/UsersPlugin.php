<?php
namespace Luminance\Builtins\Users;

use Luminance\Core\Master;
use Luminance\Core\Plugin;
use Luminance\Errors\InternalError;
use Luminance\Errors\InputError;
use Luminance\Errors\NotFoundError;
use Luminance\Errors\UserError;
use Luminance\Entities\Email;
use Luminance\Entities\Invite;

use Luminance\Responses\Response;
use Luminance\Responses\Redirect;
use Luminance\Responses\Rendered;

class UsersPlugin extends Plugin {

    public $routes = [
        # [method] [path match] [target function] [auth level] <extra arguments>
        [ 'GET',  'register',   0, 'registerForm'   ],
        [ 'POST', 'register',   0, 'doRegister'     ],
        [ 'GET',  'create',     0, 'createForm'     ],
        [ 'POST', 'create',     0, 'doCreate'       ],
        [ 'GET',  'recover',    0, 'recoverForm'    ],
        [ 'POST', 'recover',    0, 'doRecover'      ],

        [ 'GET',  '*/security', 3, 'securityForm'   ],
        [ 'POST', '*/password', 3, 'changePassword' ],
        [ 'POST', '*/email',    3, 'changeEmail'    ],
        [ 'GET',  '*/sessions', 3, 'listSessions'   ],
        [ 'GET',  '*',          0, 'legacyRedirect' ],
    ];

    protected static $useRepositories = [
        'emails'   => 'EmailRepository',
        'invites'  => 'InviteRepository',
        'sessions' => 'SessionRepository',
    ];

    protected static $useServices = [
        'auth'          => 'Auth',
        'guardian'      => 'Guardian',
        'crypto'        => 'Crypto',
        'emailManager'  => 'EmailManager',
        'inviteManager' => 'InviteManager',
        'settings'      => 'Settings',
        'flasher'       => 'Flasher',
        'secretary'     => 'Secretary',
        'tpl'           => 'TPL',
        'orm'           => 'ORM',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->users = $this->master->repos->users;
    }

    public static function register(Master $master) {
        # This registers the plugin and has nothing to do with account creation!
        $master->prependRoute([ '*', 'users/**', 0, 'plugin', 'Users' ]);
    }

    public function legacyRedirect($UserID) {
        $UserID = intval($UserID);
        return new Redirect("/user.php?id={$UserID}");
    }

    public function listSessions($UserID) {
        $this->auth->checkAllowed('users_mod');
        list($Sessions, $SessionCount) = $this->sessions->find_count();
        return new Rendered('@Users/sessions.html', ['Sessions' => $Sessions, 'SessionCount' => $SessionCount]);
    }

    public function securityForm($UserID) {
        $user = $this->users->load(intval($UserID));
        $email = $this->emails->load($user->EmailID);
        $emails = $this->emails->find('UserID = :UserID', [':UserID'=>intval($UserID)]);
        $this->auth->checkAllowed('users_mod');

        $data = [
            'email' => $email,
            'emails' => $emails,
            'user' => $user,
        ];
        return new Rendered('@Users/security.html', $data);
    }

    public function recoverForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        if (!empty($this->request->getString('token'))) {
                $token=$this->request->getString('token');
        	$this->secretary->checkToken($token, 'users.recoverPassword', 600);
        	return new Rendered('@Users/password.html', ['token' => $token]);
	} else {
        	return new Rendered('@Users/recover.html', []);
	}
    }

    public function doRecover() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        try {
            if (!empty($this->request->getString('email'))) {
                $this->guardian->check_ip_ban();
                $this->guardian->detect();
                $this->guardian->log_recover($this->request->getString('email'));

                $this->secretary->checkToken($this->request->getString('token'), 'users.recoverPassword', 600);
                $address = $this->request->getString('email');

                $email = $this->emails->get('Address = :address', [':address'=>$address]);
                if(is_null($email)) throw new UserError("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                $user = $this->users->load(intval($email->UserID));

                # Gen token after checks to prevent excessive load
                $token = $this->secretary->getToken("users.recoverPassword");

                // Stash token
                $user->ResetToken = $token;
                $this->users->save($user);

                $subject = 'Forgotten Password';
                $email_body = [];
                $email_body['token']    = $token;
                $email_body['user']     = $user;
                $email_body['ip']       = $this->request->IP;
                $email_body['settings'] = $this->settings;
                $email_body['scheme']   = $this->request->ssl ? 'https' : 'http';

                if ($this->settings->site->debug_mode) {
                    $body = $this->tpl->render('password_reset.flash', $email_body);
                    $this->flasher->notice($body);
                } else {
                    $body = $this->tpl->render('password_reset.email', $email_body);
                    $this->secretary->send_email($email->Address, $subject, $body);
                    $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                }

                write_user_log($email->UserID, 'Someone requested email password recovery, email sent');

                return new Redirect("/");

            } else {
                $token = $this->request->getString('token');
                $this->secretary->checkToken($token, 'users.recoverPassword', 600);
                $UserID = $this->users->get('ResetToken = :token', [':token'=>$token])->ID;
                $user = $this->users->load($UserID);
                $password = $this->request->getString('password');
                $checkPassword = $this->request->getString('check_password');
                if (is_null($user)) {
                    throw new UserError("Expired recovery link!");
                }
                $userInfo = $user->info();
                if (!empty($userInfo) && $userInfo['Enabled'] != '1') {
                    throw new UserError("Account has been disabled.");
                }
                if (!strlen($password) || !strlen($checkPassword)) {
                    throw new UserError("Please enter new password, twice");
                }
                if ($password !== $checkPassword) {
                    throw new UserError("Passwords don't match");
                }
                $this->auth->set_password($user->ID, $password);
                $this->flasher->notice("Password updated, please login now.");

                write_user_log($user->ID, 'User reset password using email recovery link');

                return new Redirect("/login");

            }

        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/recover");
        }
    }

    public function changePassword($UserID) {
        try {
            $this->secretary->checkToken($this->request->getString('token'), 'users.changePassword', 600);
            $user = $this->users->load(intval($UserID));
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
            print("Changing");exit;
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/{$userID}/security");
        }
    }

    public function changeEmail($userID) {
        try {
            $this->secretary->checkToken($this->request->getString('token'), 'users.changeEmail', 600);
            $email = $this->emailManager->newEmail(intval($userID), $this->request->getString('address'));
            $this->flasher->notice("E-mail change has been requested. Please check your mailbox for the validation link. {$email->ID}");
            return new Redirect("/users/{$userID}/security");

        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/users/{$userID}/security");
        }

    }

    public function registerForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        if (!empty($this->request->getString('invite'))) {
            // First invite only!
            $invite = $this->invites->get('InviteKey = :invite', [':invite'=>$this->request->getString('invite')]);
            if (empty($invite) ||$invite->hasExpired()) {
                $this->flasher->error("Invite is invalid or expired.");
                return new Redirect("/");
            } else {
                $email = $invite->Email;
                $token = $this->secretary->getToken('users.register-'.$email);
                $invite->Token = $token;
                $this->orm->save($invite);
                return new Redirect("/users/create?email={$email}&token={$token}");
            }
        }

        return new Rendered('@Users/register.html', []);
    }

    public function doRegister() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        if ($this->settings->site->open_registration) {
            $email = $this->request->getString('email');
            $token = $this->secretary->getToken('users.register-'.$email);

            if (!$this->emailManager->checkEmailAvailable($email)) {
                throw new InputError("That e-mail address is no longer available. Please register using another address.");
            }

            $subject = 'New account confirmation';
            $email_body = [];
            $email_body['token']    = $token;
            $email_body['email']    = urlencode(base64_encode($email));
            $email_body['settings'] = $this->settings;
            $email_body['scheme']   = $this->request->ssl ? 'https' : 'http';

            if ($this->settings->site->debug_mode) {
                $body = $this->tpl->render('new_registration.flash', $email_body);
                $this->flasher->notice($body);
            } else {
                $body = $this->tpl->render('new_registration.email', $email_body);
                $this->secretary->send_email($email, $subject, $body);
                $this->flasher->notice("An e-mail with further instructions has been sent to the provided address.");
            }
        }
        return new Redirect("/");
    }

    public function createForm() {
        # This is a lot safer than it looks: changing the e-mail address will invalidate the token.
        $email = base64_decode($this->request->getString('email'));
        try {
            $token = $this->request->getString('token');
            $invite = $this->invites->get('Token = :token', [':token'=>$token]);
            if (!empty($invite)) {
                $email = $invite->Email;
            }
            $this->secretary->checkToken($token, 'users.register-'.$email, 7200);
            return new Rendered('@Users/create.html', ['email' => $email, 'registerToken' => $token]);
        } catch (UserError $e) {
            $this->flasher->error($e->public_message);
            return new Redirect("/");
        }
    }

    public function doCreate() {
        $email   = $this->request->getString('email');
        $inviter = 0;
        $token   = $this->request->getString('registerToken');
        $invite = $this->invites->get('Email = :email', [':email'=>$email]);
        if ($invite) {
            $email   = $invite->Email;
            $inviter = $invite->InviterID;
        }
        try {
            $this->secretary->checkToken($this->request->getString('token'), 'users.create-'.$email);
            $username = $this->request->getString('username');
            if (!$this->auth->checkUsernameAvailable($username)) {
                throw new InputError("That username is not available.");
            }
            if (!$this->emailManager->checkEmailAvailable($email)) {
                throw new InputError("That e-mail address is no longer available. Please register using another address.");
            }

            $password = $this->request->getString('password');
            $password_check = $this->request->getString('password_check');
            if ($password !== $password_check) {
                throw new InputError("Passwords do not match.");
            }
            $user  = $this->auth->createUser($username, $password, $email, $inviter);
            $email = $this->emailManager->newEmail(intval($user->ID), $email);
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

}
