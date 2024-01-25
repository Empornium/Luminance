<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

class ClientAcceptRepository extends Repository {

    protected $entityName = 'ClientAccept';

    public function getByValues($string, $charset, $encoding, $language) {
        $clientUserAgent = $this->get(
            '`Accept` <=> ? AND `AcceptCharset` <=> ? AND `AcceptEncoding` <=> ? AND `AcceptLanguage` <=> ?',
            [$string, $charset, $encoding, $language]
        );
        return $clientUserAgent;
    }
}
