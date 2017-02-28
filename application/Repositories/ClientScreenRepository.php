<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\ClientScreen;

class ClientScreenRepository extends Repository {

    protected $entityName = 'ClientScreen';

    public function get_by_values($Width, $Height, $ColorDepth) {
        $ClientScreen = $this->get(
            '`Width` <=> ? AND `Height` <=> ? AND `ColorDepth` <=> ?',
            [$Width, $Height, $ColorDepth]
        );
        return $ClientScreen;
    }

}
