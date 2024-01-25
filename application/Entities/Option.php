<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Option Entity representing rows from the `options` DB table.
 */
class Option extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'options';

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'Name'    => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'primary' => true, 'nullable' => false],
        'Value'   => [ 'type' => 'str', 'sqltype' => 'TEXT', 'nullable' => false],
    ];
}
