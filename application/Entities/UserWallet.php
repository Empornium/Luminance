<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Option UserWallet representing rows from the `users_credits` DB table.
 */
class UserWallet extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'users_wallets';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'db'     => 'DB',
        'repos'  => 'Repos',
    ];

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'              => [ 'type' => 'int',   'sqltype' => 'INT(10)',      'nullable' => false, 'auto_increment' => true, 'primary' => true   ],
        'UserID'          => [ 'type' => 'int',   'sqltype' => 'INT(10)',      'nullable' => false,                     ],
        'BalanceDaily'    => [ 'type' => 'float', 'sqltype' => 'DOUBLE(11,2)', 'nullable' => false, 'default' => '0.00' ],
        'Balance'         => [ 'type' => 'float', 'sqltype' => 'DOUBLE(11,2)', 'nullable' => false, 'default' => '0.00' ],
        'SeedHoursDaily'  => [ 'type' => 'float', 'sqltype' => 'DOUBLE(11,2)', 'nullable' => false, 'default' => '0.00' ],
        'SeedHours'       => [ 'type' => 'float', 'sqltype' => 'DOUBLE(11,2)', 'nullable' => false, 'default' => '0.00' ],
        'Log'             => [ 'type' => 'str',   'sqltype' => 'MEDIUMTEXT',   'nullable' => false, 'default' => ''     ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID'     => [ 'columns' => [ 'UserID' ] ],
    ];

    /**
     * adjustBalance Modifies the user's credit balance directly in the DB.
     * @param  int     $change   Amount by which to modify the balance, can be negative.
     *
     * @access public
     */
    public function adjustBalance($change) {
        # Update directly in the DB to avoid race condition with scheduler
        $this->db->rawQuery(
            'UPDATE users_wallets
                 SET Balance = IF(Balance + ? < 0, 0, Balance + ?)
              WHERE ID = ?',
            [$change, $change, $this->ID]
        );
        $this->repos->userWallets->uncache($this->ID);
    }

    /**
     * addLog Modifies the user's credit log directly in the DB.
     * @param  string     $message   Message to append to the log
     *
     * @access public
     */
    public function addLog($message) {
          $message = sqltime() . $message;
          # Update directly in the DB to avoid race condition with scheduler
          $this->db->rawQuery(
              'UPDATE users_wallets
                  SET Log = CONCAT_WS(CHAR(10 using utf8), ?, Log)
                WHERE ID = ?',
              [$message, $this->ID]
          );
          $this->repos->userWallets->uncache($this->ID);
    }
}
