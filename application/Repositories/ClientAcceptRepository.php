<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\ClientAccept;

class ClientAcceptRepository extends Repository {

    protected $entityName = 'ClientAccept';

    public function get_by_values($String, $Charset, $Encoding, $Language) {
        $ClientUserAgent = $this->get(
            '`Accept` <=> ? AND `AcceptCharset` <=> ? AND `AcceptEncoding` <=> ? AND `AcceptLanguage` <=> ?',
            [$String, $Charset, $Encoding, $Language]
        );
        return $ClientUserAgent;
    }

}
