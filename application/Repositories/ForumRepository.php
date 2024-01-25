<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

class ForumRepository extends Repository {

    protected $entityName = 'Forum';

    /**
     * Delete Forum entity from cache
     * @param int|Entity $forum forum to uncache
     *
     */
    public function uncache($forum) {
        $forum = $this->load($forum);
        parent::uncache($forum);
        $cacheKeys = ["forum_last_thread", "forum_threads_count", "forum_posts_count", "latest_threads_forum"];
        foreach ($cacheKeys as $cacheKey) {
            $this->cache->deleteValue("{$cacheKey}_{$forum->ID}");
        }
    }

    public function checkForumPerm($forumID, $perm = 'Read') {
        if (!is_integer_string($forumID)) {
            return false;
        }

        $forum = $this->master->repos->forums->load($forumID);

        # Check forum exists
        if (!$forum instanceof \Luminance\Entities\Forum) {
            return false;
        }

        switch ($perm) {
            case 'Read':
                return $forum->canRead($this->master->request->user);

            case 'Write':
                return $forum->canWrite($this->master->request->user);

            case 'Create':
                return $forum->canCreate($this->master->request->user);

            default:
                return false;
        }
    }

    public function printForumSelect($forums, $forumCats, $selectedForumID = false, $elementID = '', $attribsRaw = '') {
        echo $this->master->render->forumSelect($selectedForumID, $elementID, $attribsRaw);
    }

    public function getForumCats() {
        $forumCats = $this->master->repos->forumCategories->find(null, null, 'Sort', null, 'forums_categories');
        $result = [];
        foreach ($forumCats as $forumCat) {
            $result[$forumCat->ID] = $forumCat->Name;
        }

        return $result;
    }

    public function getForumInfo() {
        //This variable contains all our lovely forum data
        $forums = $this->master->cache->getValue('forums_list');
        if ($forums === false) {
            $forums = $this->master->db->rawQuery(
                "SELECT f.ID,
                      f.ID,
                      f.CategoryID,
                      f.Name,
                      f.Description,
                      f.MinClassRead,
                      f.MinClassWrite,
                      f.MinClassCreate,
                      COUNT(sr.ThreadID) AS SpecificRules
                 FROM forums AS f
                 JOIN forums_categories AS fc ON fc.ID = f.CategoryID
            LEFT JOIN forums_rules AS sr ON sr.ForumID = f.ID
             GROUP BY f.ID
             ORDER BY fc.Sort, fc.Name, f.CategoryID, f.Sort"
            )->fetchAll(\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
            foreach ($forums as $forumID => $forum) {
                if ($forum['SpecificRules']) {
                      $threadIDs = $this->master->db->rawQuery(
                          "SELECT ThreadID FROM forums_rules WHERE ForumID = ?",
                          [$forumID]
                      )->fetchAll(\PDO::FETCH_COLUMN);
                      $forums[$forumID]['SpecificRules'] = $threadIDs;
                }
            }
            unset($forumID, $forum);
            $this->master->cache->cacheValue('forums_list', $forums, 0); //Inf cache.
        }
        return $forums;
    }
}
