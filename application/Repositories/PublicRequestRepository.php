<?php

namespace Luminance\Repositories;

use Luminance\Core\Repository;

use Luminance\Entities\IP;
use Luminance\Entities\User;
use Luminance\Entities\Email;
use Luminance\Entities\PublicRequest;

class PublicRequestRepository extends Repository {
    protected $entityName = 'PublicRequest';

    /**
     * Record request for reactivation
     *
     * @param $userID
     * @return PublicRequest
     */
    public function reactivate($userID, $emailID, $information, $proof, $proofTwo, $proofThree, $questionOne, $questionTwo, $questionThree) {
        return $this->request('Reactivate', $userID, $emailID, $information, $proof, $proofTwo, $proofThree, $questionOne, $questionTwo, $questionThree, $this->master->request->ip);
    }

    public function application($email, $information, $proof, $proofTwo, $proofThree, $questionOne, $questionTwo, $questionThree) {
        return $this->applicant('Application', $email, $information, $proof, $proofTwo, $proofThree, $questionOne, $questionTwo, $questionThree, $this->master->request->ip);
    }

    /**
     * Create a new public request
     *
     * @param $event
     * @param $userID
     * @param $email
     * @param $information
     * @param $proof
     * @param $proofTwo
     * @param $proofThree
     * @param $questionOne
     * @param $questionTwo
     * @param $questionThree
     * @param IP|null $ip
     * @param \DateTime|null $date
     * @return PublicRequest
     */
    private function request($event, $userID, $email, $information = null, $proof = null, $proofTwo = null, $proofThree = null, $questionOne = null, $questionTwo = null, $questionThree = null, IP $ip = null, \DateTime $date = null) {
        # User type conversion for the subject
        if (!is_int($userID)) {
            if (!$userID instanceof User) {
                throw new \InvalidArgumentException('User must be a User entity or an int');
            } else {
                $userID = $userID->ID;
            }
        }

        # Email type conversion for the subject
        if (!is_int($email)) {
            if (!$email instanceof Email) {
                throw new \InvalidArgumentException('Email must be a User entity or an int');
            } else {
                $email = $email->ID;
            }
        }

        # Get current date if none was provided
        if ($date === null) {
            $date = new \DateTime();
        }

        # IP type conversion
        if ($ip instanceof IP) {
            $ip = $ip->ID;
        }

        # Create the new request entry
        $request = new PublicRequest();

        $request->Status   = 'New';
        $request->Type     = $event;
        $request->UserID   = $userID;
        $request->EmailID  = $email;
        $request->Extra    = $information;
        $request->Proof    = $proof;
        $request->ProofTwo = $proofTwo;
        $request->ProofThree = $proofThree;
        $request->QuestionOne = $questionOne;
        $request->QuestionTwo = $questionTwo;
        $request->QuestionThree = $questionThree;
        $request->IPID     = $ip;
        $request->Date     = $date;

        $this->save($request);
        $this->cache->deleteValue('num_public_requests');
        $this->cache->deleteValue("public_request_{$userID}");
        return $request;
    }

     /**
     * Create a new public request
     *
     * @param $event
     * @param $userID
     * @param $email
     * @param $information
     * @param $proof
     * @param $proofTwo
     * @param $proofThree
     * @param $questionOne
     * @param $questionTwo
     * @param $questionThree
     * @param IP|null $ip
     * @param \DateTime|null $date
     * @return PublicRequest
     */
    private function applicant($event, $email, $information = null, $proof = null, $proofTwo = null, $proofThree = null, $questionOne = null, $questionTwo = null, $questionThree = null, IP $ip = null, \DateTime $date = null) {
        # Get current date if none was provided
        if ($date === null) {
            $date = new \DateTime();
        }

        # IP type conversion
        if ($ip instanceof IP) {
            $ip = $ip->ID;
        }

        # Create the new request entry
        $request = new PublicRequest();

        $request->Status         = 'New';
        $request->Type           = $event;
        $request->UserID         = 0;
        $request->EmailID        = 0;
        $request->ApplicantEmail = $email;
        $request->Extra          = $information;
        $request->Proof          = $proof;
        $request->ProofTwo       = $proofTwo;
        $request->ProofThree     = $proofThree;
        $request->QuestionOne    = $questionOne;
        $request->QuestionTwo    = $questionTwo;
        $request->QuestionThree  = $questionThree;
        $request->IPID           = $ip;
        $request->Date           = $date;

        $this->save($request);
        $this->cache->deleteValue('num_public_requests');
        return $request;
    }

    public function getAllRequests($limit, int $userID = null, int $staffID = null, string $status = null, string $type = null) {
        $where = [];
        $params = [];

        if (!empty($userID)) {
            $where[]  = 'UserID = ?';
            $params[] = $userID;
        }

        if (!empty($staffID)) {
            $where[]  = 'StaffID = ?';
            $params[] = $staffID;
        }

        if (!empty($status)) {
            $where[]  = 'Status = ?';
            $params[] = $status;
        }

        if (!empty($type)) {
            $where[]  = 'Type = ?';
            $params[] = $type;
        }

        $sql = implode(' AND ', $where);
        return $this->findCount($sql, $params, 'ID DESC', $limit);
    }

    public function getOpenRequests($limit) {
        return $this->findCount('Status = ?', ['New'], null, $limit);
    }

    public function getResolvedRequests($limit, int $userID = null, int $staffID = null, string $status = null, string $type = null) {
        $sql = 'Status != ?';
        $params = ['New'];

        if (!empty($userID)) {
            $sql .= ' AND UserID = ?';
            $params[] = $userID;
        }

        if (!empty($staffID)) {
            $sql .= ' AND StaffID = ?';
            $params[] = $staffID;
        }

        if (!empty($status)) {
            $sql .= ' AND Status = ?';
            $params[] = $status;
        }

        if (!empty($type)) {
            $sql .= ' AND Type = ?';
            $params[] = $type;
        }

        return $this->findCount($sql, $params, 'ID DESC', $limit);
    }

    public function getRequestCountByUser($userID) {
        return $this->db->rawQuery(
            "SELECT COUNT(ID)
               FROM public_requests
              WHERE UserID = ?",
            [$userID]
        )->fetchColumn();
    }
}
