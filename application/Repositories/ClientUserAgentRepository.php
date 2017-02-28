<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\ClientUserAgent;

class ClientUserAgentRepository extends Repository {

    protected $entityName = 'ClientUserAgent';

    public function get_by_string($String) {
        $ClientUserAgent = $this->get('`String` = ?', [$String]);
        return $ClientUserAgent;
    }

}
