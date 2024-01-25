<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * ForumRule Entity representing rows from the `forums_rules` DB table.
 */
class ForumRule extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'forums_rules';


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
        'ID'       => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'ForumID'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true, 'default' => null ],
        'ThreadID' => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => true, 'default' => null ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
      'ForumID'           => [ 'columns' => [ 'ForumID'  ] ],
      'ThreadID'          => [ 'columns' => [ 'ThreadID'  ] ],
    ];

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
            case 'thread':
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
            case 'thread':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->forumThreads->load($this->ThreadID));
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
