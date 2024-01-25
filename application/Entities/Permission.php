<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Permission Entity representing rows from the `permissions` DB table.
 */
class Permission extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'permissions';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'              => [ 'type' => 'int',    'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'Level'           => [ 'type' => 'int',    'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Name'            => [ 'type' => 'str',    'sqltype' => 'VARCHAR(25)', 'nullable' => false ],
        'Description'     => [ 'type' => 'str',    'sqltype' => 'VARCHAR(32)', 'nullable' => true ],
        'MaxSigLength'    => [ 'type' => 'int',    'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => false ],
        'MaxAvatarWidth'  => [ 'type' => 'int',    'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => false ],
        'MaxAvatarHeight' => [ 'type' => 'int',    'sqltype' => 'SMALLINT UNSIGNED', 'nullable' => false ],
        'Color'           => [ 'type' => 'str',    'sqltype' => 'CHAR(6)', 'nullable' => false ],
        'Forums'          => [ 'type' => 'str',    'sqltype' => 'VARCHAR(150)', 'nullable' => true ],
        'Values'          => [ 'type' => 'str',    'sqltype' => 'TEXT', 'nullable' => false ],
        'DisplayStaff'    => [ 'type' => 'str',    'sqltype' => "ENUM('0','1')", 'nullable' => false ],
        'IsUserClass'     => [ 'type' => 'str',    'sqltype' => "ENUM('0','1')", 'nullable' => false ],
        'isAutoPromote'   => [ 'type' => 'str',    'sqltype' => "ENUM('0','1')", 'nullable' => false ],
        'reqWeeks'        => [ 'type' => 'int',    'sqltype' => 'SMALLINT(5) UNSIGNED', 'default' => 100],
        'reqUploaded'     => [ 'type' => 'int',    'sqltype' => 'BIGINT(20) UNSIGNED',  'default' => 524288000],
        'reqTorrents'     => [ 'type' => 'int',    'sqltype' => 'SMALLINT(5) UNSIGNED', 'default' => 100],
        'reqForumPosts'   => [ 'type' => 'int',    'sqltype' => 'SMALLINT(5) UNSIGNED', 'default' => 100],
        'reqRatio'        => [ 'type' => 'double', 'sqltype' => 'DOUBLE(10,8)',  'default' => 99.99999999],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Level'        => [ 'columns' => [ 'Level' ] ],
        'DisplayStaff' => [ 'columns' => [ 'DisplayStaff' ] ],
        'IsUserClass'  => [ 'columns' => [ 'IsUserClass' ] ],
    ];

    /**
     * $unserialized Contains an array of the set permissions, null if none set.
     * @var array|null
     *
     * @access protected
     */
    protected $unserialized = null;

    /**
     * getUnserialized Returns an array of the set permissions, null if none set.
     * @return array|null Array of the set permission or null if none set.
     *
     * @access public
     */
    public function getUnserialized() {
        if (is_null($this->unserialized)) {
            $this->unserialized = unserialize($this->Values);
        }
        return $this->unserialized;
    }

    /**
     * getLegacy Returns legacy permission array.
     * @return array Array containing the legacy table columns and their corresponding values.
     */
    public function getLegacy() {
        $p = [];
        $p['Class'] = $this->Level;
        $p['Permissions'] = $this->getUnserialized();
        $p['MaxSigLength'] = $this->MaxSigLength;
        $p['MaxAvatarWidth'] = $this->MaxAvatarWidth;
        $p['MaxAvatarHeight'] = $this->MaxAvatarHeight;
        $p['DisplayStaff'] = $this->DisplayStaff;
        return $p;
    }
}
