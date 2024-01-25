<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Session Entity representing rows from the `sphinx_se` DB table.
 */
class SphinxSE extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'sphinx_se';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'id'       => [ 'type' => 'int', 'sqltype' => 'BIGINT UNSIGNED', 'nullable' => false ],
        'weight'   => [ 'type' => 'int', 'sqltype' => 'INT',             'nullable' => false ],
        'query'    => [ 'type' => 'str', 'sqltype' => 'VARCHAR(3072)',   'primary'  => true  ],
        'group_id' => [ 'type' => 'int', 'sqltype' => 'INT',             'nullable' => true  ],
    ];

    public static $attributes = [
        'engine'    => 'sphinx',
        'charset'   => 'latin1',
        'collate'   => 'latin1_general_ci',
    ];
}
