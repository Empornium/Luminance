<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Errors\InternalError;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\UnauthorizedError;


class Guardian extends Service {

    protected $Attempt = null;
    protected $Flood = null;

    protected static $useServices = [
        'cache' => 'Cache',
        'db'    => 'DB',
    ];

    public function check_ip_ban() {
        if ($this->site_ban_ip($_SERVER['REMOTE_ADDR'])) {
            throw new ForbiddenError(null, '<span class="warning">Your IP has been banned.</span>');
        }
    }

    // This function is slow. Don't call it unless somebody's logging in.
    public function site_ban_ip($IP) {
        $IPNum = ip2unsigned($IP);
        $IPBans = $this->cache->get_value('ip_bans');
        if (!is_array($IPBans)) {
            $IPBans = $this->db->raw_query("SELECT ID, FromIP, ToIP FROM ip_bans")->fetchAll();
            $this->cache->cache_value('ip_bans', $IPBans, 0);
        }
        foreach ($IPBans as $Index => $IPBan) {
            list($ID, $FromIP, $ToIP) = $IPBan;
            if ($IPNum >= $FromIP && $IPNum <= $ToIP) {
                return true;
            }
        }

        return false;
    }


    public function detect() {
        if (is_null($this->Attempt)) {
            $Attempt = new \stdClass();
            list($Attempt->AttemptID, $Attempt->Attempts, $Attempt->Bans, $Attempt->BannedUntil) = $this->db->raw_query("SELECT ID, Attempts, Bans, BannedUntil FROM login_attempts WHERE IP=?", [$this->master->server['REMOTE_ADDR']])->fetch(\PDO::FETCH_NUM);

            if (strtotime($Attempt->BannedUntil) >= time()) {
                $diff = time_diff($Attempt->BannedUntil);
                throw new ForbiddenError(null, '<span class="warning">You are banned from logging in for another ' . $diff . '.</span>');
            }
            if (!empty($Attempt->BannedUntil) && $Attempt->BannedUntil != '0000-00-00 00:00:00') {
                $this->db->raw_query("UPDATE login_attempts SET BannedUntil=:banneduntil, Attempts=:attempts WHERE ID=:attemptid",
                                     [':banneduntil' => '0000-00-00 00:00:00', ':attempts' => '0', ':attemptid' => $Attempt->AttemptID]);
                $Attempt->Attempts = 0;
            }
            if ($Attempt->AttemptID) {
                $this->Attempt = $Attempt;
            } else {
                $this->Attempt = false;
            }
        }
        if (is_null($this->Flood)) {
            $Flood = new \stdClass();
            list($Flood->FloodID, $Flood->Requests, $Flood->Bans, $Flood->BannedUntil) = $this->db->raw_query("SELECT ID, Attempts, Bans, BannedUntil FROM login_floods WHERE IP=?", [$this->master->server['REMOTE_ADDR']])->fetch(\PDO::FETCH_NUM);
            if (strtotime($Flood->BannedUntil) >= time()) {
                $diff = time_diff($Flood->BannedUntil);
                throw new ForbiddenError(null, '<span class="warning">Flood detected, your IP is banned for another ' . $diff . '.</span>');
            }
            if (!empty($Flood->BannedUntil) && $Flood->BannedUntil != '0000-00-00 00:00:00') {
                $this->db->raw_query("UPDATE login_floods SET BannedUntil=:banneduntil, Attempts=:attempts WHERE ID=:floodid",
                                     [':banneduntil' => '0000-00-00 00:00:00', ':attempts' => '0', ':floodid' => $Flood->FloodID]);
                $Flood->Requests = 0;
            }
            if ($Flood->FloodID) {
                $this->Flood = $Flood;
            } else {
                $this->Flood = false;
            }
        }

        return ['attempt' => $this->Attempt, 'flood' => $this->Flood];
    }

    public function get_last_attempt() {
        if (is_null($this->Attempt)) {
            throw new InternalError("Must call Guardian->detect() before Guardian->get_last_attempt()");
        }
        return $this->Attempt;
    }

    public function log_attempt($UserID) {
        if (is_null($this->Attempt)) {
            throw new InternalError("Must call Guardian->detect() before Guardian->log_attempt()");
        }
        $Attempt = $this->Attempt;
        // The user exists in the database, inform the user about the failed login attempt.
        if ($UserID > 0) {
            $Username = $this->db->raw_query("SELECT Username FROM users_main WHERE ID=?", [$UserID])->fetchColumn();
            $Subject = urlencode("I received a Security Alert");
            $Message = urlencode("Someone has made an unsuccessful attempt to access my account.\n".
                                 "I believe this might be an attempt to hack into my account.\n\n".
                                 "IP: {$_SERVER['REMOTE_ADDR']}\n".
                                 "Date: ".sqltime()."\n\n".
                                 "Custom message:\n");
            send_pm($UserID, 0, db_string('Security Alert'), db_string(
                    "Somebody (probably you, $Username) tried to login to this account but failed!\n".
                    "Their IP Address was : {$_SERVER['REMOTE_ADDR']}\n\n".
                    "We suggest that you ensure you have a strong password for your account.\n".
                    "Sometimes this message is generated when another user with a similar username accidentally types in yours.\n\n".
                    "If you think this was an attempt to hack your account please".
                    " [url=/staffpm.php?action=user_inbox&show=1&assign=mod&sub={$Subject}&msg={$Message}][u]report this event to staff[/u][/url].\n".
                    "- Thank you."));
        }

        if ($Attempt) { // User has attempted to log in recently
            $Attempt->Attempts++;
            if ($Attempt->Attempts>=3) { // Only 3 allowed login attempts, ban user's IP
                $Attempt->BannedUntil=time_plus(60*60*6);
                $this->db->raw_query("UPDATE login_attempts SET
                    LastAttempt=:lastattempt,
                    Attempts=:attempts,
                    BannedUntil=:banneduntil,
                    Bans=:bans
                    WHERE ID=:id",
                  [':lastattempt' => sqltime(),
                   ':attempts'    => $Attempt->Attempts,
                   ':banneduntil' => $Attempt->BannedUntil,
                   ':bans'        => ++$Attempt->Bans,
                   ':id'          => $Attempt->AttemptID]);

                if ($Attempt->Bans>=4) { // Automated bruteforce prevention
                    $IP = ip2unsigned($_SERVER['REMOTE_ADDR']);
                    if ($this->db->rowCount() > 0) {
                        //Ban exists already, only add new entry if not for same reason
                        $Reason = $this->db->raw_query("SELECT Reason FROM ip_bans WHERE ? BETWEEN FromIP AND ToIP", [$IP])->fetchColumn();
                        if ($Reason != "Automated ban per >3 failed login attempts") {
                            $this->db->raw_query("UPDATE ip_bans
                                SET Reason  = CONCAT('Automated ban per >3 failed login attempts AND ', Reason),
                                    EndTime = DATE_ADD(EndTime, INTERVAL 6 hour)
                                WHERE FromIP = :ip AND ToIP = :ip", [':ip' => $IP]);
                        }
                    } else {
                        //No ban
                        $this->db->raw_query("INSERT INTO ip_bans
                            (FromIP, ToIP, EndTime, Reason) VALUES (:ip, :ip, DATE_ADD(NOW(), INTERVAL 6 hour), 'Automated ban per >3 failed login attempts')", [':ip' => $IP]);
                        $this->cache->delete_value('ip_bans');
                    }
                    throw new ForbiddenError(null, '<span class="warning">Your IP has been banned.</span>');
                }
                $diff = time_diff($Attempt->BannedUntil);
                throw new ForbiddenError(null, '<span class="warning">You are banned from logging in for another ' . $diff . '.</span>');
            } else {
                // User has attempted fewer than 3 logins
                $this->db->raw_query("UPDATE login_attempts SET
                    LastAttempt=:lastattempt,
                    Attempts=:attempts,
                    BannedUntil='0000-00-00 00:00:00'
                    WHERE ID=:id",
                  [':lastattempt' => sqltime(),
                   ':attempts'    => $Attempt->Attempts,
                   ':id'          => $Attempt->AttemptID]);
            }
        } else { // User has not attempted to log in recently
            $Attempt = new \stdClass();
            $Attempt->Attempts=1;
            $this->db->raw_query("INSERT INTO login_attempts
                                 (UserID, IP, LastAttempt, Attempts) VALUES (:userid, :ip, :lastattempt, 1)",
                                 [':userid'      => $UserID,
                                  ':ip'          => $_SERVER['REMOTE_ADDR'],
                                  ':lastattempt' => sqltime()]);
        }
    }

    public function log_recover($Email) {
        if (is_null($this->Flood)) {
            throw new InternalError("Must call Guardian->detect() before Guardian->log_attempt()");
        }
        $Flood = $this->Flood;
        list($UserID,$Username,$Email,$Enabled,$UserIP,$UserOrigin)=$this->db->raw_query("SELECT
                    ID,
                    Username,
                    Email,
                    Enabled,
                    IP,
                    ipcc
                    FROM users_main
                    WHERE Email=?", [$Email])->fetch(\PDO::FETCH_NUM);

	    if($UserID == 0) return;

        $RequestIP     = $_SERVER['REMOTE_ADDR'];
        $RequestOrigin = geoip($RequestIP);

        // ensure matching times in pm's/user staffnotes
        $sqltime = sqltime();

        if($UserOrigin != $RequestOrigin){
            // send staffpm here
            $Subject="Possible hacking attempt: password reset";
            $Message='';
            if ($UserID && ($Enabled == '1')) {
                $Message .= 'A password reset was requested for [url=/user.php?id='.$UserID.']'.$Username.'[/url].[br][br]';
                $Message .= "The user's last known IP was $UserIP, with origin {$UserOrigin}.[br]";
                $Message .= "The requesting IP was $RequestIP, with origin {$RequestOrigin}.[br]";
                $Message .= "The submitted email was {$Email}.[br]";

                $this->db->raw_query("INSERT INTO staff_pm_conversations
                                      (Subject, Status, Level, UserID, Unread, Date) VALUES (:subject, 'Unanswered', '549', '0', 'false', :date)",
                                        [':subject' => $Subject,
                                         ':date'    => $sqltime]);

                $ConvID = $this->db->last_insert_id();
                $this->db->raw_query("INSERT INTO staff_pm_messages
                                      (UserID, SentDate, Message, ConvID) VALUES ('0', :sentdate, :message, :convid)",
                                        [':sentdate' => $sqltime,
                                         ':message'  => $Message,
                                         ':convid'   => $ConvID]);
            }
        }

        if ($Flood) { // User has attempted to recover account recently
            $Flood->Requests++;
            if ($Flood->Requests>=3) { // Only 3 allowed recover attempts, ban user's IP
                $Flood->BannedUntil=time_plus(60*60*6);
                $this->db->raw_query("UPDATE login_floods SET
                    LastAttempt=:lastattempt,
                    Attempts=:attempts,
                    BannedUntil=:banneduntil,
                    Bans=:bans
                    WHERE ID=:id",
                  [':lastattempt' => sqltime(),
                   ':attempts'    => $Flood->Requests,
                   ':banneduntil' => $Flood->BannedUntil,
                   ':bans'        => ++$Flood->Bans,
                   ':id'          => $Flood->FloodID]);

                if ($Flood->Bans>=4) { // Automated bruteforce prevention
                    $IP = ip2unsigned($_SERVER['REMOTE_ADDR']);
                    if ($this->db->rowCount() > 0) {
                        //Ban exists already, only add new entry if not for same reason
                        $Reason = $this->db->raw_query("SELECT Reason FROM ip_bans WHERE ? BETWEEN FromIP AND ToIP", [$IP])->fetchColumn();
                        if ($Reason != "Automated ban per >3 failed email recovery attempts") {
                            $this->db->query("UPDATE ip_bans
                                SET Reason  = CONCAT('Automated ban per >3 failed email recovery attempts AND ', Reason),
                                    EndTime = DATE_ADD(EndTime, INTERVAL 6 hour)
                                WHERE FromIP = :ip AND ToIP = :ip", [':ip' => $IP]);
                        }
                    } else {
                        //No ban
                        $this->db->raw_query("INSERT INTO ip_bans
                            (FromIP, ToIP, EndTime, Reason) VALUES
                            (:ip, :ip, DATE_ADD(NOW(), INTERVAL 6 hour), 'Automated ban per >3 failed email recovery attempts')", [':ip' => $IP]);
                        $this->cache->delete_value('ip_bans');
                    }
                    throw new ForbiddenError(null, '<span class="warning">Your IP has been banned.</span>');
                }
                $diff = time_diff($Flood->BannedUntil);
                throw new ForbiddenError(null, '<span class="warning">Flood detected, your IP is banned for another ' . $diff . '.</span>');
            } else {
                // User has attempted fewer than 3 logins
                $this->db->raw_query("UPDATE login_floods SET
                    LastAttempt=:lastattempt,
                    Attempts=:attempts,
                    BannedUntil='0000-00-00 00:00:00'
                    WHERE ID=:id",
                  [':lastattempt' => sqltime(),
                   ':attempts'    => $Flood->Requests,
                   ':id'          => $Flood->FloodID]);
            }
        } else { // User has not attempted to log in recently
            $Flood = new \stdClass();
            $Flood->Requests=1;
            $this->db->raw_query("INSERT INTO login_floods (UserID, IP, LastAttempt, Attempts) VALUES (:userid, :ip, :lastattempt, 1)",
                                  [':userid'      => $UserID,
                                   ':ip'          => $_SERVER['REMOTE_ADDR'],
                                   ':lastattempt' => sqltime()]);
        }
    }
}
