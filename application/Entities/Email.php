<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

/**
 * Email Entity representing rows from the `emails` DB table.
 */
class Email extends Entity {

    /**
     * $table contains a string identifying the DB table this entity is related to.
     * @var string
     *
     * @access public
     * @static
     */
    public static $table = 'emails';

    /**
     * $useServices represents a mapping of the Luminance services which should be injected into this object during creation.
     * @var array
     *
     * @access protected
     * @static
     */
    protected static $useServices = [
        'settings'  => 'Settings',
        'flasher'   => 'Flasher',
        'render'    => 'Render',
        'repos'     => 'Repos',
    ];

    /**
     * DB rows and their respective parameters.
     * @var array
     *
     * @access public
     * @static
     */
    public static $properties = [
        'ID'      => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'primary' => true, 'auto_increment' => true ],
        'UserID'  => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'IPID'    => [ 'type' => 'int', 'sqltype' => 'INT UNSIGNED', 'nullable' => false ],
        'Address' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Reduced' => [ 'type' => 'str', 'sqltype' => 'VARCHAR(255)', 'nullable' => false ],
        'Created' => [ 'type' => 'timestamp', 'nullable' => true ],
        'Changed' => [ 'type' => 'timestamp', 'nullable' => true ],
        'Flags'   => [ 'type' => 'int', 'sqltype' => 'TINYINT UNSIGNED', 'nullable' => false ],
    ];

    /**
     * DB indexes.
     * @var array
     *
     * @access public
     * @static
     */
    public static $indexes = [
        'UserID'  => [ 'columns' => [ 'UserID'  ] ],
        'Address' => [ 'columns' => [ 'Address' ] ],
        'Reduced' => [ 'columns' => [ 'Reduced' ] ],
        'Flags'   => [ 'columns' => [ 'Flags'   ] ],
    ];

    const VALIDATED  = 1 << 0;
    const CANCELLED  = 1 << 1;
    const IS_DEFAULT = 1 << 2;
    const ENCRYPTED  = 1 << 3;
    const QUIET      = 1 << 4;

    /**
     * readyToResend returns bool showing whether or not the user can request a resend.
     * @return bool True if user can request a resend, false otherwise.
     *
     * @access public
     */
    public function readyToResend() {
        $threshold = new \DateTime('-1 hour');
        return ($this->Changed < $threshold);
    }

    /**
     * isDefault Returns whether or not the IS_DEFAULT flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isDefault() {
        return $this->getFlag(self::IS_DEFAULT);
    }

    /**
     * isEncrypted Returns whether or not the ENCRYPTED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isEncrypted() {
        return $this->getFlag(self::ENCRYPTED);
    }

    /**
     * isConfirmed Returns whether or not the VALIDATED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isConfirmed() {
        return $this->getFlag(self::VALIDATED);
    }

    /**
     * isCancelled Returns whether or not the CANCELLED flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isCancelled() {
        return $this->getFlag(self::CANCELLED);
    }

    /**
     * isQuiet Returns whether or not the QUIET flag is set in this object.
     * @return bool True if flag is set, false otherwise.
     *
     * @access public
     */
    public function isQuiet() {
        return $this->getFlag(self::QUIET);
    }

    /**
     * sendEmail Sends an email to this object's address.
     * @param  string     $subject   Subject line for this email.
     * @param  string     $template  TWIG template identifier.
     * @param  array|null $variables Variables to be passed to TWIG for rendering or null if none applicable.
     *
     * @access public
     */
    public function sendEmail(string $subject, string $template, $variables) {
        $headerVariables = [
            'to'          => $this->Address,
            'from'        => 'noreply',
            'contentType' => 'text/plain',
        ];

        $variables['to'] = $this->Address;

        if ($this->settings->site->debug_mode) {
            if (file_exists($this->master->applicationPath . "/Templates/email/{$template}.flash.twig")) {
                $body = $this->render->template("email/{$template}.flash.twig", $variables);
            } else {
                $body = $this->render->template("email/{$template}.email.twig", $variables);
            }
            $this->flasher->notice($body);
        } else {
            $headers = str_replace("\n", "\r\n", $this->render->template("email/headers.twig", $headerVariables));
            $body = $this->render->template("email/{$template}.email.twig", $variables);

            mail($this->Address, $subject, $body, $headers, "-f " . $headerVariables['from'] . "@" . $this->settings->main->mail_domain);
        }
    }

    /**
     * __toString object magic function
     * @return string Email address represented by this object as a string
     *
     * @access public
     */
    public function __toString() {
        return $this->Address;
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
            case 'user':
            case 'ip':
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
            case 'user':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->users->load($this->UserID));
                }
                break;

            case 'ip':
                if (!array_key_exists($name, $this->localValues)) {
                    $this->safeSet($name, $this->repos->ips->load($this->IPID));
                }
                break;
        }

        # Just return from the parent
        return parent::__get($name);
    }
}
