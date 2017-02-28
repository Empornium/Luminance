<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\Session;

class ClientRepository extends Repository {

    protected $entityName = 'Client';

    public function getByCID($CID) {
        $client = $this->get('`CID` = ?', [$CID]);
        return $client;
    }
}
