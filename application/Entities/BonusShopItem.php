<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * BonusShopItem Entity representing rows from the `bonus_shop_actions` DB table.
 */
class BonusShopItem extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'bonus_shop_actions';

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
        'ID'           => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false, 'auto_increment' => true, 'primary' => true ],
        'Title'        => [ 'type' => 'str', 'sqltype' => 'VARCHAR(256)', 'nullable' => false ],
        'Description'  => [ 'type' => 'str', 'sqltype' => 'VARCHAR(1024)', 'nullable' => false ],
        'Action'       => [ 'type' => 'str', 'sqltype' => "ENUM('gb','givegb','givecredits','slot','title','badge','pfl','ufl','invite')", 'nullable' => false ],
        'Value'        => [ 'type' => 'int', 'sqltype' => 'INT(10)', 'nullable' => false, 'default' => '0' ],
        'Cost'         => [ 'type' => 'int', 'sqltype' => 'INT(9)', 'nullable' => false ],
        'Sort'         => [ 'type' => 'int', 'sqltype' => 'INT(6)', 'nullable' => false ],
        'Gift'         => [ 'type' => 'int', 'sqltype' => 'TINYINT(1)', 'nullable' => false, 'default' => '0' ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'Sort'         => [ 'columns' => [ 'Sort' ] ],
    ];

    /**
     * canBuy Returns whether user has enough credits to purchase this item.
     * @param  User|int      $user  User object or UserID integer.
     * @return bool                 True if user is permitted, false otherwise.
     *
     * @access public
     */
    public function canBuy($user) {
        $user = $this->repos->users->load($user);

        $canBuy = false;
        if (is_float($user->wallet->Balance)) {
            $canBuy = $user->wallet->Balance >= $this->Cost;
        }

        return $canBuy;
    }
}
