<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * GeoLite2ASN Entity representing rows from the `geolite2_asn` DB table.
 */
class GeoLite2ASN extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'geolite2_asn';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'           => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED',  'primary' => true, 'auto_increment' => true ],
        'StartAddress' => [ 'type' => 'str', 'sqltype' => 'VARBINARY(16)', 'nullable' => false ],
        'EndAddress'   => [ 'type' => 'str', 'sqltype' => 'VARBINARY(16)', 'nullable' => true  ],
        'ASN'          => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED',  'nullable' => true  ],
        'ISP'          => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)',  'nullable' => true  ],

    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'StartAddress' => [ 'columns' => [ 'StartAddress' ] ],
    ];
}
