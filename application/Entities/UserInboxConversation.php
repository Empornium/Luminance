<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

use Luminance\Entities\User;
use Luminance\Entities\Restriction;
use Luminance\Entities\UserInboxMessage;
use Luminance\Entities\UserInboxSubject;

/**
 * UserPMConversationsUsers Entity representing rows from the `pm_conversations_users` DB table.
 */
class UserInboxConversation extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'pm_conversations_users';


    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'repos' => 'Repos',
    ];

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'UserID'        => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'default' => '0', 'primary' => true ],
        'ConvID'        => [ 'type' => 'int', 'sqltype' => 'INT(12)', 'nullable' => false, 'default' => '0', 'primary' => true ],
        'InInbox'       => [ 'type' => 'str', 'sqltype' => "ENUM('1','0')", 'nullable' => false ],
        'InSentbox'     => [ 'type' => 'str', 'sqltype' => "ENUM('1','0')", 'nullable' => false ],
        'SentDate'      => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => 'NULL', 'nullable' => true ],
        'ReceivedDate'  => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => 'NULL', 'nullable' => true ],
        'UnRead'        => [ 'type' => 'str', 'sqltype' => "ENUM('1','0')", 'nullable' => false, 'default' => '1' ],
        'Sticky'        => [ 'type' => 'str', 'sqltype' => "ENUM('1','0')", 'nullable' => false, 'default' => '0' ],
        'ForwardedTo'   => [ 'type' => 'int', 'sqltype' => 'INT(12)', 'nullable' => false, 'default' => '0' ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'ConvID'        => [ 'columns' => [ 'ConvID' ] ],
        'SentDate'      => [ 'columns' => [ 'SentDate' ] ],
        'ReceivedDate'  => [ 'columns' => [ 'ReceivedDate' ] ],
    ];

    /**
     * canReply Returns whether the current user may reply in this conversation.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canReply() {
        $user = $this->request->user;
        return ($this->sender instanceof User) && !($this->repos->restrictions->isRestricted($user->ID, Restriction::PM));
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
            case 'user':
            case 'subject':
            case 'messages':
            case 'sender':
            case 'recipient':
            case 'other':
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
            case 'user':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->UserID));
                }
                break;

            case 'subject':
                if (!array_key_exists($name, $this->localValues)) {
                    $subject = $this->repos->userInboxSubjects->load($this->ConvID);
                    if ($subject instanceof UserInboxSubject) {
                        $this->safeSet($name, $subject->Subject);
                    }
                }
                break;

            case 'messages':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->userInboxMessages->find('ConvID = ?', [$this->ConvID]));
                }
                break;

            case 'sender': # User who initiated the conversation
                if (!array_key_exists($name, $this->localValues)) {
                    # Gawd I hate this with a passion
                    $initialMessage = $this->repos->userInboxMessages->find('ConvID = ?', [$this->ConvID], 'SentDate ASC', '1');
                    $initialMessage = end($initialMessage);
                    if ($initialMessage instanceof UserInboxMessage) {
                        $sender = $this->repos->users->load($initialMessage->SenderID);
                        if ($sender instanceof User) {
                            $this->safeSet($name, $sender);
                        } else {
                            $this->safeSet($name, null);
                        }
                    } else {
                        $this->safeSet($name, null);
                    }
                }
                break;

            case 'recipient': # Target of that conversation initiation
                if (!array_key_exists($name, $this->localValues)) {
                    # Gawd I hate this with a passion
                    if ($this->sender instanceof User) {
                        $conversation = $this->repos->userInboxConversations->get('ConvID = ? AND UserID != ?', [$this->ConvID, $this->sender->ID]);
                    } else {
                        $conversation = $this;
                    }
                    if ($conversation instanceof UserInboxConversation) {
                        $recipient = $this->repos->users->load($conversation->UserID);
                        if ($recipient instanceof User) {
                            $this->safeSet($name, $recipient);
                        } else {
                            $this->safeSet($name, null);
                        }
                    } else {
                        $this->safeSet($name, null);
                    }
                }
                break;

            case 'other': # Conversation object for the other party (not me)
                if (!array_key_exists($name, $this->localValues)) {
                    $other = null;
                    if ($this->sender instanceof User) {
                        $user = $this->request->user;
                        $other = $this->repos->userInboxConversations->get('ConvID = ? AND UserID != ?', [$this->ConvID, $user->ID]);
                    }
                    if ($other instanceof UserInboxConversation) {
                        $this->safeSet($name, $other);
                    } else {
                        $this->safeSet($name, null);
                    }
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
