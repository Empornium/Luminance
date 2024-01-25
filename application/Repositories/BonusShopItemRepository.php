<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

class BonusShopItemRepository extends Repository {

    protected $entityName = 'BonusShopItem';

    public function getItemsUFL() {
        if (empty($itemsUFL)) {
            $itemsUFL = $this->find("Action = 'ufl' AND Gift = '0'", [], 'Value DESC');
        }

        return $itemsUFL;
    }
}
