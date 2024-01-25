<?php
namespace Luminance\Repositories;

use Luminance\Core\Entity;
use Luminance\Core\Repository;

class TorrentCommentRepository extends Repository {

    protected $entityName = 'TorrentComment';

    /**
     * Save TorrentComment entity to DB and clear cache
     * @param Entity $tag object to be saved
     *
     */
    public function save(Entity &$entity, $allowUpdate = false) {
        # First check if there's anything to actually save!
        if (!$entity->needsSaving()) {
            return;
        }
        $this->cache->deleteValue("torrent_comments_{$entity->GroupID}");
        parent::save($entity, $allowUpdate);
    }

    /**
     * Delete TorrentComment entity from cache
     * @param int|TorrentComment $comment Torrent Comment to uncache
     *
     */
    public function uncache($comment) {
        $comment = $this->load($comment);
        parent::uncache($comment);
        $this->cache->deleteValue("torrent_comments_{$comment->GroupID}");
    }
}
