<?php
namespace Luminance\Plugins\User;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Errors\Error;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\InputError;
use Luminance\Errors\NotFoundError;
use Luminance\Errors\UserError;

use Luminance\Entities\Email;
use Luminance\Entities\Invite;
use Luminance\Entities\User;
use Luminance\Entities\APIKey;
use Luminance\Entities\Restriction;
use Luminance\Entities\Reminder;
use Luminance\Entities\UserInboxSubject;
use Luminance\Entities\UserInboxMessage;
use Luminance\Entities\UserInboxConversation;

use Luminance\Services\Auth;

use Luminance\Responses\JSON;
use Luminance\Responses\Redirect;
use Luminance\Responses\Rendered;
use Luminance\Responses\Response;

use Luminance\Legacy\Text;

class UserPlugin extends Plugin {


    protected static $defaultOptions = [
        'UsersLimit'             => ['value' => 5000,  'section' => 'users',  'displayRow' => 1, 'displayCol' => 1, 'type' => 'int',  'description' => 'Maximum users'],
        'UsersStartingUpload'    => ['value' => 500,   'section' => 'users',  'displayRow' => 2, 'displayCol' => 1, 'type' => 'int',  'description' => 'Initial Upload Credit (MiB)'],
        'UsersStartingInvites'   => ['value' => 0,     'section' => 'users',  'displayRow' => 2, 'displayCol' => 2, 'type' => 'int',  'description' => 'Initial Invites'],
        'UsersStartingPFLDays'   => ['value' => 0,     'section' => 'users',  'displayRow' => 2, 'displayCol' => 3, 'type' => 'int',  'description' => 'Initial Personal Freeleech (days)'],
        'UsersStartingFLTokens'  => ['value' => 0,     'section' => 'users',  'displayRow' => 2, 'displayCol' => 4, 'type' => 'int',  'description' => 'Initial Freeleech/Doubleseed Tokens'],
        'AvatarWidth'            => ['value' => 150,   'section' => 'users',  'displayRow' => 3, 'displayCol' => 1, 'type' => 'int',  'description' => 'Default Avatar Width (pix)'],
        'AvatarHeight'           => ['value' => 250,   'section' => 'users',  'displayRow' => 3, 'displayCol' => 2, 'type' => 'int',  'description' => 'Default Avatar Height (pix)'],
        'AvatarSizeKiB'          => ['value' => 1024,  'section' => 'users',  'displayRow' => 3, 'displayCol' => 3, 'type' => 'int',  'description' => 'Max Avatar Size (KiB)'],
    ];

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  'register',                     Auth::AUTH_NONE,  'userRegisterForm'         ],
        [ 'POST', 'register',                     Auth::AUTH_NONE,  'userRegister'             ],
        [ 'GET',  'create',                       Auth::AUTH_NONE,  'userCreateForm'           ],
        [ 'POST', 'create',                       Auth::AUTH_NONE,  'userCreate'               ],
        [ 'GET',  'recover',                      Auth::AUTH_NONE,  'passwordRecoverForm'      ],
        [ 'POST', 'recover',                      Auth::AUTH_NONE,  'passwordRecover'          ],
        [ 'GET',  'security',                     Auth::AUTH_LOGIN, 'genericRedirect'          ],

        [ 'GET',  '*/invite',                     Auth::AUTH_LOGIN, 'inviteForm'               ],
        [ 'GET',  '*/invite/tree',                Auth::AUTH_LOGIN, 'inviteTree'               ],
        [ 'POST', '*/invite/send',                Auth::AUTH_LOGIN, 'inviteSend'               ],
        [ 'POST', '*/invite/delete',              Auth::AUTH_LOGIN, 'inviteDelete'             ],
        [ 'POST', '*/invite/resend',              Auth::AUTH_LOGIN, 'inviteResend'             ],
        [ 'GET',  'invite/log',                   Auth::AUTH_2FA,   'inviteLogView'            ],

        [ 'GET',  'inbox/*',                      Auth::AUTH_LOGIN, 'inbox'                    ],
        [ 'POST', 'inbox/manage/read',            Auth::AUTH_LOGIN, 'inboxManageRead'          ],
        [ 'POST', 'inbox/manage/unread',          Auth::AUTH_LOGIN, 'inboxManageUnread'        ],
        [ 'POST', 'inbox/manage/sticky',          Auth::AUTH_LOGIN, 'inboxManageSticky'        ],
        [ 'POST', 'inbox/manage/unsticky',        Auth::AUTH_LOGIN, 'inboxManageUnsticky'      ],
        [ 'POST', 'inbox/manage/delete',          Auth::AUTH_LOGIN, 'inboxManageDelete'        ],
        [ 'GET',  'inbox/conversation/*',         Auth::AUTH_LOGIN, 'inboxConversation'        ],
        [ 'GET',  '*/inbox/compose',              Auth::AUTH_LOGIN, 'inboxConversationCompose' ],
        [ 'POST', '*/inbox/send',                 Auth::AUTH_LOGIN, 'inboxConversationSend'    ],
        [ 'POST', 'inbox/conversation/*/reply',   Auth::AUTH_LOGIN, 'inboxConversationReply'   ],
        [ 'POST', 'inbox/conversation/*/manage',  Auth::AUTH_LOGIN, 'inboxConversationManage'  ],
        [ 'POST', 'inbox/conversation/*/forward', Auth::AUTH_LOGIN, 'inboxConversationForward' ],
        [ 'POST', 'inbox/message/*/forward',      Auth::AUTH_LOGIN, 'inboxMessageForward'      ],
        [ 'GET',  'inbox/message/*/get',          Auth::AUTH_LOGIN, 'inboxMessageGet'          ],

        [ 'GET',  'reminders',                    Auth::AUTH_LOGIN, 'reminders'                ],
        [ 'GET',  '*/reminder',                   Auth::AUTH_LOGIN, 'reminderForm'             ],
        [ 'GET',  'new/reminder',                 Auth::AUTH_LOGIN, 'reminderNew'              ],
        [ 'POST', 'create/reminder',              Auth::AUTH_LOGIN, 'reminderCreate'           ],
        [ 'POST', '*/reminder',                   Auth::AUTH_LOGIN, 'reminderSave'             ],
        [ 'GET', '*/cancelReminder',              Auth::AUTH_LOGIN, 'reminderCancel'           ],

        [ 'GET',  '*/history/passkeys',           Auth::AUTH_2FA,   'passkeyHistory'           ],
        [ 'GET',  '*/history/apikeys',            Auth::AUTH_2FA,   'apikeyHistory'            ],
        [ 'GET',  '*/history/passwords',          Auth::AUTH_2FA,   'passwordHistory'          ],

        [ 'GET',  '*/security',                   Auth::AUTH_LOGIN, 'securityForm'             ],
        [ 'POST', '*/password/change',            Auth::AUTH_LOGIN, 'passwordChange'           ],
        [ 'GET',  '*/email/confirm',              Auth::AUTH_NONE,  'emailConfirm'             ],
        [ 'POST', '*/email/add',                  Auth::AUTH_LOGIN, 'emailAdd'                 ],
        [ 'POST', '*/email/restore',              Auth::AUTH_LOGIN, 'emailRestore'             ],
        [ 'POST', '*/email/delete',               Auth::AUTH_LOGIN, 'emailDelete'              ],
        [ 'POST', '*/email/resend',               Auth::AUTH_LOGIN, 'emailResend'              ],
        [ 'POST', '*/email/default',              Auth::AUTH_LOGIN, 'emailDefault'             ],
        [ 'GET',  '*/sessions',                   Auth::AUTH_2FA,   'sessionsForm'             ],
        [ 'GET',  '*/twofactor/enable',           Auth::AUTH_LOGIN, 'twofactorEnable'          ],
        [ 'GET',  '*/twofactor/disable',          Auth::AUTH_LOGIN, 'twofactorDisableForm'     ],
        [ 'POST', '*/twofactor/disable',          Auth::AUTH_LOGIN, 'twofactorDisable'         ],
        [ 'POST', '*/twofactor/confirm',          Auth::AUTH_LOGIN, 'twofactorConfirm'         ],
        [ 'POST', '*/irc/auth',                   Auth::AUTH_LOGIN, 'ircAuth'                  ],
        [ 'POST', '*/irc/deauth',                 Auth::AUTH_LOGIN, 'ircDeauth'                ],
        [ 'POST', '*/apikey/add',                 Auth::AUTH_LOGIN, 'apiKeyAdd'                ],
        [ 'POST', '*/apikey/restore',             Auth::AUTH_LOGIN, 'apiKeyRestore'            ],
        [ 'POST', '*/apikey/delete',              Auth::AUTH_LOGIN, 'apiKeyDelete'             ],
        [ 'POST', '*/restriction/delete',         Auth::AUTH_LOGIN, 'restrictionDelete'        ],
        [ 'POST', '*/restriction/cancel',         Auth::AUTH_LOGIN, 'restrictionCancel'        ],
        [ 'GET',  '*/dump',                       Auth::AUTH_2FA,   'dumpUser'                 ],
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
        'irker'         => 'Irker',
        'tpl'           => 'TPL',
        'orm'           => 'ORM',
        'db'            => 'DB',
        'render'        => 'Render',
        'repos'         => 'Repos',
        'options'       => 'Options',
        'cache'         => 'Cache',
    ];

    public static function register(Master $master) {
        parent::register($master);
        # This registers the plugin and has nothing to do with account creation!
        $master->prependRoute([ '*', 'user/**', Auth::AUTH_NONE, 'plugin', 'User' ]);
    }

    public function legacyRedirect($userID) {
        $userID = intval($userID);
        return new Redirect("/user.php?id={$userID}");
    }

    /**
     * Function to redirect users to their own specific page, for example
     * /user/security should redirect to /user/1234/security.
     *
     * @return Redirect
     */
    public function genericRedirect() {
        $userID = $this->request->user->ID;
        $path   = implode('/', array_slice($this->request->path, 1));
        return new Redirect("/user/{$userID}/{$path}");
    }

    public function sessionsForm($userID) {
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_mod');
            $this->auth->checkUserLevel($userID);
        }
        list($sessions, $sessionCount) = $this->repos->sessions->find_count('UserID = :userID', [':userID' => intval($userID)]);
        foreach ($sessions as $session) {
            $session->IP = $this->repos->ips->load($session->IPID);
        }
        return new Rendered('@User/user_sessions.html.twig', ['Sessions' => $sessions, 'SessionCount' => $sessionCount]);
    }

    public function twofactorEnable($userID) {
        if (!((int)$userID === $this->request->user->ID)) {
            throw new ForbiddenError();
        }
        $user = $this->repos->users->load($userID);
        $secret = $this->auth->twofactorCreateSecret();
        $protected = $this->crypto->encrypt($secret, 'default', true);
        return new Rendered('@User/twofactor_enable.html.twig', ['user' => $user, 'secret' => $secret, 'protected' => $protected]);
    }

    public function twofactorConfirm($userID) {
        if (!((int)$userID === $this->request->user->ID)) {
            throw new ForbiddenError();
        }
        $token = $this->request->getPostString('token');
        $protected = $this->request->getPostString('protected');
        $user = $this->repos->users->load($userID);
        $secret = $this->crypto->decrypt($protected, 'default', true);
        $this->secretary->checkToken($token, 'user.twofactor.enable', 600);
        $code = $this->request->getPostString('confirm_code');
        if ($this->auth->twofactorEnable($user, $secret, $code)) {
            $this->repos->securityLogs->twoFactorEnabling($user->ID);
            $this->flasher->notice('Two factor authentication enabled');
        } else {
            $this->flasher->error('Invalid or expired two factor confirmation code!');
        }
        return new Redirect("/user/{$userID}/security");
    }

    public function twofactorDisableForm($userID) {
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_edit_2fa');
            $this->auth->checkUserLevel($userID);
        }
        $user = $this->repos->users->load($userID);
        return new Rendered('@User/twofactor_disable.html.twig', ['user' => $user]);
    }

    public function twofactorDisable($userID) {
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_edit_2fa');
            $this->auth->checkUserLevel($userID);
        }
        $user = $this->repos->users->load($userID);
        $token = $this->request->getPostString('token');
        $code = $this->request->getPostString('confirm_code');
        $this->secretary->checkToken($token, 'user.twofactor.disable', 600);
        if ($this->auth->twofactorDisable($user, $code)) {
            $this->repos->securityLogs->twoFactorDisabling($user->ID);
            $this->flasher->notice('Two factor authentication disabled');
        } else {
            $this->flasher->error('Invalid or expired two factor confirmation code!');
        }
        return new Redirect("/user/{$userID}/security");
    }

    public function ircAuth($userID) {
        if ($this->options->AuthUserEnable = true) {
            if (!((int)$userID === $this->request->user->ID)) {
                $this->auth->checkAllowed('users_edit_irc');
                $this->auth->checkUserLevel($userID);
            }

            $user = $this->repos->users->load($userID);
            $nick = $user->Username;

            # Staff can set any nick, even on their own profile
            if ($this->auth->isAllowed('users_edit_irc')) {
                $nick = $this->request->getPostString('irc_nick');
            }

            $token = $this->request->getPostString('token');
            $this->secretary->checkToken($token, 'user.irc.auth', 600);
            $this->irker->authUser($nick);
            $user->IRCNick = $nick;
            $this->repos->users->save($user);
            $this->repos->securityLogs->ircAuth($user->ID, $nick);
            $this->flasher->notice('IRC authenticated');
        } else {
            $this->flasher->error('IRC authentication integration is disabled');
        }
        return new Redirect("/user/{$userID}/security");
    }

    public function ircDeauth($userID) {
        if ($this->options->AuthUserEnable = true) {
            if (!((int)$userID === $this->request->user->ID)) {
                $this->auth->checkAllowed('users_edit_irc');
                $this->auth->checkUserLevel($userID);
            }
            $user = $this->repos->users->load($userID);
            $token = $this->request->getPostString('token');
            $nick = $user->IRCNick;
            $this->secretary->checkToken($token, 'user.irc.deauth', 600);
            $this->irker->deauthUser($nick);
            $user->IRCNick = null;
            $this->repos->users->save($user);
            $this->repos->securityLogs->ircDeauth($user->ID, $nick);
            $this->flasher->notice('IRC deauthenticated');
        } else {
            $this->flasher->error('IRC authentication integration is disabled');
        }
        return new Redirect("/user/{$userID}/security");
    }

    public function apiKeyAdd($userID) {
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_edit_apikey');
            $this->auth->checkUserLevel($userID);
        }
        try {
            $this->secretary->checkToken($this->request->getString('token'), 'user.apikey.add', 600);
            $description = $this->request->getString('description');
            $apiKey = new APIKey([
                'UserID'      => intval($userID),
                'IPID'        => (!(empty($this->request->ip))) ? $this->request->ip->ID : 0,
                'Key'         => $this->crypto->randomString(32),
                'Description' => $description,
                'Created'     => new \DateTime(),
                'Flags'       => 0,
            ]);
            $this->repos->apiKeys->save($apiKey);

            $this->repos->securityLogs->newAPIKey((int) $userID, $apiKey->Key);
            return new Redirect("/user/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/{$userID}/security");
        }
    }

    public function apiKeyRestore($userID) {
        try {
            $this->auth->checkAllowed('users_edit_apikey');
            $this->auth->checkUserLevel($userID);
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'user.apikey.restore', 600);

            $apiKeyID = $this->request->getInt('apikeyID');
            $apiKey = $this->repos->apiKeys->load($apiKeyID);

            # Checking and validation
            if (!((int)$userID === $apiKey->UserID)) throw new UserError("API Key does not belong to user");

            $apiKey->unsetFlags(APIKey::CANCELLED);
            $this->repos->apiKeys->save($apiKey);
            $this->repos->securityLogs->restoreAPIKey((int) $userID, $apiKey->Key);

            return new Redirect("/user/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/{$userID}/security");
        }
    }

    public function apiKeyDelete($userID) {
        try {
            if (!((int)$userID === $this->request->user->ID)) {
                $this->auth->checkAllowed('users_edit_apikey');
                $this->auth->checkUserLevel($userID);
            }
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'user.apikey.delete', 600);

            $apiKeyID = $this->request->getInt('apikeyID');
            $apiKey = $this->repos->apiKeys->load($apiKeyID);

            # Checking and validation
            if (!((int)$userID === $apiKey->UserID)) throw new UserError("API Key does not belong to user");
            if ($this->auth->isAllowed('users_edit_apikey') && $apiKey->getFlag(APIKey::CANCELLED)) {
                $this->repos->apiKeys->delete($apiKey);
                $this->repos->securityLogs->deleteAPIKey((int) $userID, $apiKey->Key);
            } else {
                $apiKey->setFlags(APIKey::CANCELLED);
                $this->repos->apiKeys->save($apiKey);
                $this->repos->securityLogs->removeAPIKey((int) $userID, $apiKey->Key);
            }

            return new Redirect("/user/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/{$userID}/security");
        }
    }

    public function securityForm($userID) {
        $ownProfile = ((int)$userID === $this->request->user->ID);
        if ($ownProfile === false) {
            $this->auth->checkAllowed('users_view_email');
            $this->auth->checkUserLevel($userID);
        }
        $controls = [];

        if ($ownProfile === true || $this->auth->isAllowed('users_edit_email')) {
            $controls['email'] = true;
        }
        if ($ownProfile === true || $this->auth->isAllowed('users_edit_2fa')) {
            $controls['tfa'] = true;
        }
        if ($this->auth->isAllowed('users_edit_irc')) {
            $controls['irc'] = true;
        }
        if ((($ownProfile === true && $this->auth->isAllowed('site_api_access')) || $this->auth->isAllowed('users_edit_apikey')) && $this->options->APIEnabled = true) {
            $controls['api'] = true;
        }
        if ($ownProfile === true || $this->auth->isAllowed('users_edit_password')) {
            $controls['password'] = true;
        }

        $user = $this->repos->users->load(intval($userID));
        $emails = $this->repos->emails->find('UserID = ?', [intval($userID)]);
        $apiKeys = $this->repos->apiKeys->find('UserID = ?', [intval($userID)]);
        $logs = $this->security->getLogs($userID);

        $bscripts = ['jquery', 'jquery.modal', 'jquery.cookie', 'user'];
        $data = [
            'emails'     => $emails,
            'apiKeys'    => $apiKeys,
            'user'       => $user,
            'controls'   => $controls,
            'ownProfile' => $ownProfile,
            'bscripts'   => $bscripts,
            'logs'       => $logs
        ];
        return new Rendered('@User/user_security.html.twig', $data);
    }

    public function passwordRecoverForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        if (!empty($this->request->getString('token'))) {
            # Decode existing token
            $token = $this->request->getString('token');
            $fullToken = $this->crypto->decrypt($token, 'default', true);
            $this->secretary->checkExternalToken($fullToken['token'], $fullToken['userID'], 'user.password.recover', 600);
            $user = $this->repos->users->load($fullToken['userID']);
            return new Rendered('@User/password_change.html.twig', ['token' => $token, 'user' =>$user]);
        } else {
            return new Rendered('@User/password_recover.html.twig', []);
        }
    }

    public function passwordRecover() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        try {
            if (!empty($this->request->getString('identifier'))) {
                $this->secretary->checkToken($this->request->getString('token'), 'user.password.recover', 600);
                $identifier = $this->request->getString('identifier');

                # Try to find the email
                $user = $this->repos->users->getByUsername($identifier);
                if ($user instanceof User) {
                    $email = $this->repos->emails->load($user->EmailID);
                } else {
                    $email = $this->repos->emails->getByAddress($identifier);
                }

                # Silently fail for unregistered or cancelled email addresses
                if (is_null($email)) {
                    $this->guardian->logAttempt('recover', 0);
                    $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                    return new Redirect("/");
                } elseif ($email->getFlag(Email::CANCELLED)) {
                    $this->guardian->logAttempt('recover', $email->UserID);
                    $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                    return new Redirect("/");
                } else {
                    $this->guardian->logAttempt('recover', $email->UserID);
                }

                # Try to load the user account.
                if (!($user instanceof User)) {
                    $user = $this->repos->users->load(intval($email->UserID));
                }

                # Check if we loaded it or not.
                if (!($user instanceof User)) {
                    $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                    return new Redirect("/");
                }

                # Populate email_body stuff first
                $subject = 'Forgotten Password';
                $variables = [];
                $variables['user']     = $user;
                $variables['scheme']   = $this->request->ssl ? 'https' : 'http';

                if (!(getUserEnabled($user->ID) === '1')) {
                    $email->sendEmail($subject, 'disabled_reset', $variables);
                    write_user_log($email->UserID, 'Someone requested email password recovery, disabled email sent');
                } else {
                    # Gen token after checks to prevent excessive load
                    $token = $this->secretary->getExternalToken($user->ID, "user.password.recover");
                    $token = $this->crypto->encrypt(['userID' => $user->ID, 'emailID' => $email->ID, 'token' => $token], 'default', true);

                    $variables['token'] = $token;
                    $email->sendEmail($subject, 'password_reset', $variables);
                    write_user_log($email->UserID, 'Someone requested email password recovery, recovery email sent');
                }

                # *ALWAYS* say we sent an email.
                $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
                return new Redirect("/");
            } else {
                $token = $this->request->getString('token');
                $fullToken = $this->crypto->decrypt($token, 'default', true);
                if ($fullToken === false) {
                    throw new ForbiddenError("Recovery link is expired or malformed!");
                }
                $this->secretary->checkExternalToken($fullToken['token'], $fullToken['userID'], 'user.password.recover', 600);
                $user = $this->repos->users->load($fullToken['userID']);
                $password = $this->request->getString('password');
                $checkPassword = $this->request->getString('check_password');

                # Silently verify email on reset
                $email = $this->repos->emails->load($fullToken['emailID']);
                if (!$email->getFlag(Email::VALIDATED)) {
                    $email->setFlags(Email::VALIDATED);
                    $this->repos->emails->save($email);
                }
                if (is_null($user)) {
                    throw new UserError("Account could not be found!");
                }
                if (!(getUserEnabled($user->ID) === '1')) {
                    throw new UserError("Account has been disabled.");
                }
                if (!strlen($password) || !strlen($checkPassword)) {
                    throw new UserError("Please enter new password, twice");
                }
                if (!($password === $checkPassword)) {
                    throw new UserError("Passwords don't match");
                }

                $this->security->checkPasswordStrength($password);

                if ($this->security->passwordIsPwned($password, $user)) {
                    throw new UserError('This password has been found in different site breaches. You must choose another one.');
                }

                $this->guardian->logReset($user->ID);

                $this->auth->setPassword($user, $password);
                $this->repos->users->save($user);
                $this->repos->securityLogs->passwordReset($user->ID);
                $this->flasher->notice("Password updated, please login now.");

                write_user_log($user->ID, 'User reset password using email recovery link');

                return new Redirect("/login");
            }
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/recover");
        }
    }

    public function passwordChange($userID) {
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_mod');
            $this->auth->checkUserLevel($userID);
        }
        try {
            $this->secretary->checkToken($this->request->getString('token'), 'user.password.change', 600);
            $user = $this->repos->users->load(intval($userID));
            $password = $this->request->getString('password');
            $checkPassword = $this->request->getString('check_password');
            $oldPassword = $this->request->getString('old_password');
            if (!strlen($password) || !strlen($checkPassword)) {
                throw new UserError("Please enter new password, twice");
            }
            if (!($password === $checkPassword)) {
                throw new UserError("Passwords don't match");
            }
            if (!$this->auth->checkPassword($user, $oldPassword)) {
                throw new UserError("Incorrect old password entered");
            }

            $this->security->checkPasswordStrength($password);

            $this->auth->setPassword($user, $password);
            $this->repos->users->save($user);
            $this->repos->securityLogs->passwordChange($user->ID);
            $this->flasher->notice("Password updated");
            return new Redirect("/user/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/{$userID}/security");
        }
    }


    /**
     * The invites page of a specific user
     *
     * @param $userID
     * @return Rendered
     */
    public function inviteForm($userID) {
        # Owner & staff only
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_view_invites');

            $user = $this->request->user;
            if (!($user instanceof User)) {
                throw new NotFoundError('This user does not exist');
            }
            $this->auth->checkUserLevel($userID);
        }

        $comment = trim($this->request->getGetString('comment'));
        $email = trim($this->request->getGetString('email'));
        if (!($this->repos->emails->isValid($email))) {
            $email = '';
        }
        $anon = $this->request->getGetBool('anon');

        $pageSize = 50;
        list($page, $limit) = page_limit($pageSize);

        $canInvite = $this->userManager->canInvite($userID);
        $pendingInvites = $this->repos->invites->getByInviter($userID);
        list($results, $invitees) = $this->repos->users->invitedBy($userID, $page);

        $vars = compact('pendingInvites', 'invitees', 'userID', 'canInvite', 'comment', 'email', 'anon', 'results', 'page', 'pageSize');
        return new Rendered('@User/invite.html.twig', $vars);
    }

    /**
     * The invite tree page of a specific user
     *
     * @param $userID
     * @return Rendered
     */
    public function inviteTree($userID) {
        # Owner only
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_mod');
            $this->auth->checkUserLevel($userID);
        }

        $user = $this->repos->users->load($userID);
        $tree = new \Luminance\Legacy\InviteTree;
        $vars = compact('user', 'tree');
        return new Rendered('@User/invite_tree.html.twig', $vars);
    }

    /**
     * Create a new invite
     *
     * @param $userID
     * @return Redirect
     */
    public function inviteSend($userID) {
        # Owner only
        if (!((int)$userID === $this->request->user->ID)) {
            return new Redirect("/user/{$this->request->user->ID}/invite");
        }

        # CSRF check
        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'user.invite.send', 600);

        # Check if the user can invite people
        if (!$this->userManager->canInvite($userID)) {
            $this->flasher->error('You cannot invite anyone');
            return new Redirect("/user/{$userID}/invite");
        }

        $email = trim($this->request->getString('email'));
        try {
            $this->emailManager->validate($email);
        } catch (InputError $e) {
            if ($e->getMessage() === "That email address is not available.") {
                $subject = 'User sent an invite using an existing email address';
                $message = $this->render->template(
                    'bbcode/duplicate_email.twig',
                    [
                    'Email'       => (string) $email,
                    'Username'    => false,
                    'IP'          => $this->master->request->ip,
                    'CurrentTime' => sqltime(),
                    'Inviter'     => $this->request->user->Username,
                    ]
                );

                $staffClass = $this->repos->permissions->getMinClassPermission('users_view_ips');
                if ($staffClass === false) {
                    throw $e;
                }

                send_staff_pm($subject, $message, $staffClass->Level);
            }
            throw $e;
        }
        $anon = !empty($this->request->getString('anon'));
        $user = $this->master->repos->users->load($userID);
        $comment = '';
        if ($this->auth->isAllowed('users_mod') || $this->auth->isAllowed('site_recruiter')) {
            $inviteComment = $this->request->getString('comment');
            $existingComm = $this->db->rawQuery(
                "SELECT UserID
                FROM users_info
                WHERE AdminComment LIKE CONCAT ('%', ?, '%')",
                [$inviteComment]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $comment = "{$inviteComment} by {$user->Username}";
            if ($existingComm) {
                $subject = "Possible Duplicate Account Created";
                $message = $this->render->template(
                    'bbcode/user_duplicate.twig',
                    [
                      'email'         => (string) $email,
                      'inviteComment' => $inviteComment,
                      'inviter'       => $user->ID,
                      'existing'      => $existingComm
                    ]
                );
                $staffClass = $this->permissions->getMinClassPermission('users_view_email');
                send_staff_pm($subject, $message, $staffClass->Level);
            }
        } else {
            if (strlen($this->request->getString('comment')) > 0) {
                $comment = $this->request->getString('comment');
                $comment = "{$user->Username} was asked how they know the invitee. Answer: {$comment}";
            }
        }

        # Check if no invite already exists for that e-mail
        if ($this->repos->invites->getByAddress($email) instanceof Invite) {
            $this->flasher->error('An invite already exists with that e-mail');
            return new Redirect("/user/{$userID}/invite");
        }

        # Create the invite instance and take it back from user's count
        $invite = $this->inviteManager->newInvite($userID, $email, $anon, $comment);
        $this->inviteManager->takeInvite($userID);

        # Send a new invite e-mail
        $this->emailManager->sendInviteEmail($invite);

        # Log the sent invite to invite log
        $this->repos->inviteLogs->inviteSent($email, $comment);

        $this->flasher->success("The invite was successfully sent");
        return new Redirect("/user/{$userID}/invite");
    }

    /**
     * Delete a user's pending invite
     *
     * @param $userID
     * @return Redirect
     */
    public function inviteDelete($userID) {
        # Owner & staff only
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_view_invites');
            $this->auth->checkUserLevel($userID);
        }

        # CSRF check
        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'user.invite.delete', 600);

        $inviteID = $this->request->getString('inviteID');
        $invite = $this->repos->invites->load($inviteID);

        # Log the cancelled invite to invite log
        $this->repos->inviteLogs->inviteCancel($invite->Email);

        # Check that the invite belongs to the user
        if (!($invite instanceof Invite) || !($invite->InviterID === (int)$userID)) {
            $this->flasher->error("Invalid invite!");
            return new Redirect("/user/{$userID}/invite");
        }

        $this->repos->invites->delete($invite);

        # Give the user's invite back if he's limited
        if (!$this->auth->isAllowed('site_send_unlimited_invites')) {
            $this->inviteManager->giveInvite($userID);
        }

        $this->flasher->success("The invite was successfully deleted!");
        return new Redirect("/user/{$userID}/invite");
    }

    /**
     * Resend a user's pending invite
     *
     * @param $userID
     * @return Redirect
     */
    public function inviteResend($userID) {
        # Owner & staff only
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_view_invites');
            $this->auth->checkUserLevel($userID);
        }

        # CSRF check
        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'user.invite.resend', 600);

        $inviteID = $this->request->getString('inviteID');
        $invite = $this->repos->invites->load($inviteID);

        # Check that the invite belongs to the user
        if (!($invite instanceof Invite) || !($invite->InviterID === (int)$userID)) {
            $this->flasher->error("Invalid invite!");
            return new Redirect("/user/{$userID}/invite");
        }

        if (!$invite->readyToResend()) {
            throw new UserError("Cannot resend so quickly");
        }

        # Send a new invite e-mail
        $this->emailManager->sendInviteEmail($invite);

        # Log the resent invite to invite log
        $this->repos->inviteLogs->inviteResendLog($invite->Email);

        $this->flasher->success("The invite was successfully resent!");
        return new Redirect("/user/{$userID}/invite");
    }

    public function inviteLogView() {
        $this->auth->checkAllowed('users_invites_logs');

        $search = $this->request->getGetString('search');
        $entity = $this->request->getGetString('entity'); // user or mass
        $action = $this->request->getGetString('action'); // sent, resent, cancel, grant, remove
        $author = $this->request->getGetString('author'); // Int USERID
        $searcU = $this->request->getGetString('userid'); // Int USERID
        $date = $this->request->getGetString('date'); // TODO take 2020-02-02 00:00:00 n search LIKE '2022%'
        // Currently date only accepts 2020-01-01

        // TODO
        //$token = $this->request->getGetString('token');
        //$this->secretary->checkToken($token, 'user.invite.log');

        $view = [];
        $params = [];
        $wheres = [];

        if (!($entity === null) && ($entity === 'user' || $entity === 'mass')) {
            $view[] = $entity;
            $params[] = $entity;
            $wheres[] = "Entity = ?";
        }

        if (!($action === null) && ($action === 'sent' || $action === 'resent' || $action === 'cancel' || $action === 'grant' || $action === 'remove')) {
            $view[] = $action;
            $params[] = $action;
            $wheres[] = "Action = ?";
        }

        if (!($author === null) && is_integer_string($author)) {
            $view[] = 'author';
            $params[] = (int) $author;
            $wheres[] = "AuthorID = ?";
        }

        if (!($searcU === null) && is_integer_string($searcU)) {
            $view[] = 'userSearch';
            $params[] = (int) $searcU;
            $wheres[] = "UserID = ?";
        }

        if (!($date === null)) {
            $dateC = explode("-", $date);
            list($yr, $mo, $day) = $dateC;
            $yr = (int) $yr;
            $mo = (int) $mo;
            $day = (int) $day;
            if (checkDate($mo, $day, $yr)) {
                $view[] = 'date';
                $params[] = $date;
                $params[] = $date;
                $wheres[] = "Date RLIKE ?";
            }
        }

        $where = implode(" AND ", $wheres);

        $pageSize = $this->settings->pagination->reports;
        list($page, $limit) = page_limit($pageSize);

        if ($search === '1' && count($wheres) >= 1) {
            $logs = $this->repos->inviteLogs->searchLog($where, $params, $limit);
            $countResults = $this->repos->inviteLogs->countSearched($where, $params);
        } else {
            // If search invalid or nonexistant show full log
            $logs = $this->repos->inviteLogs->searchLog();
        }

        $pages = $countResults / $pageSize;

        $params = [
            'page'     => $page,
            'pages'    => $pages,
            'pageSize' => $pageSize,
            'logs'     => $logs,
            'view'     => $view,
            'total'    => $countResults,
        ];

        return new Rendered('@User/invite_log.html.twig', $params);
    }

    public function inbox($section = 'received') {
        $user = $this->request->user;
        $pageSize = $user->options('MessagesPerPage', $this->settings->pagination->messages);
        list($page, $limit) = page_limit($pageSize);

        if (!(in_array($section, ['sent', 'received']))) {
            $section = 'received';
        }

        $sort = $this->request->getGetString('sort') ?? '';
        $order = 'pcu1.Sticky';
        if ($sort === 'unread') {
            if ($section === 'received') {
                $order .= ', pcu1.UnRead';
            } else {
                $order .= ', pcu2.UnRead';
            }
        } else {
            $sort = '';
        }

        if ($section === 'received') {
            $order .= ', pcu1.ReceivedDate DESC';
        } else {
            $order .= ', pcu1.SentDate DESC';
        }

        # Start the query with the user and inbox/outbox
        if ($section === 'received') {
            $query = 'pcu1.UserID = ? AND pcu1.InInbox = ?';
        } else {
            $query = 'pcu1.UserID = ? AND pcu1.InSentbox = ?';
        }

        # Start the query parameters
        $params = [$user->ID, '1'];

        # What are we looking for? Let's make sure it isn't dangerous.
        $search = $this->request->getGetString('search');
        $search = trim($search);

        # get type with defaults.
        $searchType = $this->request->getGetString('searchtype') ?? 'user';
        if (!in_array($searchType, ['user', 'subject', 'message'])) {
            $searchType = 'user';
        }

        $joins = '';

        if (!empty($search)) {
            switch ($searchType) {
                case 'message':
                    # Break search string down into individual words
                    $words = explode(' ',  $search);
                    foreach ($words as &$word) {
                        $word = trim($word);
                        $word = "%$word%";
                    }
                    $joins .= 'JOIN pm_messages AS pms ON pcu1.ConvID = pms.ConvID';
                    $query .= ' AND ' . implode(' AND ', array_fill(0, count($words), "Body LIKE ?"));
                    $params = array_merge($params, $words);
                    break;

                case 'user':
                    $otherUser = $this->repos->users->get('Username LIKE ?', [$search]);
                    if ($otherUser instanceof USER) {
                        $query .= ' AND pcu2.UserID = ?';
                        $params[] = $otherUser->ID;
                    } else {
                        # intentionally have the query return nothing for unknown users
                        $query .= ' AND FALSE';
                    }
                    break;

                default:
                case 'subject':
                    # Break search string down into individual words
                    $words = explode(' ',  $search);
                    foreach ($words as &$word) {
                        $word = trim($word);
                        $word = "%$word%";
                    }
                    $joins .= 'JOIN pm_conversations AS pms ON pcu1.ConvID=pms.ID';
                    $query .= ' AND ' . implode(' AND ', array_fill(0, count($words), "Subject LIKE ?"));
                    $params = array_merge($params, $words);
                    break;
            }
        }

        $conversations = $this->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS
                    pcu1.ConvID
               FROM pm_conversations_users AS pcu1
               JOIN pm_conversations_users AS pcu2 ON pcu1.ConvID = pcu2.ConvID
                    {$joins}
              WHERE {$query}
           GROUP BY pcu1.ConvID
           ORDER BY {$order}
              LIMIT {$limit}",
            $params
        )->fetchAll(\PDO::FETCH_COLUMN);
        $results = $this->db->foundRows();

        foreach ($conversations as &$conversation) {
            $conversation = $this->repos->userInboxConversations->load([$user->ID, $conversation]);
        }

        $params = [
            'section'       => $section,
            'search'        => $search,
            'searchType'    => $searchType,
            'sort'          => $sort,
            'page'          => $page,
            'results'       => $results,
            'pageSize'      => $pageSize,
            'conversations' => $conversations,
        ];

        return new Rendered('@User/inbox.html.twig', $params);
    }

    public function inboxManageRead() {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.manage');

        $conversations = $this->request->getPostArray('conversations');

        if (empty($conversations)) {
            throw new UserError('No conversations are selected');
        }

        foreach ($conversations as &$conversation) {
            $conversation = $this->repos->userInboxConversations->load([$user->ID, $conversation]);
            $conversation->UnRead = '0';
            $this->repos->userInboxConversations->save($conversation);
        }

        $this->cache->deleteValue('inbox_new_'.$user->ID);

        return new Redirect("/user/inbox/received");
    }

    public function inboxManageUnread() {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.manage');

        $conversations = $this->request->getPostArray('conversations');

        if (empty($conversations)) {
            throw new UserError('No conversations are selected');
        }

        foreach ($conversations as &$conversation) {
            $conversation = $this->repos->userInboxConversations->load([$user->ID, $conversation]);
            $conversation->UnRead = '1';
            $this->repos->userInboxConversations->save($conversation);
        }

        $this->cache->deleteValue('inbox_new_'.$user->ID);

        return new Redirect("/user/inbox/received");
    }

    public function inboxManageSticky() {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.manage');

        $conversations = $this->request->getPostArray('conversations');

        if (empty($conversations)) {
            throw new UserError('No conversations are selected');
        }

        foreach ($conversations as &$conversation) {
            $conversation = $this->repos->userInboxConversations->load([$user->ID, $conversation]);
            $conversation->Sticky = '1';
            $this->repos->userInboxConversations->save($conversation);
        }

        return new Redirect("/user/inbox/received");
    }

    public function inboxManageUnsticky() {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.manage');

        $conversations = $this->request->getPostArray('conversations');

        if (empty($conversations)) {
            throw new UserError('No conversations are selected');
        }

        foreach ($conversations as &$conversation) {
            $conversation = $this->repos->userInboxConversations->load([$user->ID, $conversation]);
            $conversation->Sticky = '0';
            $this->repos->userInboxConversations->save($conversation);
        }

        return new Redirect("/user/inbox/received");
    }

    public function inboxManageDelete() {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.manage');

        $conversations = $this->request->getPostArray('conversations');

        if (empty($conversations)) {
            throw new UserError('No conversations are selected');
        }

        foreach ($conversations as &$conversation) {
            $conversation = $this->repos->userInboxConversations->load([$user->ID, $conversation]);
            $conversation->InInbox = '0';
            $conversation->InSentbox = '0';
            $conversation->Sticky = '0';
            $this->repos->userInboxConversations->save($conversation);
        }

        $this->cache->deleteValue('inbox_new_'.$user->ID);

        return new Redirect("/user/inbox/received");
    }

    public function inboxConversation($convID) {
        $user = $this->request->user;
        $conversation = $this->repos->userInboxConversations->load([$user->ID, $convID]);

        if (!($conversation instanceof UserInboxConversation)) {
            throw new NotFoundError('This conversation does not exist');
        }

        if ($conversation->UnRead === '1') {
            $conversation->UnRead = '0';
            $this->repos->userInboxConversations->save($conversation);
            $this->cache->decrementValue('inbox_new_'.$user->ID);
        }


        $bscripts = ['comments', 'inbox', 'bbcode', 'jquery', 'jquery.cookie'];
        $params = [
            'bscripts'     => $bscripts,
            'conversation' => $conversation,
        ];
        return new Rendered('@User/inbox_conversation.html.twig', $params);
    }

    private function canSendMessage($userID) {
        $user = $this->request->user;
        $recipient = $this->repos->users->load($userID);

        # Don't know this guy
        if (!($recipient instanceof User)) {
            throw new NotFoundError('', 'This user does not exist');
        }

        # Silly user!
        if ($recipient === $user) {
            throw new UserError('', 'You cannot start a conversation with yourself');
        }

        # Check if user can PM at all
        if ($this->repos->restrictions->isRestricted($user, Restriction::PM)) {
            throw new ForbiddenError('', 'Your private message rights have been disabled');
        }

        # Check if user can PM this guy
        if ($recipient->canPM() === false) {
            throw new UserError('', 'This user cannot receive messages from you');
        }
    }

    public function inboxConversationCompose($userID) {
        $this->canSendMessage($userID);
        $recipient = $this->repos->users->load($userID);

        $bscripts = ['comments', 'inbox', 'bbcode', 'jquery', 'jquery.cookie'];

        $params = [
            'messageType' => 'message',
            'bscripts'    => $bscripts,
            'recipient'   => $recipient,
        ];

        return new Rendered('@User/inbox_compose.html.twig', $params);
    }

    public function inboxMessageForward($messageID) {
        //$this->canSendMessage($userID);
        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.forward');

        $recipient = $this->request->getPostString('recipient');
        $recipient = $this->repos->users->getByUsername($recipient);

        # Don't know this guy
        if (!($recipient instanceof User)) {
            throw new NotFoundError('This user does not exist');
        }

        $message = $this->repos->userInboxMessages->load($messageID);
        if (!($message instanceof UserInboxMessage)) {
            throw new NotFoundError('This message does not exist');
        }

        $bscripts = ['comments', 'inbox', 'bbcode', 'jquery', 'jquery.cookie'];

        $usernameOptions = [
            'drawInBox' => false,
            'colorname' => false,
            'dropDown'  => false,
            'useSpan'   => false,
            'noIcons'   => true,
            'noGroup'   => true,
            'noClass'   => true,
            'noTitle'   => true,
            'noLink'    => true,
        ];

        $username = $this->render->username($message->author, $usernameOptions);
        $body = "[quote={$username} (msg#{$message->ID})]{$message->Body}[/quote]\n";

        $params = [
            'messageType' => 'message',
            'subject'     => "FWD: {$message->subject}",
            'forward'     => $body,
            'bscripts'    => $bscripts,
            'recipient'   => $recipient,
        ];

        return new Rendered('@User/inbox_compose.html.twig', $params);
    }

    public function inboxConversationForward($convID) {
        //$this->canSendMessage($userID);
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.forward');

        $recipient = $this->request->getPostString('recipient');
        $recipient = $this->repos->users->getByUsername($recipient);

        # Don't know this guy
        if (!($recipient instanceof User)) {
            throw new NotFoundError('', 'This user does not exist');
        }

        $conversation = $this->repos->userInboxConversations->load([$user->ID, $convID]);
        if (!($conversation instanceof UserInboxConversation)) {
            throw new NotFoundError('This conversation does not exist');
        }

        $usernameOptions = [
            'drawInBox' => false,
            'colorname' => false,
            'dropDown'  => false,
            'useSpan'   => false,
            'noIcons'   => true,
            'noGroup'   => true,
            'noClass'   => true,
            'noTitle'   => true,
            'noLink'    => true,
        ];

        $body = "[bg=#d3e3f3]FWD: {$conversation->subject}          [color=grey]conv#{$conversation->ConvID}[/color][/bg]\n";
        foreach ($conversation->messages as $message) {
            $username = $this->render->username($message->author, $usernameOptions);
            $body .= "[quote=$username]{$message->Body}[/quote]\n";
        }

        $bscripts = ['comments', 'inbox', 'bbcode', 'jquery', 'jquery.cookie'];

        $params = [
            'messageType' => 'conversation',
            'subject'     => "FWD: {$message->subject}",
            'forward'     => $body,
            'bscripts'    => $bscripts,
            'recipient'   => $recipient,
        ];

        return new Rendered('@User/inbox_compose.html.twig', $params);
    }


    public function inboxConversationSend($userID) {
        $this->canSendMessage($userID);
        $user = $this->request->user;
        $recipient = $this->repos->users->load($userID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.send');

        $body = $this->request->getPostString('body');
        $subject = $this->request->getPostString('subject');

        $forwardbody = $this->request->getPostString('forwardbody');

        $body = $forwardbody.$body;

        # If you're not sending anything, go back
        if (empty($body)) {
            throw new UserError('', 'You cannot send an empty message');
        }

        # If you're not sending anything, go back
        if (empty($subject)) {
            throw new UserError('', 'You must set a subject');
        }

        # Work-around for references to master inside the legacy Text class
        $master = $this->master;
        $bbCode = new Text;
        $bbCode->validate_bbcode($body, get_permissions_advtags($user->ID), true, false);

        $sqltime = sqltime();

        $subject = new UserInboxSubject([
            'Subject' => $subject,
        ]);
        $this->repos->userInboxSubjects->save($subject);

        $senderConversation = new UserInboxConversation([
            'UserID'        => $user->ID,
            'ConvID'        => $subject->ID,
            'InInbox'       => '0',
            'InSentbox'     => '1',
            'SentDate'      => $sqltime,
            'ReceivedDate'  => $sqltime,
            'UnRead'        => '0',
        ]);
        $this->repos->userInboxConversations->save($senderConversation);

        $recipientConversation = new UserInboxConversation([
            'UserID'        => $recipient->ID,
            'ConvID'        => $subject->ID,
            'InInbox'       => '1',
            'InSentbox'     => '0',
            'SentDate'      => $sqltime,
            'ReceivedDate'  => $sqltime,
            'UnRead'        => '1',
        ]);
        $this->repos->userInboxConversations->save($recipientConversation);

        $message = new UserInboxMessage([
            'SenderID'  => $user->ID,
            'ConvID'    => $subject->ID,
            'SentDate'  => $sqltime,
            'Body'      => $body,
        ]);
        $this->repos->userInboxMessages->save($message);

        $this->cache->deleteValue('inbox_new_' . $user->ID);
        $this->cache->deleteValue('inbox_new_' . $recipient->ID);

        return new Redirect("/user/inbox/received");
    }

    public function inboxConversationReply($convID) {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.conversation.reply');

        $conversation = $this->repos->userInboxConversations->load([$user->ID, $convID]);
        if (!($conversation instanceof UserInboxConversation)) {
              throw new NotFoundError('This conversation does not exist');
        }

        if (!($conversation->other instanceof UserInboxConversation)) {
              throw new NotFoundError('This conversation does not exist');
        }

        $body = $this->request->getPostString('body');

        # If you're not sending anything, go back
        if (empty($body)) {
            throw new UserError('', 'You cannot send an empty message');
        }

        # Work-around for references to master inside the legacy Text class
        $master = $this->master;
        $bbCode = new Text;
        $bbCode->validate_bbcode($body, get_permissions_advtags($user->ID), true, false);

        $sqltime = sqltime();

        $conversation->InSentbox = '1';
        $conversation->SentDate = $sqltime;
        $this->repos->userInboxConversations->save($conversation);

        $conversation->other->InInbox = '1';
        $conversation->other->UnRead = '1';
        $conversation->other->ReceivedDate = $sqltime;
        $this->repos->userInboxConversations->save($conversation->other);

        $message = new UserInboxMessage([
            'SenderID'  => $user->ID,
            'ConvID'    => $convID,
            'SentDate'  => $sqltime,
            'Body'      => $body,
        ]);
        $this->repos->userInboxMessages->save($message);

        $this->cache->deleteValue('inbox_new_' . $conversation->user->ID);
        $this->cache->deleteValue('inbox_new_' . $conversation->other->user->ID);

        return new Redirect("/user/inbox/received");
    }

    public function inboxConversationManage($convID) {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.inbox.conversation.manage');

        $conversation = $this->repos->userInboxConversations->load([$user->ID, $convID]);

        if (!($conversation instanceof UserInboxConversation)) {
            throw new NotFoundError('This conversation does not exist');
        }

        $delete = $this->request->getPostBool('delete');
        $sticky = $this->request->getPostBool('sticky');
        $unread = $this->request->getPostBool('mark_unread');

        if ($sticky === true) {
            $conversation->Sticky = '1';
        } else {
            $conversation->Sticky = '0';
        }

        if ($unread === true && $delete === false) {
            $conversation->UnRead = '1';
            $this->cache->incrementValue("inbox_new_{$user->ID}");
        }

        if ($delete === true) {
            $conversation->InInbox = '0';
            $conversation->InSentbox = '0';
            $conversation->Sticky = '0';
        }

        $this->repos->userInboxConversations->save($conversation);

        return new Redirect("/user/inbox/received");
    }

    public function inboxMessageGet($messageID) {
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.
        try {
            $user = $this->request->user;
            $message = $this->repos->userInboxMessages->load($messageID);

            if (!($message instanceof UserInboxMessage)) {
                throw new NotFoundError('', 'This message does not exist');
            }

            if (!($message->conversation instanceof UserInboxConversation)) {
                throw new NotFoundError('', 'This message has been orphaned');
            }

            if ($message->canRead($user) === false) {
                throw new ForbiddenError('', "You do not have permission to read this message");
            }

            # Work-around for references to master inside the legacy Text class
            $master = $this->master;
            $bbCode = new Text;
            $body = $bbCode->clean_bbcode($message->Body, get_permissions_advtags($user->ID));

            if ($this->request->getGetString('body') === '1') {
                return new JSON(trim($body));
            } else {
                $bbCode->display_bbcode_assistant("editbox{$message->ID}", get_permissions_advtags($user->ID, $user->legacy['CustomPermissions']));
                $escapedBody = display_str($body);
                return new Response("<textarea id=\"editbox{$message->ID}\" class=\"long\" onkeyup=\"resize('editbox{$message->ID}');\" name=\"body\" rows=\"10\">{$escapedBody}</textarea>");
            }
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function reminders() {
        $this->auth->checkAllowed('site_set_reminder');
        $type = $this->request->getGetString('type');
        $ext = $this->request->getGetString('ext');
        $page = $this->request->getGetString('page');
        $user = $this->request->user;
        switch ($ext) {
            case 'completed':
                $defaultFlags = Reminder::COMPLETED;
                break;
            case 'cancelled':
                $defaultFlags = Reminder::CANCELLED;
                break;
            default:
                $defaultFlags = 0;
                break;
        }
        switch ($type) {
            case 'group':
                $this->auth->checkAllowed('users_fls');
                $defaultFlags += Reminder::SHARED;
                $reminders = $this->repos->reminders->loadGroup($user, $defaultFlags);
                $numResults = $this->db->foundRows();
                break;
            case 'pers':
            default:
                $reminders = $this->repos->reminders->loadUser($user, $defaultFlags);
                $numResults = $this->db->foundRows();
                break;
        }

        $pages = get_pages($page, $numResults, 15, 10);

        $params = [
            'page'     => $page,
            'pages'    => $pages,
            'entries'  => $reminders,
            'total'    => $numResults,
            'classes'  => $this->repos->permissions->getClasses(),
        ];

        return new Rendered('@User/reminders.html.twig', $params);
    }

    public function reminderForm($reminderID) {
        $this->auth->checkAllowed('site_set_reminder');
        $reminder = $this->repos->reminders->load($reminderID);
        $user = $this->request->user;
        if ($reminder instanceof Reminder) {
            $params = [
                'reminder' => $reminder,
                'classes' => $this->repos->permissions->getClasses(),
            ];

            return new Rendered('@User/reminder.html.twig', $params);
        } else {
            $response = ["error" => "No reminder exists with provided ID"];
            return new JSON($response);
        }
    }

    public function reminderSave($reminderID) {
        $this->auth->checkAllowed('site_set_reminder');
        $reminder = $this->repos->reminders->load($reminderID);
        if ($reminder instanceof Reminder) {
            $oldLvl = $reminder->StaffLevel;
            $oldSub = $reminder->Subject;
            $oldNote = $reminder->Note;
            $oldDate = $reminder->RemindDate;
            $oldType = $reminder->Type;

            $newLvl = $this->request->getString('stafflevel');
            $newNote = $this->request->getString('note');
            $newSub = $this->request->getString('subject');
            $newType = $this->request->getString('type');
            $newDate = $this->request->getString('RemindDate');

            if (!($oldLvl === $newLvl)) {
                $this->auth->checkAllowed('users_fls');
                $reminder->StaffLevel = $newLvl;
            }
            if (is_null($reminder->StaffLevel)) {
                $reminder->setFlags(Reminder::SHARED);
            }
            if (!($oldType === $newType)) {
                $reminder->Type = $newType;
            }
            if ($reminder->Type === 'pers') {
                $reminder->unsetFlags(Reminder::SHARED);
            }
            if (!($oldDate === $newDate)) {
                $reminder->RemindDate = $newDate;
            }
            if (!($oldSub === $newSub)) {
                $reminder->Subject = $newSub;
            }
            if (!($newNote === $oldNote)) {
                $reminder->Note = $newNote;
            }
            $this->repos->reminders->save($reminder);
            return new Redirect("/user/reminders");
        } else {
            $response = ["error" => "No reminder exists with provided ID"];
            return new JSON($response);
        }
    }

    public function reminderNew() {
        $this->auth->checkAllowed('site_set_reminder');
        $params = [
            'classes' => $this->repos->permissions->getClasses(),
        ];
        return new Rendered('@User/reminder_new.html.twig', $params);
    }

    public function reminderCreate() {
        $this->auth->checkAllowed('site_set_reminder');
        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.reminder.create');
        $type = $this->request->getString('type');
        $user = $this->request->user;

        $reminder = new Reminder([
            'UserID'      => intval($user->ID),
            'Subject'     => $this->request->getString('subject'),
            'Note'        => $this->request->getString('note'),
            'Created'     => new \DateTime(),
            'RemindDate'  => $this->request->getString('RemindDate'),
            'StaffLevel'  => $this->request->getString('stafflevel'),
            'Flags'       => 0,
        ]);
        if ($type === 'group') {
            $this->auth->checkAllowed('users_fls');
            $reminder->setFlags(Reminder::SHARED);
        }
        $this->repos->reminders->save($reminder);
        return new Redirect("/user/reminders");
    }

    public function reminderCancel($reminderID) {
        $this->auth->checkAllowed('site_set_reminder');
        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'user.reminder.cancel', 600);
        $reminder = $this->repos->reminders->load($reminderID);
        if ($reminder instanceof Reminder) {
            $reminder->setFlags(Reminder::CANCELLED);
            $this->repos->reminders->save($reminder);
            return new Redirect("/user/reminders");
        } else {
            throw new UserError('Invalid Reminder');
        }
    }

    public function passkeyHistory($userID) {
        $this->auth->checkAllowed('users_view_keys');
        $this->auth->checkUserLevel($userID);

        $user = $this->repos->users->load($userID);

        return new Rendered('@User/history_passkeys.html.twig', ['user' => $user]);
    }

    public function apikeyHistory($userID) {
        $this->auth->checkAllowed('users_view_keys');
        $this->auth->checkUserLevel($userID);

        $apiKeys = $this->repos->apiKeys->find('UserID = ?', [intval($userID)]);
        $user = $this->repos->users->load($userID);

        return new Rendered('@User/history_apikeys.html.twig', ['apiKeys' => $apiKeys, 'user' => $user]);
    }

    public function passwordHistory($userID) {
        $this->auth->checkAllowed('users_view_keys');
        $this->auth->checkUserLevel($userID);

        $user = $this->repos->users->load($userID);

        return new Rendered('@User/history_passwords.html.twig', ['user' => $user]);
    }

    public function emailAdd($userID) {
        if (!((int)$userID === $this->request->user->ID)) {
            $this->auth->checkAllowed('users_edit_email');
            $this->auth->checkUserLevel($userID);
        }
        try {
            $this->secretary->checkToken($this->request->getString('token'), 'user.email.add', 600);
            $address = $this->request->getString('address');
            $stealth = $this->request->getBool('stealth');
            $email = $this->repos->emails->getByAddress($address);
            if ($email instanceof Email) {
                if (!($email->UserID === (int)$userID)) throw new UserError("This email is already registered");
                if (!check_perms('users_edit_email')) {
                    if ($email->getFlag(Email::CANCELLED)) {
                        $email->unsetFlags(Email::CANCELLED);
                        $this->emails->save($email);
                    }
                    if ($email->getFlag(Email::QUIET)) {
                        $email->unsetFlags(Email::QUIET);
                        $this->emails->save($email);
                    }
                } else {
                    if (!$stealth && $email->getFlag(Email::CANCELLED)) {
                        $email->unsetFlags(Email::CANCELLED);
                        $email->unsetFlags(Email::QUIET);
                        $this->emails->save($email);
                    } else {
                        $this->flasher->notice('Looks like you tried to add an existing email');
                    }
                }
            } else {
                if (isset($stealth)) {
                    $email = $this->emailManager->newEmail(intval($userID), $address);
                    $email->setFlags(Email::QUIET);
                    $email->setFlags(Email::CANCELLED);
                    $this->repos->emails->save($email);
                } else {
                    $email = $this->emailManager->newEmail(intval($userID), $address);
                    $this->emailManager->sendConfirmation($email->ID);
                }
            }
            $this->repos->securityLogs->newEmail((int) $userID, $address);
            return new Redirect("/user/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/{$userID}/security");
        }
    }

    public function emailResend($userID) {
        try {
            if (!((int)$userID === $this->request->user->ID)) {
                $this->auth->checkAllowed('users_edit_email');
                $this->auth->checkUserLevel($userID);
            }
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'user.email.resend', 600);

            $emailID = $this->request->getInt('emailID');
            $email = $this->repos->emails->load($emailID);

            # Checking and validation
            if (!((int)$userID === $email->UserID)) {
                throw new UserError("Email does not belong to user");
            }

            if (!$email->readyToResend()) {
                throw new UserError("Cannot resend so quickly");
            }

            $this->emailManager->sendConfirmation($email->ID);

            return new Redirect("/user/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/{$userID}/security");
        }
    }

    public function emailConfirm($userID) {
        try {
            $token = $this->request->getString('token');
            $fullToken = $this->crypto->decrypt($token, 'default', true);
            $this->secretary->checkExternalToken($fullToken['token'], $fullToken['email'], 'user.email.confirm');

            $this->emailManager->validateAddress($fullToken['email']);

            if ($this->request->user) {
                return new Redirect("/user/{$userID}/security");
            } else {
                return new Redirect("/");
            }
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            if ($this->request->user) {
                return new Redirect("/user/{$userID}/security");
            } else {
                return new Redirect("/");
            }
        }
    }

    public function emailRestore($userID) {
        try {
            $this->auth->checkAllowed('users_edit_email');
            $this->auth->checkUserLevel($userID);
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'user.email.restore', 600);

            $emailID = $this->request->getInt('emailID');
            $email = $this->repos->emails->load($emailID);

            # Checking and validation
            if (!((int)$userID === $email->UserID)) throw new UserError("Email does not belong to user");

            $email->unsetFlags(Email::CANCELLED);
            $email->unsetFlags(Email::QUIET);
            $this->repos->emails->save($email);
            $this->repos->securityLogs->restoreEmail((int) $userID, $email->Address);

            return new Redirect("/user/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/{$userID}/security");
        }
    }

    public function emailDelete($userID) {
        try {
            if (!((int)$userID === $this->request->user->ID)) {
                $this->auth->checkAllowed('users_edit_email');
                $this->auth->checkUserLevel($userID);
            }
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'user.email.delete', 600);

            $emailID = $this->request->getInt('emailID');
            $email = $this->repos->emails->load($emailID);

            # Checking and validation
            if (!((int)$userID === $email->UserID)) throw new UserError("Email does not belong to user");
            if ($email->getFlag(Email::IS_DEFAULT)) throw new UserError("Cannot delete default email");
            if ($this->auth->isAllowed('users_edit_email') && $email->getFlag(Email::CANCELLED)) {
                $this->repos->emails->delete($email);
                $this->repos->securityLogs->deleteEmail((int) $userID, $email->Address);
            } else {
                $email->setFlags(Email::CANCELLED);
                $this->repos->emails->save($email);
                $this->repos->securityLogs->removeEmail((int) $userID, $email->Address);
            }

            return new Redirect("/user/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/{$userID}/security");
        }
    }

    public function emailDefault($userID) {
        try {
            if (!((int)$userID === $this->request->user->ID)) {
                $this->auth->checkAllowed('users_edit_email');
                $this->auth->checkUserLevel($userID);
            }
            $token = $this->request->getString('token');
            $this->secretary->checkToken($token, 'user.email.default', 600);

            $emailID = $this->request->getInt('emailID');
            $email = $this->repos->emails->load($emailID);

            # Checking and validation
            if (!((int)$userID === $email->UserID)) throw new UserError("Email does not belong to user");
            if ($email->getFlag(Email::CANCELLED)) throw new UserError("Can't set a deleted email to default");
            if (!$this->auth->isAllowed('users_edit_email') && !$email->getFlag(Email::VALIDATED)) throw new UserError("Can't set an unverified email to default");
            # Do the stupid shuffle
            $user = $this->repos->users->load($userID);
            if ($user->defaultEmail instanceof Email) {
                $user->defaultEmail->unsetFlags(Email::IS_DEFAULT);
                $this->repos->emails->save($user->defaultEmail);
            }
            $email->setFlags(Email::IS_DEFAULT);
            $user->EmailID = $email->ID;
            $this->repos->emails->save($email);
            $this->repos->users->save($user);

            return new Redirect("/user/{$userID}/security");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/{$userID}/security");
        }
    }

    public function userRegisterForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }

        $token = $this->request->getString('invite');
        if (!empty($token)) {
            $fullToken = $this->crypto->decrypt($token, 'default', true);
            if ($fullToken === false) {
                # Decrypt failed
                throw new ForbiddenError('Invite link malformed');
            }
            $invite = $this->repos->invites->load($fullToken['inviteID']);

            # First invite only!
            if (empty($invite) || $invite->hasExpired() === true || !(getUserEnabled($invite->InviterID) === '1')) {
                $this->flasher->error("Invite is invalid or expired.");
                return new Redirect("/");
            }

            # Generate a new token, should be considered valid for 1 day.
            $token = $this->secretary->getExternalToken($invite->Email, 'user.register');
            $token = $this->crypto->encrypt(['email' => $invite->Email, 'token' => $token, 'invite' => $invite->ID], 'default', true);

            return new Redirect("/user/create?token={$token}");
        }

        return new Rendered('@User/user_register.html.twig', []);
    }

    public function userRegister() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        if ($this->settings->site->open_registration) {
            $email = $this->request->getString('email');
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);

            $this->repos->emails->checkAvailable($email);
            $token = $this->secretary->getExternalToken($email, 'user.register');
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
                $this->emailManager->sendEmail($email, $subject, $body);
                $this->flasher->notice("An e-mail with further instructions has been sent to the provided address.");
            }
        }
        return new Redirect("/");
    }

    public function userCreateForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        try {
            $token = $this->request->getString('token');
            $fullToken = $this->crypto->decrypt($token, 'default', true);
            if (array_key_exists('token', $fullToken) && array_key_exists('email', $fullToken)) {
                $this->secretary->checkExternalToken($fullToken['token'], $fullToken['email'], 'user.register', 86400); # 1 days
            } else {
                throw new UserError("Malformed URL");
            }
            return new Rendered('@User/user_create.html.twig', ['email' => $fullToken['email'], 'token' => $token]);
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/");
        }
    }

    public function userCreate() {
        $token   = $this->request->getString('token');
        $fullToken = $this->crypto->decrypt($token, 'default', true);
        $this->secretary->checkExternalToken($fullToken['token'], $fullToken['email'], 'user.register', 86400);
        $email   = $fullToken['email'];

        if (array_key_exists('invite', $fullToken)) {
            # Check the invite is valid, first invite only!
            # Force load from DB
            $this->repos->invites->disableCache();
            $invite = $this->repos->invites->load($fullToken['invite']);
            if (!($invite instanceof Invite) || $invite->hasExpired() === true || !(getUserEnabled($invite->InviterID) === '1')) {
                $this->flasher->error("Invite is invalid or expired.");
                return new Redirect("/");
            }
        } else {
            $invite = null;
        }

        try {
            $username = $this->request->getString('username');
            $password = $this->request->getString('password');
            $passwordCheck = $this->request->getString('password_check');
            if (!($password === $passwordCheck)) {
                throw new InputError("Passwords do not match.");
            }

            $this->security->checkPasswordStrength($password);

            $this->auth->createUser($username, $password, $email, $invite);
            if ($invite instanceof Invite) $this->orm->delete($invite);
            return new Redirect("/login");
        } catch (InputError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/user/create?email={$email}&token={$token}");
        } catch (UserError $e) {
            $this->flasher->error($e->publicMessage);
            return new Redirect("/");
        }
    }

    public function restrictionDelete($userID) {
        $this->auth->checkAllowed('users_disable_any');
        $this->auth->checkUserLevel($userID);

        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'user.restriction.delete', 600);

        $restrictionID = $this->request->getInt('restrictionID');
        $restriction = $this->repos->restrictions->load($restrictionID);

        if (!($restriction instanceof Restriction)) {
            throw new NotFoundError('Restriction ID could not be found');
        }

        # Check that the restriction belongs to the user
        $this->repos->restrictions->checkUser($restriction, $userID);

        $this->repos->restrictions->delete($restriction);
        return new Redirect("/user.php?id={$userID}#restrictionsdiv");
    }

    public function restrictionCancel($userID) {
        $this->auth->checkAllowed('users_disable_any');
        $this->auth->checkUserLevel($userID);

        $token = $this->request->getString('token');
        $this->secretary->checkToken($token, 'user.restriction.cancel', 600);

        $restrictionID = $this->request->getInt('restrictionID');
        $restriction = $this->repos->restrictions->load($restrictionID);

        if (!($restriction instanceof Restriction)) {
            throw new NotFoundError('Restriction ID could not be found');
        }

        # Check that the restriction belongs to the user
        $this->repos->restrictions->checkUser($restriction, $userID);

        $restriction->Expires = new \DateTime();
        $this->repos->restrictions->save($restriction);

        return new Redirect("/user.php?id={$userID}#restrictionsdiv");
    }

    public function dumpUser($userID) {
        $user = $this->master->repos->users->load($userID);
        if (!($user instanceof User)) {
            throw new NotFoundError('Invalid or Unknown User ID');
        }
        if (!check_perms('users_view_email')) {
            throw new ForbiddenError();
        }
        $this->auth->checkUserLevel($userID);

        $perPage = $this->request->user->options('IpsPerPage', 350);

        list($page, $limit) = page_limit($perPage);

        list($ips, $results) = $this->master->repos->userHistoryIPs->findCount('UserID = ?', [$user->ID], 'StartTime DESC', $limit);

        $emails = $this->repos->emails->find('UserID = ?', [intval($userID)]);

        $user->torrentClients = $this->db->rawQuery(
            "SELECT useragent,
                    INET6_NTOA(ipv4) AS `ip`,
                    LEFT(peer_id, 8) AS `clientid`
               FROM xbt_files_users WHERE uid = ?
           GROUP BY useragent, ipv4",
            [$user->ID]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $user->connectable = $this->db->rawQuery(
            "SELECT ucs.Status AS `status`,
                ucs.IP AS `ip`,
                xbt.port AS `port`,
                Max(ucs.Time) AS `timeChecked`
            FROM users_connectable_status AS ucs
            LEFT JOIN xbt_files_users AS xbt ON xbt.uid=ucs.UserID
                AND INET6_NTOA(xbt.ipv4)=ucs.IP
                AND xbt.Active='1'
            WHERE UserID = ?
            GROUP BY ucs.IP
            ORDER BY Max(ucs.Time) DESC LIMIT 100",
            [$user->ID]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $data = [
            'user'       => $user,
            'ips'        => $ips,
            'page'       => $page,
            'pageSize'   => $perPage,
            'results'    => $results,
            'emails'     => $emails,
        ];
        return new Rendered('@User/dump.html.twig', $data);
    }
}
