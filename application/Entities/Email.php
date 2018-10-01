<?php
namespace Luminance\Entities;

use Luminance\Core\Entity;

class Email extends Entity {

    public static $table = 'emails';

    protected static $useServices = [
        'settings'  => 'Settings',
        'flasher'   => 'Flasher',
        'render'    => 'Render',
    ];

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

    public function ready_to_resend() {
        $treshold = new \DateTime('-1 hour');
        return ($this->Changed < $treshold);
    }

    public function is_default() {
        return $this->getFlag(self::IS_DEFAULT);
    }

    public function is_encrypted() {
        return $this->getFlag(self::ENCRYPTED);
    }

    public function is_confirmed() {
        return $this->getFlag(self::VALIDATED);
    }

    public function is_cancelled() {
        return $this->getFlag(self::CANCELLED);
    }

    public function send_email($subject, $template, $variables) {
        $headerVariables = [
            'to'          => $this->Address,
            'from'        => 'noreply',
            'contentType' => 'text/plain',
        ];

        $variables['to'] = $this->Address;

        if ($this->settings->site->debug_mode && file_exists($this->master->application_path . "/Templates/email/{$template}.flash.twig")) {
            $body = $this->render->render("email/{$template}.flash.twig", $variables);
            $this->flasher->notice($body);
        } else {
            $headers = str_replace("\n", "\r\n", $this->render->render("email/headers.twig", $headerVariables));
            $body = $this->render->render("email/{$template}.email.twig", $variables);

            mail($this->Address, $subject, $body, $headers, "-f " . $headerVariables['from'] . "@" . $this->settings->main->mail_domain);
        }
    }
}
