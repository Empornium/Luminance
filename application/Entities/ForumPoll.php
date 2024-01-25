<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ForumPoll Entity representing rows from the `forums_polls` DB table.
 */
class ForumPoll extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'forums_polls';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'cache'         => 'Cache',
        'db'            => 'DB',
    ];

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'        => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',  'primary'  => true, 'auto_increment' => true ],
        'ThreadID'  => [ 'type' => 'int',       'sqltype' => 'INT UNSIGNED',  'nullable' => false ],
        'Question'  => [ 'type' => 'str',       'sqltype' => 'VARCHAR(255)',  'nullable' => false ],
        'Answers'   => [ 'type' => 'str',       'sqltype' => 'TEXT',          'nullable' => false ],
        'Featured'  => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME',      'nullable' => true,  'default' => null ],
        'Closed'    => [ 'type' => 'str',       'sqltype' => "ENUM('0','1')", 'nullable' => false, 'default' => '0' ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'ThreadID'  => [ 'columns' => [ 'ThreadID' ] ],
    ];

    private $answers = null;
    private $userVotes = null;

    /**
     * isFeatured Returns whether this poll is currently featured.
     * @return bool True if featured, false otherwise.
     *
     * @access public
     */
    public function isFeatured() {
        $threadID = $this->cache->getValue('polls_featured');
        if (empty($threadID)) {
            $threadID = $this->db->rawQuery("SELECT ThreadID FROM forums_polls WHERE Featured IS NOT NULL ORDER BY Featured DESC LIMIT 1")->fetchColumn();
            $this->cache->cacheValue('polls_featured', $threadID, 0);
        }
        return $threadID === $this->ThreadID;
    }

    /**
     * answers Returns an unserialized version of the Answers column.
     * @return array|bool Array containing the unserialized version of the Answers column or False on failure.
     *
     * @access public
     */
    public function answers() {
        if (is_null($this->answers)) {
            $this->answers = @unserialize($this->Answers);
        }

        return $this->answers;
    }

    /**
     * userVotes Returns an array of the specified users votes.
     * @param  int    $userID Integer ID of the user whose votes should be returned.
     * @return array          Array containing the votes for this poll.
     *
     * @access public
     */
    public function userVotes($userID) {
        if (is_null($this->userVotes)) {
            $votes = $this->db->rawQuery(
                "SELECT Vote
                   FROM forums_polls_votes
                  WHERE ThreadID=?
                    AND UserID=?",
                [$this->ThreadID, $userID]
            )->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($votes)) {
                $this->userVotes = array_column($votes, 'Vote');
            } else {
                $this->userVotes = [];
            }
        }
        return $this->userVotes;
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
            case 'votes':
            case 'maxVotes':
            case 'totalVotes':
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
            case 'votes':
                if (!array_key_exists($name, $this->localValues)) {
                    $votes = $this->cache->getValue("forum_poll_votes_{$this->ThreadID}");
                    if ($votes === false) {
                        $votes = $this->db->rawQuery(
                            "SELECT Vote,
                                    COUNT(UserID) AS total,
                                    GROUP_CONCAT(u.Username SEPARATOR ', ') AS names
                               FROM forums_polls_votes AS fpv
                               JOIN users AS u ON fpv.UserID=u.ID
                              WHERE ThreadID=?
                           GROUP BY Vote",
                            [$this->ThreadID]
                        )->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
                        $this->cache->cacheValue("forum_poll_votes_{$this->ThreadID}", $votes, 0);
                    }
                    $this->safeSet($name, $votes);
                }
                break;

            case 'maxVotes':
                $maxVotes = max(array_column($this->votes, 'total'));
                $this->safeSet($name, $maxVotes);
                break;

            case 'totalVotes':
                $totalVotes = array_sum(array_column($this->votes, 'total'));
                $this->safeSet($name, $totalVotes);
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
