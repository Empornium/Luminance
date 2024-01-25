<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

class ClientUserAgentRepository extends Repository {

    protected $entityName = 'ClientUserAgent';

    public function getByString($string) {
        $clientUserAgent = $this->get('`String` = ?', [$string]);
        return $clientUserAgent;
    }
}
