<?php

namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\InviteLog;
use Luminance\Entities\User;

class InviteLogRepository extends Repository {
    protected $entityName = 'InviteLog';

    /**
     * Return a class name from level
     *
     * @param $lvl
     * @return string Class Name
     */
    private function getClass($lvl) {
        return $this->db->rawQuery("SELECT Name FROM permissions WHERE Level = ?", [$lvl])->fetchColumn();
    }

    /**
     * Return the most recent invitesLog ID
     *
     * @param $address
     * @return string ID
     */
    private function getLatest($address) {
        return $this->db->rawQuery("SELECT ID FROM invites_log WHERE Address = ? ORDER BY ID DESC LIMIT 1", [$address])->fetchColumn();
    }

    /**
     * Update a user log to associate a UserID
     *
     * @param $reason
     * @param $userID
     * @param $address
     * @param int $quantity
     * @return void
     */
    public function updateOnJoin($address, $userID, $inviter = 0) {
        $count = $this->db->rawQuery("SELECT Count(ID) FROM invites_log WHERE Address = ?", [$address])->fetchColumn();
        $pubReq = $this->db->rawQuery("SELECT Count(ID) FROM public_requests WHERE ApplicantEmail = ?", [$address])->fetchColumn();
        if ($count === 1) {
            $event = "User invite sent, Account Created";
            $this->db->rawQuery(
                "UPDATE invites_log
                    SET UserID = ?,
                        Event = ?
                  WHERE Address = ?
                    AND Action = 'sent'",
                [$userID, $event, $address]
            );
        } elseif ($count === 0 && $pubReq === 0) {
            // Invite could have been sent prior to this tool, account for this
            $this->accountCreate($userID, $address, 0, 'Added at account creation');
        } elseif ($count === 0 && !($pubReq === 0)) {
            $this->accountCreate($userID, $address, $inviter, 'Joined via Public Requests Application');
        } else {
            // We only want to update the latest log if it was resent
            $id = $this->getLatest($address);
            $this->db->rawQuery("UPDATE invites_log SET UserID = ? WHERE ID = ?", [$userID, $id]);
        }
    }

    /**
     * Return the full invites_log
     *
     * @return array full log
     */
    public function searchLog(string $where, array $param, $limit) {
        if (!empty($limit)) {
            return $this->db->rawQuery("SELECT * FROM invites_log WHERE {$where} ORDER BY `Date` DESC LIMIT {$limit}", $param)->fetchAll(\PDO::FETCH_BOTH);
        } else {
            return $this->db->rawQuery("SELECT * FROM invites_log WHERE {$where} ORDER BY `Date` DESC", $param)->fetchAll(\PDO::FETCH_BOTH);
        }
    }

    /**
     * Return the count of searched logs
     *
     * @return int
     */
    public function countSearched(string $where, array $param) {
        return $this->db->rawQuery("SELECT Count(ID) FROM invites_log WHERE {$where}", $param)->fetchColumn();
    }

    public function massLog() {
        return $this->db->rawQuery(
            "SELECT
                `Event`,
                Reason,
                AuthorID,
                Quantity,
                `Action`,
                `Date`
            FROM invites_log
            WHERE Entity = 'mass'
            ORDER BY `Date` DESC"
        )->fetchAll(\PDO::FETCH_BOTH);
    }

    /**
     * Log an account when no prev log at creation
     *
     * @param $reason
     * @param $userID
     * @param $address
     * @param int $quantity
     * @return InviteLog
     */
    public function accountCreate($userID, $address, $inviter = 0, $reason = '') {
        return $this->log('User join', $inviter, $address, $reason, 'user', 'sent', 1, $userID);
    }

    /**
     * Log on User Invite send
     *
     * @param $reason
     * @param $address
     * @param int $quantity
     * @return InviteLog
     */
    public function inviteSent($address, $reason = '') {
        return $this->log('User invite sent', $this->master->request->user->ID, $address, $reason, 'user', 'sent', 1);
    }

    /**
     * Log on User Invite Cancel
     *
     * @param $reason
     * @param $address
     * @param int $quantity
     * @return InviteLog
     */
    public function inviteCancel($address, $reason = '') {
        return $this->log('User invite cancelled', $this->master->request->user->ID, $address, $reason, 'user', 'cancel', 1);
    }

    /**
     * Log on User Invite resend
     *
     * @param $reason
     * @param $address
     * @param int $quantity
     * @return InviteLog
     */
    public function inviteResendLog($address, $reason = '') {
        return $this->log('User invite sent', $this->master->request->user->ID, $address, $reason, 'user', 'sent', 1);
    }

    /**
     * Log on User Invite Grant
     *
     * @param $reason
     * @param $userID
     * @param int $quantity
     * @return InviteLog
     */
    public function userInviteGrant($reason, $userID, $quantity) {
        return $this->log('User invite added', $this->master->request->user->ID, null, $reason, 'user', 'grant', $quantity, $userID);
    }

    /**
     * Log on User Invite Removal
     *
     * @param $reason
     * @param $userID
     * @param int $quantity
     * @return InviteLog
     */
    public function userInviteRemoval($reason, $userID, $quantity) {
        return $this->log('User invite removed', $this->master->request->user->ID, null, $reason, 'user', 'remove', $quantity, $userID);
    }

    /**
     * Log on Mass Invite Grant
     *
     * @param $reason
     * @param $start
     * @param $end
     * @param int $quantity
     * @return InviteLog
     */
    public function massInviteGrant($reason, $start, $end, $quantity) {
        return $this->log('Mass invites granted to user level '.$start.' ('.$this->getClass($start).') through '.$end.'('.$this->getClass($end).')', $this->master->request->user->ID, null, $reason, 'mass', 'grant', $quantity);
    }

    /**
     * Log on Mass Invite Removal
     *
     * @param $reason
     * @param $start
     * @param $end
     * @param int $quantity
     * @return InviteLog
     */
    public function massInviteRemoval($reason, $start, $end, $quantity) {
        return $this->log('Mass invites removal from user level '.$start.' ('.$this->getClass($start).') through '.$end.'('.$this->getClass($end).')', $this->master->request->user->ID, null, $reason, 'mass', 'remove', $quantity);
    }

    /**
     * Create a new invite log
     *
     * @param $event
     * @param $reason
     * @param $entity
     * @param $action
     * @param int $author
     * @param int $quantity
     * @param int $userID
     * @param \DateTime|null $date
     * @return InviteLog
     */
    private function log($event, $author, $address, $reason, $entity, $action, $quantity = 1, $userID = 0, \DateTime $date = null) {
        # User type conversion for the author
        if (!is_int($author)) {
            if (!$author instanceof User) {
                throw new \InvalidArgumentException('Author must be a User entity or an int');
            } else {
                $author = $author->ID;
            }
        }

        # Get current date if none was provided
        if ($date === null) {
            $date = new \DateTime();
        }

        # Create the new security entry
        $log = new InviteLog();

        $log->Event    = $event;
        $log->Reason   = $reason;
        $log->Address  = $address;
        $log->UserID   = $userID;
        $log->AuthorID = $author;
        $log->Quantity = $quantity;
        $log->Action   = $action;
        $log->Entity   = $entity;
        $log->Date     = $date;

        $this->save($log);
        return $log;
    }
}
