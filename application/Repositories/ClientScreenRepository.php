<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

class ClientScreenRepository extends Repository {

    protected $entityName = 'ClientScreen';

    public function getByValues($width, $height, $colorDepth) {
        $clientScreen = $this->get(
            '`Width` <=> ? AND `Height` <=> ? AND `ColorDepth` <=> ?',
            [$width, $height, $colorDepth]
        );
        return $clientScreen;
    }
}
