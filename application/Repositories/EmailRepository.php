<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\Email;

class EmailRepository extends Repository {

    protected $entityName = 'Email';

    public function get_by_address($Address) {
        $email = $this->get('`Address` = ?', [$Address]);
        return $email;
    }
}
