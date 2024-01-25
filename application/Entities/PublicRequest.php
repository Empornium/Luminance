<?php

namespace Luminance\Entities;

use Luminance\Core\Entity;

use Luminance\Errors\NotFoundError;

/**
 * PublicRequest Entity representing rows from the `public_requests` DB table.
 */
class PublicRequest extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'public_requests';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'db'            => 'DB',
        'cache'         => 'Cache',
        'tracker'       => 'Tracker',
        'inviteManager' => 'InviteManager',
        'emailManager'  => 'EmailManager',
        'repos'         => 'Repos',
    ];

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'            => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary'  => true, 'auto_increment' => true],
        'Type'          => ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false],
        'Status'        => ['type' => 'str', 'sqltype' => "ENUM('New','Accepted','Rejected','Summoned')", 'default' => "'New'", 'nullable' => true ],
        'UserID'        => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'StaffID'       => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'EmailID'       => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false],
        'ApplicantEmail'=> ['type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => true],
        'IPID'          => ['type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true ],
        'Extra'         => ['type' => 'str', 'sqltype' => 'TEXT',         'nullable' => false],
        'Proof'         => ['type' => 'str', 'sqltype' => 'VARCHAR(75)',  'nullable' => false],
        'ProofTwo'      => ['type' => 'str', 'sqltype' => 'VARCHAR(75)',  'nullable' => false],
        'ProofThree'    => ['type' => 'str', 'sqltype' => 'VARCHAR(75)',  'nullable' => false],
        'QuestionOne'   => ['type' => 'str', 'sqltype' => 'TEXT',  'nullable' => false],
        'QuestionTwo'   => ['type' => 'str', 'sqltype' => 'TEXT',  'nullable' => false],
        'QuestionThree' => ['type' => 'str', 'sqltype' => 'TEXT',  'nullable' => false],
        'Notes'         => ['type' => 'str', 'sqltype' => 'TEXT',  'nullable' => true],
        'Date'          => ['type' => 'timestamp', 'nullable' => false]
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID' => ['columns' => ['UserID']],
        'IPID'   => ['columns' => ['IPID']]
    ];

    /**
     * accept Enables a user, sending them an email and resolves the pending public request
     * @param string  $notes    Staff notes to be added to the account
     *
     * @access public
     */
    public function accept($notes) {
        # Anit-ninja
        if (!($this->Status === 'New')) {
            throw new NotFoundError;
        }

        $staffNotes = sqltime()." - Account Disabled->Enabled by {$this->master->request->user->Username}";
        if (!empty($notes)) {
            $staffNotes .= "\nReason: ".$notes;
        }

        $exception = new \DateTime('+72 hour');
        $this->db->rawQuery(
            'UPDATE users_main AS um
               JOIN users_info AS ui ON um.ID = ui.UserID
                SET um.Enabled = ?,
                    can_leech = ?,
                    ui.InactivityException = ?,
                    ui.AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
              WHERE um.ID = ?',
            ['1', '1', $exception->format('Y-m-d H:i:s'), $staffNotes, $this->user->ID]
        );
        $this->tracker->addUser($this->user->legacy['torrent_pass'], $this->user->ID);
        $this->repos->users->uncache($this->user);

        # Populate email_body stuff first
        $subject = 'Account Reactivation';
        $variables = [];
        $variables['user']     = $this->user;
        $variables['scheme']   = $this->master->request->ssl ? 'https' : 'http';

        $this->email->sendEmail($subject, 'account_reactivation_accepted', $variables);

        $this->Status = 'Accepted';
        $this->StaffID = $this->master->request->user->ID;
        $this->repos->publicRequests->save($this);

        $this->cache->deleteValue('num_public_requests');
    }

    /**
     * reject Does not enable the user, sends them an email and resolves the pending public request
     * @param string  $notes    Staff notes to be added to the account
     *
     * @access public
     */
    public function reject($notes) {
        # Anit-ninja
        if (!($this->Status === 'New')) {
            throw new NotFoundError;
        }

        $staffNotes = sqltime()." - Reactivation request rejected by {$this->master->request->user->Username}";
        if (!empty($notes)) {
            $staffNotes .= "\nReason: ".$notes;
        }

        $this->db->rawQuery(
            'UPDATE users_main AS um
               JOIN users_info AS ui ON um.ID = ui.UserID
                SET ui.AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
              WHERE um.ID = ?',
            [$staffNotes, $this->user->ID]
        );
        $this->repos->users->uncache($this->user);

        # Populate email_body stuff first
        $subject = 'Account Reactivation';
        $variables = [];
        $variables['user']     = $this->user;
        $variables['scheme']   = $this->master->request->ssl ? 'https' : 'http';

        $this->email->sendEmail($subject, 'account_reactivation_rejected', $variables);

        $this->Status = 'Rejected';
        $this->StaffID = $this->master->request->user->ID;
        $this->repos->publicRequests->save($this);

        $this->cache->deleteValue('num_public_requests');
    }

    /**
     * summon Does not enable the user, sends them an email and resolves the pending public request
     * @param string  $notes    Staff notes to be added to the account
     *
     * @access public
     */
    public function summon($notes) {
        # Anti-ninja
        if (!($this->Status === 'New')) {
            throw new NotFoundError;
        }

        $staffNotes = sqltime()." - User summoned to IRC to discuss their reactivation request by {$this->master->request->user->Username}";
        if (!empty($notes)) {
            $staffNotes .= "\nReason: ".$notes;
        }

        $this->db->rawQuery(
            'UPDATE users_main AS um
               JOIN users_info AS ui ON um.ID = ui.UserID
                SET ui.AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
              WHERE um.ID = ?',
            [$staffNotes, $this->user->ID]
        );
        $this->repos->users->uncache($this->user);

        # Populate email_body stuff first
        $subject = 'Account Reactivation';
        $variables = [];
        $variables['user']     = $this->user;
        $variables['scheme']   = $this->master->request->ssl ? 'https' : 'http';

        $this->email->sendEmail($subject, 'account_reactivation_summoned', $variables);

        $this->Status = 'Summoned';
        $this->StaffID = $this->master->request->user->ID;
        $this->repos->publicRequests->save($this);

        $this->cache->deleteValue('num_public_requests');
    }

     /**
     * acceptApplicant Accepts an applicant, sending them an email and resolves the pending public request
     * @param string  $notes    Staff notes to be added to the account
     * @param string  $email    Email address to send invite email to
     * @param int     $ID       ID of the application
     *
     * @access public
     */
    public function acceptApplicant($ID, $notes, $email) {
        # Anti-ninja
        if (!($this->Status === 'New')) {
            throw new NotFoundError;
        }

        $comment = sqltime()." - User application accepted by {$this->master->request->user->Username}";
        if (!empty($notes)) {
            $comment .= "\nAdditional: ".$notes;
        }
        $request = $this->repos->publicRequests->load($ID);
        $request->Notes     = $comment;
        $this->repos->publicRequests->save($request);

        $userID = $this->master->request->user->ID;
        $invite = $this->inviteManager->newInvite($userID, $email, 1, $comment);
        $this->emailManager->sendAcceptEmail($invite);

        $this->repos->users->uncache($this->user);

        $this->Status = 'Accepted';
        $this->StaffID = $this->master->request->user->ID;
        $this->repos->publicRequests->save($this);

        $this->cache->deleteValue('num_public_requests');
    }

     /**
     * acceptSummApplicant Accepts an applicant who has been previously summoned, sends them an email, and resolves the pending public request
     * @param string  $notes    Staff notes to be added to the account
     * @param string  $email    Email address to send invite email to
     * @param int     $ID       ID of the application
     *
     * @access public
     */
    public function acceptSummApplicant($ID, $notes, $email) {
        # Anti-ninja
        if (!($this->Status === 'Summoned')) {
            throw new NotFoundError;
        }

        $comment = sqltime()." - User was summoned and has passed IRC questioning by {$this->master->request->user->Username}";
        if (!empty($notes)) {
            $comment .= "\nAdditional: ".$notes;
        }
        $request = $this->repos->publicRequests->load($ID);
        $request->Notes     = $comment;
        $this->repos->publicRequests->save($request);

        $userID = $this->master->request->user->ID;
        $invite = $this->inviteManager->newInvite($userID, $email, 1, $comment);
        $this->emailManager->sendAcceptEmail($invite);

        $this->repos->users->uncache($this->user);

        $this->Status = 'Accepted';
        $this->StaffID = $this->master->request->user->ID;
        $this->repos->publicRequests->save($this);

        $this->cache->deleteValue('num_public_requests');
    }

    /**
     * rejectApplicant Does not accept the user, sends them an email and resolves the pending public request
     * @param string  $notes    Notes to be added to the public request
     * @param string  $email    Email address to send rejection email to
     * @param int     $ID       ID of the application
     *
     * @access public
     */
    public function rejectApplicant($notes, $email, $ID) {
        # Anti-ninja
        if (!($this->Status === 'New')) {
            throw new NotFoundError;
        }

        $requestNotes = sqltime()." - Application rejected by {$this->master->request->user->Username}";
        if (!empty($notes)) {
            $requestNotes .= "\nReason: ".$notes;
        }

        $request = $this->repos->publicRequests->load($ID);
        $request->Notes     = $requestNotes;
        $this->repos->publicRequests->save($request);

        $this->repos->users->uncache($this->user);

        $this->emailManager->sendApplicationEmail($email, 'email/application_rejected.email.twig');

        $this->Status = 'Rejected';
        $this->StaffID = $this->master->request->user->ID;
        $this->repos->publicRequests->save($this);

        $this->cache->deleteValue('num_public_requests');
    }

    /**
     * summon Does not enable the user, sends them an email and resolves the pending public request
     * @param string  $notes    Notes to be added to the public request
     * @param string  $email    Email address to send summoning email to
     * @param int     $ID       ID of the application
     *
     * @access public
     */
    public function summonApplicant($notes, $email, $ID) {
        # Anti-ninja
        if (!($this->Status === 'New')) {
            throw new NotFoundError;
        }

        $requestNotes = sqltime()." - User summoned to IRC to discuss their application request by {$this->master->request->user->Username}";
        if (!empty($notes)) {
            $requestNotes .= "\nReason: ".$notes;
        }

        $request = $this->repos->publicRequests->load($ID);
        $request->Notes     = $requestNotes;
        $this->repos->publicRequests->save($request);

        $this->repos->users->uncache($this->user);

        $this->emailManager->sendApplicationEmail($email, $request, 'email/application_summoned.email.twig');

        $this->Status = 'Summoned';
        $this->StaffID = $this->master->request->user->ID;
        $this->repos->publicRequests->save($this);

        $this->cache->deleteValue('num_public_requests');
    }

    /**
     * __isset returns whether an object property exists or not,
     * this is necessary for lazy loading to function correctly from TWIG
     * @param  string  $name Name of property being checked
     * @return bool          True if property exists, false otherwise
     *
     * @access public
     */
    public function __isset($name) {
        switch ($name) {
            case 'ip':
            case 'user':
            case 'email':
                return true;

            default:
                return parent::__isset($name);
        }
    }


    /**
     * __get returns the property requested, loading it from the DB if necessary,
     * this permits us to perform lazy loading and thus dynamically minimize both
     * memory usage and cache/DB usage.
     * @param  string $name Name of property being accessed
     * @return mixed        Property data (could be anything)
     *
     * @access public
     */
    public function __get($name) {

        switch ($name) {
            case 'ip':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->ips->load($this->IPID));
                }
                break;

            case 'user':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->UserID));
                }
                break;

            case 'email':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->emails->load($this->EmailID));
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
