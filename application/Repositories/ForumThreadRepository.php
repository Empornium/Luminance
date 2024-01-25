<?php
namespace Luminance\Repositories;

use Luminance\Core\Entity;
use Luminance\Core\Repository;

use Luminance\Entities\ForumPoll;
use Luminance\Entities\ForumThread;

use Luminance\Errors\SystemError;

class ForumThreadRepository extends Repository {

    protected $entityName = 'ForumThread';

    /**
     * Delete Thread entity from cache
     * @param int|Entity $thread thread to uncache
     *
     */
    public function uncache($thread) {
        $thread = $this->load($thread);
        parent::uncache($thread);

        $this->cache->deleteValue("thread_last_post_{$thread->ID}");
        $this->cache->deleteValue("forum_posts_count_{$thread->ID}");
        $this->cache->deleteValue("forum_posts_flow_count_{$thread->ID}");
        if ($this->cache->getValue("forum_last_thread_{$thread->forum->ID}") === $thread->ID) {
            $this->cache->deleteValue("forum_last_thread_{$thread->forum->ID}");
        }
        $latestForumThreads = $this->cache->getValue("latest_threads_forum_{$thread->forum->ID}");
        if (is_array($latestForumThreads)) {
            $latestForumThreadIDs = array_column($latestForumThreads, 'ThreadID');
            if (in_array($thread->ID, $latestForumThreadIDs)) {
                $this->cache->deleteValue("latest_threads_forum_{$thread->forum->ID}");
            }
        }
    }

    public function appendNote(Entity $entity, string $message, string $comment = null) {
        $message = str_replace("\r", '', $message);
        $message = str_replace("\n", "[br]", $message);
        $sqltime = sqltime();
        $notes = "{$sqltime} - {$message} by {$this->master->request->user->Username}\n";
        if (!empty($comment)) {
            $notes .= "Reason: {$comment}\n";
        }
        $notes .= $entity->Notes;
        $entity->Notes = $notes;
        parent::save($entity);
    }

    public function delete(Entity $entity) {
        $relatedEntityTables = ["forums_polls", "forums_polls_votes", "forums_posts"];

        foreach ($relatedEntityTables as $table) {
            $this->db->rawQuery(
                "DELETE FROM {$table}
                       WHERE ThreadID = ?",
                [$entity->ID]
            );
        }
        $this->uncache($entity);
        parent::delete($entity);
    }

    public function merge(Entity $entity, Entity $mergeThread, string $title = "") {
        $mergeForumID = $mergeThread->forum->ID;
        $sourceForumID = $entity->forum->ID;
        $mergeThreadTitle = $mergeThread->Title;

        if ($title === "") {
            $title = $mergeThreadTitle;
        }

        $threadPoll = $entity->poll;
        // if mergeThread has a poll, delete poll from thread
        if ($mergeThread->poll instanceof ForumPoll) {
            if ($threadPoll instanceof ForumPoll) {
                $this->master->repos->forumPolls->delete($entity->poll);
            }
        }

        // update any existing polls
        if ($threadPoll instanceof ForumPoll) {
            $threadPoll->ThreadID = $mergeThread->ID;
            $this->master->repos->forumPolls->save($threadPoll);

            $this->db->rawQuery(
                "UPDATE forums_polls_votes
                    SET ThreadID = ?
                  WHERE ThreadID = ?",
                [$mergeThread->ID, $entity->ID]
            );
        }

        $posts = $this->db->rawQuery(
            "SELECT ID
               FROM forums_posts
              WHERE ThreadID = ?",
            [$entity->ID]
        )->fetchAll(\PDO::FETCH_COLUMN);

        $this->db->rawQuery(
            "UPDATE forums_posts
                SET ThreadID = ?,
                    Body = CONCAT_WS(?, Body, ?)
              WHERE ThreadID = ?",
            [
                $mergeThread->ID,
                '[br][br]',
                "[align=right][size=0][i]merged from thread[/i][br]'{$entity->Title}'[/size][/align]",
                $entity->ID,
            ]
        );

        foreach ($posts as $post) {
            $this->master->repos->forumposts->uncache($post);
        }

        if (!($mergeThread->Title === $title)) {
            $mergeThread->Title = $title;
            $this->save($mergeThread);
        }

        // Fugly but functional
        $this->db->rawQuery(
            "UPDATE forums_last_read_threads AS flrt JOIN (
                SELECT MAX(PostID) AS PostID,
                       UserID,
                       ThreadID
                  FROM forums_last_read_threads
                 WHERE ThreadID IN (?, ?)
              GROUP BY UserID) AS updates ON flrt.UserID = updates.UserID AND flrt.ThreadID = updates.ThreadID
                SET flrt.PostID = updates.PostID
              WHERE flrt.ThreadID = ?",
            [$mergeThread->ID, $entity->ID, $entity->ID]
        );

        $this->db->rawQuery(
            "DELETE FROM forums_last_read_threads
                   WHERE ThreadID = ?",
            [$entity->ID]
        );

        $this->db->rawQuery(
            "UPDATE IGNORE forums_subscriptions
                SET ThreadID = ?
              WHERE ThreadID = ?",
            [$mergeThread->ID, $entity->ID]
        );

        $this->db->rawQuery(
            "DELETE FROM forums_subscriptions
              WHERE ThreadID = ?",
            [$entity->ID]
        );
        parent::delete($entity);

        $userIDs = $this->db->rawQuery(
            "SELECT UserID
               FROM forums_last_read_threads
              WHERE ThreadID = ?",
            [$mergeThread->ID]
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($userIDs as $userID) {
            $this->cache->deleteValue("_entity_ForumLastRead_{$mergeThread->ID}_{$userID}");
        }
        $this->cache->deleteValue("latest_threads_forum_{$mergeForumID}");
        $this->cache->deleteValue("latest_threads_forum_{$sourceForumID}");

        $this->master->repos->forums->uncache($mergeForumID);
        $this->master->repos->forumthreads->uncache($mergeThread->ID);
    }

    public function split(ForumThread $thread, array $posts, string $option, $forum, $targetThread = null, string $title = "", $comment = "") {
        $sqltime = sqltime();
        $siteName = $this->master->settings->main->site_name;
        $user = $this->master->request->user;
        $numSplitPosts = count($posts);

        switch ($option) {
            case 'delete':
                $note = "{$numSplitPosts} posts were deleted from this thread";
                break;
            case 'merge':
                $extra = "merged into";
                $note = "$numSplitPosts posts $extra thread /forum/thread/{$targetThread->ID}";
                $targetBody = "[quote={$siteName}][/quote]";

                $this->appendNote(
                    $targetThread,
                    "$numSplitPosts posts $extra this thread from /forum/thread/{$thread->ID}"
                );
                break;
            case 'new':
                $extra = "moved to";
                if ($title === '') {
                    $title = "Split thread - from \"{$thread->Title}\"";
                }

                $targetThread = new ForumThread([
                    'Title'     => $title,
                    'AuthorID'  => $posts[0]->AuthorID,
                    'ForumID'   => $forum->ID,
                ]);
                $this->save($targetThread);
                $this->appendNote(
                    $targetThread,
                    "$numSplitPosts posts $extra this thread from /forum/thread/{$thread->ID}"
                );

                $note = "$numSplitPosts posts $extra thread /forum/thread/{$targetThread->ID}";
                break;
            default:
                throw new SystemError('Unknown action in ForumThreadRepository::split()');
        }
        $this->appendNote($thread, $note);

        $postIDs = [];
        if ($option === "delete") {
            foreach ($posts as $post) {
                $this->master->repos->forumPosts->delete($post);
            }
        } else {
            foreach ($posts as $post) {
                $post->Body.="[br][br][align=right][size=0][i]split from thread[/i][br]'{$thread->Title}'[/size][/align]";
                $post->ThreadID = $targetThread->ID;
                $this->master->repos->forumPosts->save($post);
            }
        }
        $this->master->repos->forumthreads->uncache($targetThread->ID);

        $redirectPost = null;
        switch ($option) {
            case 'delete':
                $redirectPost = $thread->lastPost;
                break;
            case 'merge':
            case 'new':
                $redirectPost = end($posts);
                break;
        }

        return $redirectPost;
    }
}
