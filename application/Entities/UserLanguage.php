<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * UserLanguage Entity representing rows from the `users_languages` DB table.
 */
class UserLanguage extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'users_languages';

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
        'UserID'  => [ 'type' => 'int', 'sqltype' => 'INT(10)',     'nullable' => false, 'primary' => true ],
        'LangID'  => [ 'type' => 'int', 'sqltype' => 'SMALLINT(3)', 'nullable' => false, 'primary' => true ],
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
            case 'language':
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
            case 'language':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->languages->load($this->LangID));
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
