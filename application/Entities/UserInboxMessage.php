<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * UserInboxMessage Entity representing rows from the `pm_messages` DB table.
 */
class UserInboxMessage extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'pm_messages';


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
        'ID'        => [ 'type' => 'int', 'sqltype' => 'INT(12)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'ConvID'    => [ 'type' => 'int', 'sqltype' => 'INT(12)', 'nullable' => false, 'default' => '0' ],
        'SentDate'  => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => 'NULL', 'nullable' => true ],
        'SenderID'  => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'default' => '0' ],
        'Body'      => [ 'type' => 'str', 'sqltype' => 'MEDIUMTEXT', 'nullable' => false ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'ConvID'    => [ 'columns' => [ 'ConvID' ] ],
        'SenderID'  => [ 'columns' => [ 'SenderID' ] ],
    ];

    /**
     * canRead Returns whether the current user has permission to read messages in this conversation.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canRead() {
        return true;
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
            case 'author':
            case 'subject':
            case 'conversation':
            case 'AddedTime':
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
            case 'author':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->SenderID));
                }
                break;

            case 'subject':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->userInboxSubjects->load($this->ConvID)->Subject);
                }
                break;

            case 'conversation':
                if (!array_key_exists($name, $this->localValues)) {
                    $user = $this->request->user;
                    $this->safeSet($name, $this->repos->userInboxConversations->load([$user->ID, $this->ConvID]));
                }
                break;

            case 'AddedTime':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->SentDate);
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
