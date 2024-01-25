<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Entities\IP;
use Luminance\Entities\User;

class Guardian extends Service {

    protected static $defaultOptions = [
      'FloodBanTries'     => ['value' => 3, 'section' => 'security', 'displayRow' => 3, 'displayCol' => 1, 'type' => 'int', 'description' => 'Flood ban threshold'],
      'FloodBanHours'     => ['value' => 6, 'section' => 'security', 'displayRow' => 3, 'displayCol' => 2, 'type' => 'int', 'description' => 'Flood ban hours'],
    ];

    protected static $useServices = [
        'cache'    => 'Cache',
        'db'       => 'DB',
        'settings' => 'Settings',
        'options'  => 'Options',
        'render'   => 'Render',
        'repos'    => 'Repos',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->log = $this->master->log;
    }

    public function detect($type, $user = null) {
        $IP = $this->request->ip;
        $flood = $this->repos->requestFloods->getOrNew($type, $IP, $user);
        return $flood;
    }

    public function getLastAttempt($type) {
        $user = $this->request->user;
        $IP = $this->request->ip;
        $flood = $this->repos->requestFloods->getOrNew($type, $IP, $user);
        return $flood;
    }

    protected function recordAttempt($type, $userID = 0) {
        # Don't be a dick in debug mode
        if ($this->settings->site->debug_mode) return;

        $user = $this->repos->users->load($userID);
        $flood = $this->detect($type, $user);

        $flood->Requests++;
        if ($flood->Requests>=$this->options->FloodBanTries) {
            $IP = $this->repos->ips->ban($this->request->ip, "Automated ban per >{$this->options->FloodBanTries} {$type} requests", $this->options->FloodBanHours);
            $flood->IPBans++;
            $this->repos->requestFloods->save($flood);
            $this->repos->ips->checkBanned($IP);
        }
        $this->repos->requestFloods->save($flood);
    }

    public function logAttempt($type = 'failed login', $userID = 0) {
        # The user exists in the database, inform the user about the failed login attempt.
        if ($userID > 0 && $type==='failed login') {
            $user = $this->repos->users->load($userID);

            # Don't alert 2FA protected users to password attempts
            if (!($user->isTwoFactorEnabled())) {
                $subject = urlencode("I received a Security Alert");
                $message = urlencode("Someone has made an unsuccessful attempt to access my account.\n".
                                    "I believe this might be an attempt to hack into my account.\n\n".
                                    "IP: {$_SERVER['REMOTE_ADDR']}\n".
                                    "Date: ".sqltime()."\n\n".
                                    "Custom message:\n");
                send_pm(
                    $user->ID,
                    0,
                    'Security Alert',
                    "Somebody (probably you, ".$user->Username.") tried to login to this account but failed!\n".
                    "Their IP Address was : {$_SERVER['REMOTE_ADDR']}\n\n".
                    "We suggest that you ensure you have a strong password for your account.\n".
                    "Sometimes this message is generated when another user with a similar username accidentally types in yours.\n\n".
                    "If you think this was an attempt to hack your account please".
                    " [url=/staffpm.php?action=user_inbox&show=1&assign=smod&sub={$subject}&msg={$message}][u]report this event to staff[/u][/url].\n".
                    "- Thank you."
                );
            }
        }
        $this->recordAttempt($type, $userID);
    }

    public function logReset($userID) {

        if ($userID === 0) return;

        $user    = $this->repos->users->load($userID);
        $rip     = $this->request->ip;
        $ripcc   = geoip($rip);
        $ip      = (string) ($this->repos->ips->load($user->IPID) ?? 'unknown');
        $ipcc    = geoip($ip);

        if (!($ipcc === $ripcc)) {
            # send staffpm here

            if ($user->ID && !(getUserEnabled($user->ID) === '1')) {
                $subject = 'Possible hack attempt: password reset';
                $message = $this->render->template('bbcode/hack_password_reset.twig', [
                    'user'   => $user,
                    'ip'     => (string) $ip,
                    'ipcc'   => geoip($ip),
                    'rip'    => $rip,
                    'ripcc'  => $ripcc,
                ]);

                # Find the first staff class with IP permission
                $staffClass = $this->repos->permissions->getMinClassPermission('users_view_ips');
                if (!($staffClass === false)) {
                    send_staff_pm($subject, $message, $staffClass->Level);
                }
            }
        }
    }

    /**
     * @param User $user
     * @param IP $ip
     */
    public function logDisabled(User $user, IP $ip) {
        if (!$this->options->DisabledHits) {
            return;
        }

        $this->db->rawQuery("INSERT IGNORE INTO disabled_hits (UserID, IPID, Time) VALUES (:userid, :ipid, NOW()) ON DUPLICATE KEY UPDATE Time = NOW()", [
            ':userid' => $user->ID,
            ':ipid'   => $ip->ID
        ]);
    }
}
