<?php
namespace Luminance\Repositories;

use Luminance\Core\Entity;
use Luminance\Core\Repository;

class ArticleRepository extends Repository {

    protected $entityName = 'Article';

    public function getByTopic($topic) {
        return $this->get('`TopicID` = ?', [$topic], "article_topic_{$topic}");
    }

    /**
     * Save Article entity to DB and clear cache
     * @param Entity $entity object to be saved
     *
     */
    public function save(Entity &$entity, $allowUpdate = false) {
        # First check if there's anything to actually save!
        if (!$entity->needsSaving()) {
            return;
        }
        $this->cache->deleteValue("all_articles");
        $this->cache->deleteValue("articles_{$entity->Category}");
        $this->cache->deleteValue("articles_sub_{$entity->Category}_{$entity->SubCat}");
        $this->cache->deleteValue("article_topic_{$entity->TopicID}");
        parent::save($entity, $allowUpdate);
    }
}
