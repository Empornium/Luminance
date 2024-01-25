<?php

namespace Luminance\Services;

use Luminance\Core\Service;
use Luminance\Entities\IP;
use Luminance\Entities\User;
use Luminance\Errors\UserError;

/**
 * The Security service wraps several security modules/repos in one.
 * So far, it handles:
 *    Users Security Logs
 *
 * @package Luminance\Services
 */
class Security extends Service {

    protected static $useServices = [
        'haveibeenpwned' => 'HaveIBeenPwned',
        'render'         => 'Render',
        'db'             => 'DB',
        'options'        => 'Options',
        'repos'          => 'Repos',
    ];

    /**
     * Get all logs for a specific user
     *
     * @param $userID
     * @return mixed
     */
    public function getLogs($userID) {
        $logs = $this->repos->securityLogs->find('UserID = :userID', [':userID'=> $userID]);
        foreach ($logs as $log) {
            # Get IP infos
            if ($log->IPID === null) {
                $log->IP = '-';
            } else {
                $ip = $this->repos->ips->load($log->IPID);
                if ($ip instanceof IP) {
                    $log->IP = display_ip((string) $ip, $ip->geoip);
                } else {
                    $log->IP = '-';
                }
            }
        }
        return $logs;
    }

    /**
     * @param $password
     * @throws UserError
     */
    public function checkPasswordStrength($password) {
        # Make sure the password has the minimum required length
        if (mb_strlen($password) < $this->options->MinPasswordLength) {
            throw new UserError("This password is too short. It must be at least {$this->options->MinPasswordLength} characters.");
        }

        # Make sure the password is not blacklisted, list was collected from:
        # https://github.com/danielmiessler/SecLists
        # https://github.com/danielmiessler/SecLists/blob/master/Passwords/darkweb2017-top10000.txt
        # https://github.com/danielmiessler/SecLists/blob/master/Passwords/probable-v2-top12000.txt
        # We should make this a DB table and auto-populate it.
        if ($this->options->PasswordBlacklist) {
            $blacklistFile = $this->master->applicationPath . '/../resources/lists/blacklisted-passwords.txt';
            if ($fh = @fopen($blacklistFile, 'rb')) {
                while (!(($buffer = fgets($fh)) === false)) {
                    if ($password === trim($buffer)) {
                        throw new UserError("This password is blacklisted. Please use a strong and unique password.");
                    }
                }
                fclose($fh);
            }
        }
    }

    /**
     * @param $password
     * @return bool
     */
    public function passwordIsPwned($password, $user) {
        # No need to check if the option is disabled
        if (!$this->options->HaveIBeenPwned) {
            return false;
        }

        # Enable or disable based on random-ish user percentile to allow gradual enabling
        $percent = $this->options->HaveIBeenPwnedPercent;
        $userPercentile = $user->ID % 100 + 1;
        if ($userPercentile > $percent) {
            return false;
        }

        # Check the password against HaveIBeenPwned
        # If it failed (UNKNOWN), we silently skip the check
        switch ($this->haveibeenpwned->check($password)) {
            case HaveIBeenPwned::EXPOSED:
                return true;
            case HaveIBeenPwned::NOT_EXPOSED:
            case HaveIBeenPwned::UNKNOWN:
            default:
                return false;
        }
    }

    /**
     * @param User $user
     * @param IP $ip
     * @return bool
     */
    public function checkDisabledHits(User $user, IP $ip) {
        if (!$this->options->DisabledHits) {
            return false;
        }

        $linkGroup = $this->db->rawQuery('SELECT GroupID FROM users_dupes WHERE UserID = ?', [$user->ID])->fetchColumn();
        if (is_integer_string($linkGroup)) {
            $hits  = $this->db->rawQuery('SELECT dh.UserID, dh.Time FROM disabled_hits AS dh LEFT JOIN users_dupes AS ud ON dh.UserID = ud.UserID WHERE dh.IPID = :ipid AND dh.UserID != :userid AND ud.GroupID = :linkgroup AND ud.UserID IS NULL', [
                ':ipid'      => $ip->ID,
                ':userid'    => $user->ID,
                ':linkgroup' => $linkGroup,
            ])->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $hits  = $this->db->rawQuery('SELECT dh.UserID, dh.Time FROM disabled_hits AS dh WHERE dh.IPID = :ipid AND dh.UserID != :userid', [
                ':ipid'      => $ip->ID,
                ':userid'    => $user->ID,
            ])->fetchAll(\PDO::FETCH_ASSOC);
        }


        if (!count($hits)) {
            return false;
        }

        $subject = 'Possible hack attempt or dupe accounts';
        $message = $this->render->template('bbcode/disabled_hits.twig', [
            'UserID' => $user->ID,
            'IP'     => (string) $ip,
            'Hits'   => $hits,
            'CurrentTime' => sqltime()
        ]);

        # Find the first staff class with IP permission
        $staffClass = $this->repos->permissions->getMinClassPermission('users_view_ips');
        if ($staffClass === false) {
            return false;
        }

        send_staff_pm($subject, $message, $staffClass->Level);
        return true;
    }
}
