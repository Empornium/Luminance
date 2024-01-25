<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

use Luminance\Entities\Email;

use Luminance\Errors\InputError;

class EmailRepository extends Repository {

    protected $entityName = 'Email';

    public function getByAddress($address) {
        $email = $this->get('`Address` = ?', [$address], "address_{$address}");
        return $email;
    }

    public function isAvailable($address) {
        # reduce fannies around with the domain. :(
        #$address = $this->reduceEmail($address);
        $email = $this->getByAddress($address);
        if ($email instanceof Email) {
            return false;
        }
        return true;
    }

    public function checkAvailable($address) {
        if (!$this->isAvailable($address))
            throw new InputError("That email address is not available.");
    }

    protected function getEmailblacklistRegex() {
        $pattern = $this->cache->getValue('emailblacklist_regex');
        if (empty($pattern)) {
            $emails = $this->db->rawQuery("SELECT Email as address FROM email_blacklist")->fetchAll(\PDO::FETCH_ASSOC);
            if (count($emails)>0) {
                $pattern = '@';
                $div = '';
                foreach ($emails as $email) {
                    $pattern .= $div . preg_quote($email['address'], '@');
                    $div = '|';
                }
                $pattern .= '@i';
                $this->cache->cacheValue('emailblacklist_regex', $pattern);
            } else {
                $pattern = '@nohost.non@i';
            }
        }
        return $pattern;
    }

    public function isBlacklisted($address) {
        return preg_match($this->getEmailblacklistRegex(), $address);
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
        return !(filter_var($address, FILTER_VALIDATE_EMAIL) === false);
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

    /**
     * Delete Email entity from cache
     * @param int|Entity $email email to uncache
     *
     */
    public function uncache($email) {
        $email = $this->load($email);
        if ($email instanceof Email) {
            parent::uncache($email);
            $this->cache->deleteValue("_query_Email_Address_{$email->Address}");
        }
    }
}
