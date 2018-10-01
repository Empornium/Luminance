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
    protected static $useRepositories = [
        'ips'         => 'IPRepository',
        'log'         => 'SecurityLogRepository',
        'permissions' => 'PermissionRepository',
    ];

    protected static $useServices = [
        'haveibeenpwned' => 'HaveIBeenPwned',
        'render'         => 'Render',
        'db'             => 'DB',
        'options'        => 'Options',
    ];

    /**
     * Get all logs for a specific user
     *
     * @param $userID
     * @return mixed
     */
    public function getLogs($userID) {
        $logs = $this->log->find('UserID = :userID', [':userID'=> $userID]);
        foreach ($logs as $log) {
            // Get IP infos
            if ($log->IPID === null) {
                $log->IP = '-';
            } else {
                $ip = $this->ips->load($log->IPID);
                $log->IP = display_ip((string) $ip, $ip->geoip);
            }
        }
        return $logs;
    }

    /**
     * @param $password
     * @throws UserError
     */
    public function checkPasswordStrength($password) {
        // Make sure the password has the minimum required length
        if (mb_strlen($password) < $this->options->MinPasswordLength) {
            throw new UserError("This password is too short. It must be at least {$this->options->MinPasswordLength} characters.");
        }

        // Make sure the password is not blacklisted, list was collected from:
        // https://github.com/danielmiessler/SecLists
        // https://github.com/danielmiessler/SecLists/blob/master/Passwords/darkweb2017-top10000.txt
        // https://github.com/danielmiessler/SecLists/blob/master/Passwords/probable-v2-top12000.txt
        // We should make this a DB table and auto-populate it.
        if ($this->options->PasswordBlacklist) {
            $blacklistFile = $this->master->application_path . '/../resources/lists/blacklisted-passwords.txt';
            if ($fh = @fopen($blacklistFile, 'rb')) {
                while (($buffer = fgets($fh)) !== false) {
                    if ($password == trim($buffer)) {
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
        // No need to check if the option is disabled
        if (!$this->options->HaveIBeenPwned) {
            return false;
        }

        // Enable or disable based on random-ish user percentile to allow gradual enabling
        $percent = $this->options->HaveIBeenPwnedPercent;
        $user_percentile = $user->ID % 100 + 1;
        if ($user_percentile > $percent) {
            return false;
        }

        // Check the password against HaveIBeenPwned
        // If it failed (UNKNOWN), we silently skip the check
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

        $linkGroup = $this->db->raw_query('SELECT GroupID FROM users_dupes WHERE UserID = ?', [$user->ID])->fetchColumn();
        if (is_number($linkGroup)) {
            $hits  = $this->db->raw_query('SELECT dh.UserID, dh.Time FROM disabled_hits AS dh LEFT JOIN users_dupes AS ud ON dh.UserID = ud.UserID WHERE dh.IPID = :ipid AND dh.UserID != :userid AND ud.GroupID = :linkgroup AND ud.UserID IS NULL', [
                ':ipid'      => $ip->ID,
                ':userid'    => $user->ID,
                ':linkgroup' => $linkGroup,
            ])->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $hits  = $this->db->raw_query('SELECT dh.UserID, dh.Time FROM disabled_hits AS dh WHERE dh.IPID = :ipid AND dh.UserID != :userid', [
                ':ipid'      => $ip->ID,
                ':userid'    => $user->ID,
            ])->fetchAll(\PDO::FETCH_ASSOC);
        }


        if (!count($hits)) {
            return false;
        }

        $subject = 'Possible hack attempt or dupe accounts';
        $message = $this->render->render('bbcode/disabled_hits.twig', [
            'UserID' => $user->ID,
            'IP'     => (string) $ip,
            'CC'     => geoip($ip),
            'Hits'   => $hits,
            'CurrentTime' => sqltime()
        ]);

        // Find the first staff class with IP permission
        $staffClass = $this->permissions->getMinClassPermission('users_view_ips');
        if ($staffClass === false) {
            return false;
        }

        send_staff_pm($subject, $message, $staffClass->Level);
        return true;
    }
}
