<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\Email;

use Luminance\Errors\InputError;

class EmailRepository extends Repository {

    protected $entityName = 'Email';

    public function get_by_address($Address) {
        $email = $this->get('`Address` = ?', [$Address]);
        return $email;
    }

    public function isAvailable($address) {
        // reduce fannies around with the domain. :(
        //$address = $this->reduceEmail($address);
        $email = $this->get_by_address($address);
        if ($email) {
            return false;
        }
        return true;
    }

    public function checkAvailable($address) {
        if (!$this->isAvailable($address))
            throw new InputError("That email address is not available.");
    }

    protected function get_emailblacklist_regex() {
        $pattern = $this->cache->get_value('emailblacklist_regex');
        if ($pattern==false) {
            $emails = $this->db->raw_query("SELECT Email as address FROM email_blacklist")->fetchAll(\PDO::FETCH_ASSOC);
            if (count($emails)>0) {
                $pattern = '@';
                $div = '';
                foreach ($emails as $email) {
                    $pattern .= $div . preg_quote($email['address'], '@');
                    $div = '|';
                }
                $pattern .= '@i';
                $this->cache->cache_value('emailblacklist_regex', $pattern);
            } else {
                $pattern = '@nohost.non@i';
            }
        }
        return $pattern;
    }

    public function isBlacklisted($address) {
        return preg_match($this->get_emailblacklist_regex(), $address);
    }

    public function checkBlacklisted($address) {
        if ($this->isBlacklisted($address))
            throw new InputError("That email address is blacklisted.");
    }

    /**
     * Check the format of the given e-mail
     *
     * @param string $address
     * @return bool
     */
    public function isValid($address) {
        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check the format of the given e-mail
     *
     * @param string $address
     * @return void
     *
     * @throws InputError if the e-mail is invalid
     */
    public function checkFormat($address) {
        if (!$this->isValid($address)) {
            throw new InputError("Invalid e-mail format.");
        }
    }
}
