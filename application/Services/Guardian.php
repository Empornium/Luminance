<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Entities\IP;
use Luminance\Entities\User;
use Luminance\Errors\InternalError;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\AuthError;

class Guardian extends Service {

    protected static $useRepositories = [
        'users'    => 'UserRepository',
        'ips'      => 'IPRepository',
        'floods'   => 'RequestFloodRepository',
    ];

    protected static $useServices = [
        'cache'    => 'Cache',
        'db'       => 'DB',
        'settings' => 'Settings',
        'options'  => 'Options',
    ];

    public function detect($type, $user = null) {
        $IP = $this->request->ip;
        $flood = $this->floods->get_or_new($type, $IP, $user);
        return $flood;
    }

    public function get_last_attempt($type) {
        $user = $this->request->user;
        $IP = $this->request->ip;
        $flood = $this->floods->get_or_new($type, $IP, $user);
        return $flood;
    }

    protected function record_attempt($type, $UserID = 0) {

        // Don't be a dick in debug mode
        if ($this->settings->site->debug_mode) return;

        switch ($type) {
            case '2fa':
                $bannedMessage = 'You are banned from logging in';
                break;
            case 'login':
                $bannedMessage = 'You are banned from logging in';
                break;
            case 'recover':
                $bannedMessage = 'Flood detected, your IP is banned';
                break;
            default:
                throw new InternalError("Unkown attempt type: {$type}");
                break;
        }
        $user = $this->users->load($UserID);
        $flood = $this->detect($type, $user);

        $flood->Requests++;
        $this->floods->save($flood);
        if ($flood->Requests>=3) { // Only 3 allowed login attempts, ban user's IP
            $IP = $this->ips->ban($this->request->ip, "Automated ban per >3 failed {$type} attempts", 6);
            $this->ips->check_banned($IP);
        }
    }

    public function log_attempt($type = 'login', $UserID = 0) {
        // The user exists in the database, inform the user about the failed login attempt.
        if ($UserID > 0 && $type==='login') {
            $user = $this->users->load($UserID);
            $Subject = urlencode("I received a Security Alert");
            $Message = urlencode("Someone has made an unsuccessful attempt to access my account.\n".
                                 "I believe this might be an attempt to hack into my account.\n\n".
                                 "IP: {$_SERVER['REMOTE_ADDR']}\n".
                                 "Date: ".sqltime()."\n\n".
                                 "Custom message:\n");
            send_pm($user->ID, 0, db_string('Security Alert'), db_string(
                "Somebody (probably you, ".$user->Username.") tried to login to this account but failed!\n".
                "Their IP Address was : {$_SERVER['REMOTE_ADDR']}\n\n".
                "We suggest that you ensure you have a strong password for your account.\n".
                "Sometimes this message is generated when another user with a similar username accidentally types in yours.\n\n".
                "If you think this was an attempt to hack your account please".
                " [url=/staffpm.php?action=user_inbox&show=1&assign=smod&sub={$Subject}&msg={$Message}][u]report this event to staff[/u][/url].\n".
                "- Thank you."
            ));
        }
        $this->record_attempt($type, $UserID);
    }

    public function log_reset($UserID) {

        if ($UserID == 0) return;

        $user          = $this->users->load($UserID);
        $RequestIP     = $this->request->ip;
        $RequestOrigin = geoip($RequestIP);
        $sqltime       = sqltime();
        $IP            = (string) ($this->ips->load($user->IPID) ?? 'unknown');

        if ($user->legacy['ipcc'] != $RequestOrigin) {
            // send staffpm here
            $Subject="Possible hacking attempt: password reset";
            $Message='';
            if ($user->ID && ($user->legacy['Enabled'] == '1')) {
                $Message .= 'A password reset was completed for [url=/user.php?id='.$user->ID.']'.$user->Username.'[/url].[br][br]';
                $Message .= "The user's last known IP was {$IP}, with origin {$user->legacy['ipcc']}.[br]";
                $Message .= "The requesting IP was {$RequestIP}, with origin {$RequestOrigin}.[br]";

                $this->db->raw_query(
                    "INSERT INTO staff_pm_conversations
                                      (Subject, Status, Level, UserID, Unread, Date) VALUES (:subject, 'Unanswered', '549', '0', 'false', :date)",
                    [':subject' => $Subject,
                     ':date'    => $sqltime]
                );

                $ConvID = $this->db->last_insert_id();
                $this->db->raw_query(
                    "INSERT INTO staff_pm_messages
                                      (UserID, SentDate, Message, ConvID) VALUES ('0', :sentdate, :message, :convid)",
                    [':sentdate' => $sqltime,
                     ':message'  => $Message,
                     ':convid'   => $ConvID]
                );
            }
        }
    }

    /**
     * @param User $user
     * @param IP $ip
     */
    public function log_disabled(User $user, IP $ip) {
        if (!$this->options->DisabledHits) {
            return;
        }

        $this->db->raw_query("INSERT IGNORE INTO disabled_hits (UserID, IPID, Time) VALUES (:userid, :ipid, NOW()) ON DUPLICATE KEY UPDATE Time = NOW()", [
            ':userid' => $user->ID,
            ':ipid'   => $ip->ID
        ]);
    }
}
