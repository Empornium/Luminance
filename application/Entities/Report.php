<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Report Entity representing rows from the `reportsv2` DB table.
 */
class Report extends Entity {

    use \Luminance\Legacy\sections\reportsv2\types;

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'reportsv2';

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
        'ID'              => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'ReporterID'      => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'default' => '0' ],
        'TorrentID'       => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'default' => '0' ],
        'Type'            => [ 'type' => 'str', 'sqltype' => 'VARCHAR(20)', 'default' => '', 'nullable' => true ],
        'UserComment'     => [ 'type' => 'str', 'sqltype' => 'TEXT', 'nullable' => false ],
        'ResolverID'      => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'default' => '0' ],
        'Status'          => [ 'type' => 'str', 'sqltype' => "ENUM('New','InProgress','Resolved')", 'default' => "'New'", 'nullable' => true ],
        'ReportedTime'    => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => 'NULL', 'nullable' => true ],
        'LastChangeTime'  => [ 'type' => 'timestamp', 'sqltype' => 'DATETIME', 'default' => 'NULL', 'nullable' => true ],
        'ModComment'      => [ 'type' => 'str', 'sqltype' => 'TEXT', 'nullable' => false ],
        'Track'           => [ 'type' => 'str', 'sqltype' => 'TEXT', 'default' => 'NULL', 'nullable' => true ],
        'Image'           => [ 'type' => 'str', 'sqltype' => 'TEXT', 'default' => 'NULL', 'nullable' => true ],
        'ExtraID'         => [ 'type' => 'str', 'sqltype' => 'TEXT', 'default' => 'NULL', 'nullable' => true ],
        'Link'            => [ 'type' => 'str', 'sqltype' => 'TEXT', 'default' => 'NULL', 'nullable' => true ],
        'LogMessage'      => [ 'type' => 'str', 'sqltype' => 'TEXT', 'default' => 'NULL', 'nullable' => true ],
        'Credit'          => [ 'type' => 'str', 'sqltype' => "ENUM('0','1')", 'nullable' => false, 'default' => "'0'" ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Status'          => [ 'columns' => [ 'Status' ] ],
        'Type'            => [ 'columns' => [ 'Type' ] ],
        'LastChangeTime'  => [ 'columns' => [ 'LastChangeTime' ] ],
        'ReporterID'      => [ 'columns' => [ 'ReporterID' ] ],
        'TorrentID'       => [ 'columns' => [ 'TorrentID' ] ],
        'ResolverID'      => [ 'columns' => [ 'ResolverID' ] ],
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
            case 'reporter':
            case 'type':
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
            case 'reporter':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->ReporterID));
                }
                break;

            case 'type':
                $types = self::getTypes();
                if (!array_key_exists($name, $this->localValues)) {
                    if (array_key_exists($this->Type, $types)) {
                        $type = $types[$this->Type];
                    } else {
                        //There was a type but it wasn't an option!
                        $type = $types['other'];
                    }

                    $this->safeSet($name, $type);
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
