<?php

namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\IP;
use Luminance\Entities\SecurityLog;
use Luminance\Entities\User;

class SecurityLogRepository extends Repository
{
    protected $entityName = 'SecurityLog';

    /**
     * Log on passkey reset
     *
     * @param $userID
     * @return SecurityLog
     */
    public function passkeyChange($userID) {
        return $this->log('Passkey reset', $userID, $this->master->request->user->ID);
    }

    /**
     * Log on password change
     *
     * @param $userID
     * @return SecurityLog
     */
    public function passwordChange($userID) {
        return $this->log('Password changed', $userID, $this->master->request->user->ID);
    }

    /**
     * Log on password reset (recovery)
     *
     * @param $userID
     * @return SecurityLog
     */
    public function passwordReset($userID) {
        // The author of the event is not logged yet (0),
        // but we do have to force the IP
        return $this->log('Password reset', $userID, 0, $this->master->request->ip);
    }

    /**
     * Log on 2-fa enabling
     *
     * @param $userID
     * @return SecurityLog
     */
    public function twoFactorEnabling($userID) {
        return $this->log('2-Factor Authentication enabled', $userID, $this->master->request->user->ID);
    }

    /**
     * Log on 2-fa disabling
     *
     * @param $userID
     * @return SecurityLog
     */
    public function twoFactorDisabling($userID) {
        return $this->log('2-Factor Authentication disabled', $userID, $this->master->request->user->ID);
    }

    /**
     * Log on new email
     *
     * @param $userID
     * @param $email
     * @return SecurityLog
     */
    public function newEmail($userID, $email) {
        return $this->log("{$email} added", $userID, $this->master->request->user->ID);
    }

    /**
     * Log on email removal (soft delete)
     *
     * @param $userID
     * @param $email
     * @return SecurityLog
     */
    public function removeEmail($userID, $email) {
        return $this->log("{$email} removed", $userID, $this->master->request->user->ID);
    }

    /**
     * Log on email removal (hard delete)
     *
     * @param $userID
     * @param $email
     * @return SecurityLog
     */
    public function deleteEmail($userID, $email) {
        return $this->log("{$email} deleted", $userID, $this->master->request->user->ID);
    }

    /**
     * Log on email restore
     *
     * @param $userID
     * @param $email
     * @return SecurityLog
     */
    public function restoreEmail($userID, $email) {
        return $this->log("{$email} restored", $userID, $this->master->request->user->ID);
    }

    /**
     * Create a new security log
     *
     * @param $event
     * @param $subject
     * @param int $author
     * @param IP|null $ip
     * @param \DateTime|null $date
     * @return SecurityLog
     */
    private function log($event, $subject, $author = 0, IP $ip = null, \DateTime $date = null) {
        // User type conversion for the subject
        if (!is_int($subject)) {
            if (!$subject instanceof User) {
                throw new \InvalidArgumentException('User must be a User entity or an int');
            } else {
                $subject = $subject->ID;
            }
        }

        // User type conversion for the author
        if (!is_int($author)) {
            if (!$author instanceof User) {
                throw new \InvalidArgumentException('Author must be a User entity or an int');
            } else {
                $author = $author->ID;
            }
        }

        // Get current date if none was provided
        if ($date === null) {
            $date = new \DateTime();
        }

        // IP type conversion
        if ($ip instanceof IP) {
            $ip = $ip->ID;

        // If no IP was given and it was not a System event,
        // we get the IP directly from the request
        } elseif ($author !== 0) {
            $ip = $this->master->request->ip->ID;
        }

        // Create the new security entry
        $log = new SecurityLog();

        $log->Event    = $event;
        $log->UserID   = $subject;
        $log->IPID     = $ip;
        $log->AuthorID = $author;
        $log->Date     = $date;

        $this->save($log);
        return $log;
    }
}
