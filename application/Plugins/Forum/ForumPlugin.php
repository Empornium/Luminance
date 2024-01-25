<?php
namespace Luminance\Plugins\Forum;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Errors\Error;
use Luminance\Errors\UserError;
use Luminance\Errors\NotFoundError;
use Luminance\Errors\ForbiddenError;

use Luminance\Entities\User;
use Luminance\Entities\CommentEdit;
use Luminance\Entities\Restriction;

use Luminance\Entities\Forum;
use Luminance\Entities\ForumPoll;
use Luminance\Entities\ForumPost;
use Luminance\Entities\ForumRule;
use Luminance\Entities\ForumThread;
use Luminance\Entities\ForumLastRead;
use Luminance\Entities\ForumCategory;
use Luminance\Entities\ForumPollVote;
use Luminance\Entities\ForumSubscription;

use Luminance\Services\Auth;

use Luminance\Responses\JSON;
use Luminance\Responses\Redirect;
use Luminance\Responses\Rendered;
use Luminance\Responses\Response;

use Luminance\Legacy\Text;
use Luminance\Legacy\Validate;

class ForumPlugin extends Plugin {

    protected static $defaultOptions = [
        'LatestForumThreadsNum' => ['value' => 6,    'section' => 'forum',  'displayRow' => 1, 'displayCol' => 1, 'type' => 'int',  'description' => 'Number of latest forum posts to show'],
        'EditTimelockEnable'    => ['value' => true, 'section' => 'forum',  'displayRow' => 2, 'displayCol' => 1, 'type' => 'bool', 'description' => 'Enable edit timelock in forums'],
        'EditTimelockMins'      => ['value' => 15,   'section' => 'forum',  'displayRow' => 2, 'displayCol' => 2, 'type' => 'int',  'description' => 'Number of mins before post is locked'],
        'ImagesCheck'           => ['value' => true, 'section' => 'forum',  'displayRow' => 3, 'displayCol' => 1, 'type' => 'bool', 'description' => 'Enable images checking in posts'],
        'MaxImagesCount'        => ['value' => 10,   'section' => 'forum',  'displayRow' => 3, 'displayCol' => 2, 'type' => 'int',  'description' => 'Max. number of images in posts'],
        'MaxImagesWeight'       => ['value' => 10,   'section' => 'forum',  'displayRow' => 3, 'displayCol' => 3, 'type' => 'int',  'description' => 'Max. size for images in posts (MB)'],
        'ImagesCheckMinClass'   => ['value' => 0,    'section' => 'forum',  'displayRow' => 3, 'displayCol' => 4, 'type' => 'int',  'description' => 'Disable for users above this rank'],
        'ExtTrackerForums'      => ['value' => 51,   'section' => 'forum',  'displayRow' => 5, 'displayCol' => 1, 'type' => 'str',  'description' => 'External Tracker Invite Forums'],
        'SiteBugForum'          => ['value' => 18,   'section' => 'forum',  'displayRow' => 5, 'displayCol' => 2, 'type' => 'str',  'description' => 'Site Bug Forum'],
    ];

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  '*',                     Auth::AUTH_LOGIN,  'forum'          ],
        [ 'GET',  '*/new',                 Auth::AUTH_LOGIN,  'newThreadForm'  ],
        [ 'POST', '*/new',                 Auth::AUTH_LOGIN,  'newThread'      ],
        [ 'GET',  '*/unread',              Auth::AUTH_LOGIN,  'unreadPosts'    ],
        [ 'POST', 'goto',                  Auth::AUTH_LOGIN,  'goto'           ],
        [ 'GET',  'unread',                Auth::AUTH_LOGIN,  'unreadPosts'    ],
        [ 'GET',  'thread/*',              Auth::AUTH_LOGIN,  'thread'         ],
        [ 'POST', 'thread/*/delete',       Auth::AUTH_2FA,    'delete'         ],
        [ 'POST', 'thread/*/edit',         Auth::AUTH_2FA,    'edit'           ],
        [ 'POST', 'thread/*/merge',        Auth::AUTH_2FA,    'merge'          ],
        [ 'POST', 'thread/*/reply',        Auth::AUTH_LOGIN,  'reply'          ],
        [ 'POST', 'thread/*/poll/vote',    Auth::AUTH_LOGIN,  'pollVote'       ],
        [ 'POST', 'thread/*/poll/add',     Auth::AUTH_2FA,    'pollAdd'        ],
        [ 'POST', 'thread/*/poll/remove',  Auth::AUTH_2FA,    'pollRemove'     ],
        [ 'POST', 'thread/*/poll/feature', Auth::AUTH_2FA,    'pollFeature'    ],
        [ 'POST', 'thread/*/poll/open',    Auth::AUTH_2FA,    'pollOpen'       ],
        [ 'POST', 'thread/*/poll/close',   Auth::AUTH_2FA,    'pollClose'      ],
        [ 'POST', 'thread/*/poll/delete',  Auth::AUTH_2FA,    'pollDelete'     ],
        [ 'POST', 'threads/delete',        Auth::AUTH_2FA,    'delete'         ],
        [ 'POST', 'threads/move',          Auth::AUTH_2FA,    'move'           ],
        [ 'GET',  'post/*/remove',         Auth::AUTH_IPLOCK, 'postRemoveForm' ],
        [ 'POST', 'post/*/trash',          Auth::AUTH_IPLOCK, 'postTrash'      ],
        [ 'POST', 'post/*/delete',         Auth::AUTH_2FA,    'postDelete'     ],
        [ 'GET',  'post/*/get',            Auth::AUTH_LOGIN,  'postGet'        ],
        [ 'GET',  'post/*/edit',           Auth::AUTH_LOGIN,  'postEditForm'   ],
        [ 'POST', 'post/*/edit',           Auth::AUTH_LOGIN,  'postEdit'       ],
        [ 'POST', 'post/*/revert',         Auth::AUTH_IPLOCK, 'postRevert'     ],
        [ 'POST', 'post/*/editlock',       Auth::AUTH_IPLOCK, 'editLock'       ],
        [ 'POST', 'post/*/timelock',       Auth::AUTH_IPLOCK, 'timeLock'       ],
        [ 'POST', 'post/*/pinpost',        Auth::AUTH_IPLOCK, 'postPin'        ],
        [ 'POST', 'posts/split/*',         Auth::AUTH_IPLOCK, 'postsSplit'     ],
        [ 'GET',  'search',                Auth::AUTH_LOGIN,  'searchForm'     ],
        [ 'GET',  'catchup',               Auth::AUTH_LOGIN,  'catchup'        ],
        [ 'GET',  'catchup/*',             Auth::AUTH_LOGIN,  'catchup'        ],
        [ 'GET',  'manage',                Auth::AUTH_2FA,    'manage'         ],
        [ 'POST', 'create',                Auth::AUTH_2FA,    'forumCreate'    ],
        [ 'POST', 'edit',                  Auth::AUTH_2FA,    'forumEdit'      ],
        [ 'POST', 'delete',                Auth::AUTH_2FA,    'forumDelete'    ],
        [ 'POST', 'category/create',       Auth::AUTH_2FA,    'categoryCreate' ],
        [ 'POST', 'category/edit',         Auth::AUTH_2FA,    'categoryEdit'   ],
        [ 'POST', 'category/delete',       Auth::AUTH_2FA,    'categoryDelete' ],
        [ 'GET',  'recent',                Auth::AUTH_IPLOCK, 'recent'         ],
        [ 'GET',  '*/rules',               Auth::AUTH_2FA,    'rules'          ],
        [ 'POST', '*/rules/add',           Auth::AUTH_2FA,    'ruleAdd'        ],
        [ 'POST', '*/rules/delete',        Auth::AUTH_2FA,    'ruleDelete'     ],
    ];

    protected static $useServices = [
        'auth'          => 'Auth',
        'db'            => 'DB',
        'cache'         => 'Cache',
        'settings'      => 'Settings',
        'flasher'       => 'Flasher',
        'secretary'     => 'Secretary',
        'render'        => 'Render',
        'repos'         => 'Repos',
        'irker'         => 'Irker',
        'options'       => 'Options',
    ];

    protected static $userinfoTools = [
        [
            'forum_admin',            # permission
            'forum/manage',           # action
            'Forum'                   # title
        ],
        [
            'users_fls',              # permission
            'forum/recent',           # action
            'Recent Forum Posts'      # title
        ],
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ '*', 'forum/**', Auth::AUTH_LOGIN, 'plugin', 'Forum' ]);
    }

    public function forum($forumID = null) {
        $user = $this->request->user;
        $postsPerPage = $user->options('PostsPerPage', $this->settings->pagination->posts);

        if (is_integer_string($forumID)) {
            $forum = $this->repos->forums->load($forumID);
            if (!$forum instanceof Forum) {
                throw new NotFoundError('This forum does not exist');
            }
            if (!$forum->canRead($this->request->user)) {
                throw new ForbiddenError();
            }

            $pageSize = $this->settings->pagination->threads;
            list($page, $limit) = page_limit($pageSize);

            $threads = $this->db->rawQuery(
                "SELECT fp.ThreadID, MAX(fp.AddedTime) AS LastPostID
                   FROM forums_posts AS fp
                   JOIN forums_threads AS ft ON ft.ID=fp.ThreadID
                  WHERE ft.ForumID = ?
               GROUP BY fp.ThreadID
               ORDER BY ft.IsSticky DESC, LastPostID DESC
                  LIMIT {$limit}",
                [$forumID]
            )->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($threads as &$thread) {
                $thread = $this->repos->forumThreads->load($thread);
            }

            $params = [
                'forum'         => $forum,
                'page'          => $page,
                'pageSize'      => $pageSize,
                'postsPerPage'  => $postsPerPage, # Used in inline page links
                'rules'         => $this->repos->forumRules->find('ForumID = ?', [$forumID], null, null, "forum_{$forumID}_rules"),
                'threads'       => $threads,
            ];

            return new Rendered('@Forum/forum.html.twig', $params);
        }

        # No or invalid forumID specified so just show the forum index
        $params = [
            'postsPerPage'  => $postsPerPage,
            'categories'    => $this->repos->forumCategories->find(null, null, 'Sort', null, 'forums_categories'),
        ];

        foreach ($params['categories'] as $categoryIndex => $category) {
            # Filter out the forums which the user lacks authorization to view.
            $forums = $category->allForums;
            foreach ((array)$forums as $forumIndex => $forum) {
                if (!$forum->canRead($user)) {
                    unset($forums[$forumIndex]);
                }
            }

            # Also filter out empty categories
            if (empty($forums)) {
                unset($params['categories'][$categoryIndex]);
            } else {
                $category->forums = $forums;
            }
        }
        return new Rendered('@Forum/forums.html.twig', $params);
    }

    public function goto() {
        $forumID = $this->request->getPostInt('forumid');
        return new Redirect("/forum/{$forumID}");
    }

    public function thread($threadID) {
        $user = $this->request->user;
        $thread = $this->repos->forumThreads->load($threadID);
        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$thread->forum instanceof Forum) {
            throw new NotFoundError('', 'This thread has been orphaned');
        }

        if (!$thread->forum->canRead($this->request->user)) {
            throw new ForbiddenError();
        }
        $thread->NumViews++;
        $this->repos->forumThreads->save($thread);
        $forum = $this->repos->forums->load($thread->ForumID);
        $pageSize = $user->options('PostsPerPage', $this->settings->pagination->posts);

        $page = 0;
        $postID = $this->request->getInt('postid');

        # When filtering posts for users we want to separate out posts into these groups:
        # Posts which are neither pinned nor trashed
        # Posts which are pinned but not trashed
        # This means that we must always mask for the ForumPost::TRASHED Flag when it's a user and
        # compare to either 0 or ForumPost::PINNED
        #
        # For staff with the right permission we simply do not mask for the ForumPost::TRASHED flag.
        if ($this->auth->isAllowed('forum_post_trash')) {
            $checkFlags = ForumPost::PINNED;
        } else {
            $checkFlags = ForumPost::PINNED | ForumPost::TRASHED;
        }

        if (!($postID === 0)) {
            $post = $this->repos->forumPosts->load($postID);
            if ($post instanceof ForumPost) {
                if ($post->ThreadID === $thread->ID) {
                    # We need to find position by date rather than ID as the posts are
                    # ordered by date to ensure automatic system posts appear first.
                    # It's a work-around for a bad hack.
                    $postPosition = $this->db->rawQuery(
                        "SELECT COUNT(ID) FROM forums_posts WHERE ThreadID = ? AND Flags & ? = 0 AND AddedTime <= ?",
                        [$thread->ID, $checkFlags, $post->AddedTime->format('Y-m-d H:i:s')]
                    )->fetchColumn();
                    list($page, $limit) = page_limit($pageSize, min($thread->numPostsInFlow, $postPosition));
                }
            }
        }

        if ($page === 0) {
            list($page, $limit) = page_limit($pageSize);
        } else {
            $maxPage = ceil($thread->numPostsInFlow / $pageSize);
            $maxPage = max(1, $maxPage);
            if ($page > $maxPage) {
                $limit = $pageSize * $maxPage - $pageSize . ', ' . $pageSize;
            }
        }

        # We could implement catalog caching for the thread IDs, but it's not worth it
        $posts = $this->repos->forumPosts->find(
            'ThreadID = ? AND Flags & ? = 0',
            [$thread->ID, $checkFlags],
            'AddedTime',
            $limit
        );

        $pinned = $this->repos->forumPosts->find(
            'ThreadID = ? AND Flags & ? = ?',
            [$thread->ID, $checkFlags, ForumPost::PINNED],
            'AddedTime'
        );

        # Pinned posts are placed at the top of the thread
        $posts = array_merge($pinned, $posts);

        # get last PostID on page
        $lastPinned = end($pinned);
        $lastPost = end($posts);

        if ($lastPinned instanceof ForumPost) {
            if ($lastPinned->ID > $lastPost->ID) {
                $lastPost = $lastPinned;
            }
        }

        $bscripts = ['comments', 'subscriptions', 'bbcode', 'jquery', 'jquery.cookie', 'jquery.modal', 'hidebar', 'overlib'];
        $params = [
            'page'      => $page,
            'pageSize'  => $pageSize,
            'forum'     => $forum,
            'thread'    => $thread,
            'posts'     => $posts,
            'bscripts'  => $bscripts,
        ];

        return new Rendered('@Forum/thread.html.twig', $params, 200, null, [$this, 'updateLastRead'], [$thread, $lastPost]);
    }

    public function edit($threadID) {
        $this->auth->checkAllowed('forum_moderate');

        $thread = $this->repos->forumThreads->load($threadID);
        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        $comments = $this->request->getPostArray('note');
        $comment = implode("\n", array_filter($comments));

        $sendTo = $this->request->getPostString('send_thread');
        if ($sendTo === 'trash') {
            $forumID = $this->settings->forums->trash_forum_id;
        } else {
            $forumID = $this->request->getPostInt('forumid');
        }
        $forum = $this->repos->forums->load($forumID);
        if (!$forum instanceof Forum) {
            $forum = $thread->forum;
        }

        if (!($thread->ForumID === $forumID)) {
            if (!$forum->canWrite($this->request->user)) {
                throw new ForbiddenError('', 'You do not have permission to move to this forum');
            }

            $this->repos->forums->uncache($forumID);
            $this->repos->forums->uncache($thread->ForumID);

            $note = "moved from [b][url=/forum/{$thread->ForumID}]{$thread->forum->Name}[/url][/b] to [b][url=/forum/$forumID]{$forum->Name}[/url][/b]";

            $thread->ForumID = $forumID;

            $this->repos->forumThreads->appendNote($thread, $note, $comment);
            $comment = null;
        }

        $sticky = $this->request->getPostString('sticky') === "on" ? 1 : 0;
        $locked = $this->request->getPostString('locked') === "on" ? 1 : 0;
        $page = $this->request->getPostInt('page');

        if (!(intval($thread->IsSticky) === $sticky)) {
            if ($sticky === 1) {
                $this->repos->forumThreads->appendNote($thread, "Stickied");
            } else {
                $this->repos->forumThreads->appendNote($thread, "Unstickied");
            }
            $thread->IsSticky = $sticky;
        }

        if (!(intval($thread->IsLocked) === $locked)) {
            if ($locked === 1) {
                $this->repos->forumThreads->appendNote($thread, "Locked");
            } else {
                $this->repos->forumThreads->appendNote($thread, "Unlocked");
            }
            $thread->IsLocked = $locked;
        }

        $title = $this->request->getPostString('title');
        if (!(empty($title)) && !($thread->Title === $title)) {
            $this->repos->forumThreads->appendNote($thread, "Title changed from {$thread->Title} to $title");
            $thread->Title = $title;
        }

        if (!empty($comment)) {
            $this->repos->forumThreads->appendNote($thread, $comment);
        }

        $this->repos->forumThreads->save($thread);

        if ($locked && $thread->poll instanceof ForumPoll) {
            $thread->poll->Closed = '0';
            $this->repos->forumPolls->save($thread->poll);
        }

        return new Redirect("/forum/thread/{$threadID}?page={$page}");
    }

    public function merge($threadID) {
        $this->auth->checkAllowed('forum_thread_merge');

        $thread = $this->repos->forumThreads->load($threadID);
        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }
        $mergeThreadID = $this->request->getPostInt("mergethreadid");
        if ($threadID === $mergeThreadID) {
            throw new UserError('', 'You cannot merge a thread with itself');
        }

        $mergeThread = $this->repos->forumThreads->load($mergeThreadID);
        if (!$mergeThread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$mergeThread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to post in this forum');
        }

        $title = $this->request->getPostString('title');
        $this->repos->forumThreads->merge($thread, $mergeThread, $title);

        return new Redirect("/forum/thread/{$mergeThreadID}");
    }

    public function reply($threadID) {
        $user = $this->request->user;

        $thread = $this->repos->forumThreads->load($threadID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'thread.reply');

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$thread->forum instanceof Forum) {
            throw new NotFoundError('', 'This thread has been orphaned');
        }

        if (!$thread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to post in this forum');
        }

        $body = $this->request->getPostString('body');
        $forum = $thread->forum;

        # If you're not sending anything, go back
        if (empty($body)) {
            throw new UserError('', 'You cannot post a reply with no content');
        }

        # Work-around for references to master inside the legacy Text class
        $master = $this->master;
        $bbCode = new Text;
        $bbCode->validate_bbcode($body, get_permissions_advtags($user->ID));

        if ($thread->IsLocked === '1' && !$this->auth->isAllowed('forum_moderate')) {
            throw new ForbiddenError('', 'This forum thread is locked');
        }

        if ($this->request->getPostBool('subscribe') === true) {
            $subscription = $this->repos->forumSubscriptions->load([$user->ID, $thread->ID]);

            if (!($subscription instanceof ForumSubscription)) {
                $subscription = new ForumSubscription([
                    'UserID'    => $user->ID,
                    'ThreadID'  => $thread->ID,
                ]);
                $this->repos->forumSubscriptions->save($subscription, true);
            }
        }

        # Load the last post to check for merging
        $post = $thread->lastPost;

        $timestamp = new \DateTime;

        if ($post instanceof ForumPost) {
            $canEdit = $post->canEdit($user->ID);
        } else {
            $canEdit = false;
        }

        $merge = $this->request->getPostBool('merge');
        $canDoublePost = $this->auth->isAllowed('forum_thread_double_post');

        # Merge if user was last to post, is not allowed to double post or chose to merge,
        # and is within the post editing time window.
        $postMerge =     ($post->AuthorID === $user->ID)
                      && ($canDoublePost === false || $merge === true)
                      && ($canEdit === true);

        # Handle the special case of merging posts
        if ($postMerge === true) {
            # post wasn't edited by someone else and is within the edit time
            $edit = new CommentEdit([
                'Page'      => 'forums',
                'PostID'    => $post->ID,
                'EditUser'  => $user->ID,
                'EditTime'  => $timestamp,
                'Body'      => $post->Body,
            ]);
            $this->repos->commentEdits->save($edit);

            $post->Body = $post->Body."\n\n{$body}";
            $post->EditedUserID = $user->ID;
            $post->EditedTime = $timestamp;
            $this->repos->forumPosts->save($post);

            # Remove once all edits are done via Luminance
            $this->cache->deleteValue("forums_edits_{$post->ID}");

        # We're dealing with a new post
        } else {
            flood_check();

            $post = new ForumPost([
                'ThreadID'  => $thread->ID,
                'AuthorID'  => $user->ID,
                'AddedTime' => $timestamp,
                'Body'      => $body,
            ]);
            $this->repos->forumPosts->save($post);
            $this->repos->forums->uncache($forum->ID);
            $this->repos->forumThreads->uncache($thread->ID);
        }

        $subscribers = $this->repos->forumSubscriptions->find('ThreadID = ?', [$thread->ID]);
        foreach ($subscribers as $subscriber) {
            if ($subscriber instanceof ForumSubscription) {
                $this->cache->deleteValue("subscriptions_user_new_{$subscriber->UserID}");
            }
        }

        return new Redirect("/forum/thread/{$thread->ID}?postid={$post->ID}#post{$post->ID}");
    }

    public function pollVote($threadID) {
        $user = $this->request->user;

        $thread = $this->repos->forumThreads->load($threadID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'thread.poll.vote');

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$thread->forum instanceof Forum) {
            throw new NotFoundError('', 'This thread has been orphaned');
        }

        if (!$thread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to post in this forum');
        }

        $vote = $this->repos->forumPollVotes->get('ThreadID = ? AND UserID = ?', [$thread->ID, $user->ID], "poll_vote_{$thread->ID}_{$user->ID}");
        if ($vote instanceof ForumPollVote) {
            if (!$this->auth->isAllowed('forum_moderate')) {
                throw new UserError('', 'You have already voted');
            }
        } else {
            $vote = new ForumPollVote([
                'ThreadID'  => $thread->ID,
                'UserID'    => $user->ID,
            ]);
            $this->cache->deleteValue("poll_vote_{$thread->ID}_{$user->ID}");
        }

        $voteID = $this->request->getPostString('vote');
        if (!is_integer_string($voteID)) {
            throw new UserError('', 'Please select an option');
        }

        $vote->Vote = $voteID;
        $this->repos->forumPollVotes->save($vote);

        $this->cache->deleteValue("forum_poll_votes_{$thread->ID}");

        return new Redirect("/forum/thread/{$thread->ID}");
    }

    public function pollAdd($threadID) {
        $user = $this->request->user;

        $this->auth->checkAllowed('forum_polls_moderate');
        $thread = $this->repos->forumThreads->load($threadID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'thread.poll.add');

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$thread->forum instanceof Forum) {
            throw new NotFoundError('', 'This thread has been orphaned');
        }

        if (!$thread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to post in this forum');
        }

        if (!($thread->poll instanceof ForumPoll)) {
            throw new NotFoundError('', 'This thread does not have a poll');
        }

        $newOption = $this->request->getPostString('new_option');
        $answers = unserialize($thread->poll->Answers);
        $answers[] = $newOption;
        $thread->poll->Answers = serialize($answers);
        $this->repos->forumPolls->save($thread->poll);

        return new Redirect("/forum/thread/{$threadID}");
    }

    public function pollRemove($threadID) {
        $user = $this->request->user;

        $this->auth->checkAllowed('forum_polls_moderate');
        $thread = $this->repos->forumThreads->load($threadID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'thread.poll.remove');

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$thread->forum instanceof Forum) {
            throw new NotFoundError('', 'This thread has been orphaned');
        }

        if (!$thread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to post in this forum');
        }

        if (!($thread->poll instanceof ForumPoll)) {
            throw new NotFoundError('', 'This thread does not have a poll');
        }

        $option = $this->request->getPostInt('vote');
        $answers = unserialize($thread->poll->Answers);
        unset($answers[$option]);
        $thread->poll->Answers = serialize($answers);
        $this->repos->forumPolls->save($thread->poll);

        $this->db->rawQuery(
            "DELETE FROM forums_polls_votes
                   WHERE Vote = ?
                     AND ThreadID = ?",
            [$option, $threadID]
        );

        return new Redirect("/forum/thread/{$threadID}");
    }

    public function pollFeature($threadID) {
        $user = $this->request->user;

        $this->auth->checkAllowed('forum_polls_moderate');
        $thread = $this->repos->forumThreads->load($threadID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'thread.poll.feature');

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$thread->forum instanceof Forum) {
            throw new NotFoundError('', 'This thread has been orphaned');
        }

        if (!$thread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to post in this forum');
        }

        if (!($thread->poll instanceof ForumPoll)) {
            throw new NotFoundError('', 'This thread does not have a poll');
        }

        $featuredPollID = $this->cache->getValue('featured_poll');
        if ($featuredPollID === false) {
            $featuredPollID = $this->db->rawQuery(
                "SELECT ThreadID
                   FROM forums_polls
                  WHERE Featured IS NOT NULL
               ORDER BY Featured DESC
                  LIMIT 1"
            )->fetchColumn();
            $this->cache->cacheValue('featured_poll', $featuredPollID, 0);
        }

        if (!($featuredPollID === $threadID)) {
            $this->cache->cacheValue('polls_featured', $threadID, 0);
            $thread->poll->Featured = new \DateTime;
            $this->repos->forumPolls->save($thread->poll);
        }
    }

    public function pollOpen($threadID) {
        $user = $this->request->user;

        $this->auth->checkAllowed('forum_polls_moderate');
        $thread = $this->repos->forumThreads->load($threadID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'thread.poll.open');

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$thread->forum instanceof Forum) {
            throw new NotFoundError('', 'This thread has been orphaned');
        }

        if (!$thread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to post in this forum');
        }

        if (!($thread->poll instanceof ForumPoll)) {
            throw new NotFoundError('', 'This thread does not have a poll');
        }

        $thread->poll->Closed = '0';
        $this->repos->forumPolls->save($thread->poll);
    }

    public function pollClose($threadID) {
        $user = $this->request->user;

        $this->auth->checkAllowed('forum_polls_moderate');
        $thread = $this->repos->forumThreads->load($threadID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'thread.poll.close');

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$thread->forum instanceof Forum) {
            throw new NotFoundError('', 'This thread has been orphaned');
        }

        if (!$thread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to post in this forum');
        }

        if (!($thread->poll instanceof ForumPoll)) {
            throw new NotFoundError('', 'This thread does not have a poll');
        }

        $thread->poll->Closed = '1';
        $this->repos->forumPolls->save($thread->poll);
    }

    public function pollDelete($threadID) {
        $user = $this->request->user;

        $this->auth->checkAllowed('forum_polls_moderate');
        $thread = $this->repos->forumThreads->load($threadID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'thread.poll.delete');

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This thread does not exist');
        }

        if (!$thread->forum instanceof Forum) {
            throw new NotFoundError('', 'This thread has been orphaned');
        }

        if (!$thread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to post in this forum');
        }

        if (!($thread->poll instanceof ForumPoll)) {
            throw new NotFoundError('', 'This thread does not have a poll');
        }

        $this->db->rawQuery(
            "DELETE FROM forums_polls_votes WHERE ThreadID = ?",
            [$thread->ID]
        );

        $this->repos->forumPolls->delete($thread->poll);
    }

    public function delete($threadID = null) {
        $this->auth->checkAllowed('forum_thread_delete');

        $threadIDs = $this->request->getPostArray('threadids', (array) $threadID);

        $forumID = $this->request->getPostInt('destination');

        $user = $this->request->user;

        $threads = [];
        foreach ($threadIDs as $threadID) {
            $thread = $this->repos->forumThreads->load($threadID);
            if (!$thread instanceof ForumThread) {
                throw new NotFoundError('', 'This thread does not exist');
            }

            if (!$thread->forum->canWrite($user)) {
                throw new ForbiddenError('You cannot delete this thread');
            }
            $threads[] = $thread;
            $forumID = $thread->forum->ID;
        }

        foreach ($threads as $thread) {
            $this->repos->forumThreads->delete($thread);
        }
        return new Redirect("/forum/{$forumID}");
    }

    public function move($threadID = null) {
        $this->auth->checkAllowed('forum_thread_move');

        $threadIDs = $this->request->getPostArray('threadids', (array) $threadID);

        $destination = $this->request->getPostInt('destination');
        $forumID = $this->request->getPostInt('forumid');
        $forum = $this->repos->forums->load($forumID);
        if (!$forum instanceof Forum) {
            throw new NotFoundError('This forum does not exist');
        }

        $user = $this->request->user;

        $threads = [];
        foreach ($threadIDs as $threadID) {
            $thread = $this->repos->forumThreads->load($threadID);
            if (!$thread instanceof ForumThread) {
                throw new NotFoundError('', 'This thread does not exist');
            }

            if (!$thread->forum->canWrite($user)) {
                throw new ForbiddenError('You cannot move this post');
            }
            if ($thread->ForumID === $forum->ID) {
                throw new UserError('This forum thread is already in the specified forum');
            }
            $threads[] = $thread;
        }

        $comment = $this->request->getPostString('comment');

        foreach ($threads as $thread) {
            $this->repos->forums->uncache($forum->ID);
            $this->repos->forums->uncache($thread->ForumID);

            $note = "moved from [b][url=/forum/{$thread->ForumID}]{$thread->forum->Name}[/url][/b] to [b][url=/forum/$forum->ID]{$forum->Name}[/url][/b]";

            $thread->ForumID = $forum->ID;

            $comment = $this->request->getPostString('comment');
            $this->repos->forumThreads->appendNote($thread, $note, $comment);
        }

        if (count($threads) > 0) {
            return new Redirect("/forum/{$forum->ID}");
        } else {
            return new Redirect("/forum/{$destination}");
        }
    }

    public function updateLastRead($thread, $post) {
        $thread = $this->repos->forumThreads->load($thread);
        if (!$thread instanceof ForumThread) {
            return;
        }

        $user = $this->request->user;

        # Clear subscriptions after page load
        $this->cache->deleteValue('subscriptions_user_new_'.$user->ID);

        # Make sure we do a fresh DB query
        $lastRead = $this->repos->forumLastReads->load([$thread->ID, $user->ID]);

        # Unlikely, but need to check anyway
        if ($post instanceof ForumPost && $user instanceof User) {
            # Create new lastRead object if required
            if (!($lastRead instanceof ForumLastRead)) {
                $lastRead = new ForumLastRead([
                    'UserID'    => $user->ID,
                    'ThreadID'  => $thread->ID,
                    'PostID'    => $post->ID,
                ]);
                # Allow update on duplicate key, just in case
                $this->repos->forumLastReads->save($lastRead, true);
            } else {
                if ($lastRead->PostID < $post->ID || $lastRead->PostID > $thread->lastPost->ID) {
                    $lastRead->PostID = $post->ID;
                    $this->repos->forumLastReads->save($lastRead);
                }
            }
        }
    }

    public function postRemoveForm($postID) {
        $params = ['post' => $this->repos->forumPosts->load($postID)];
        return new Rendered('@Forum/post_remove.html.twig', $params);
    }

    protected function ajaxPostValidate($postID, $permission = null, $action = "edit") {
        # Do user permission and post validation common to all AJAX post requests.
        try {
            if (!is_integer_string($postID)) {
                throw new NotFoundError('', 'This post does not exist');
            }

            if (!empty($permission)) {
                $this->auth->checkAllowed($permission);
            }

            if (!in_array($action, ["read", "trash"])) {
                $token = $this->request->getPostString('token');
                $this->secretary->checkToken($token, "post.{$action}");
            }

            $user = $this->request->user;
            $post = $this->repos->forumPosts->load($postID);

            if ($action === "edit") {
                $this->repos->restrictions->checkRestricted($user, Restriction::POST);
            }

            if (!($post instanceof ForumPost)) {
                throw new NotFoundError('', 'This post does not exist');
            }

            if (!($post->thread->forum instanceof Forum)) {
                throw new NotFoundError('', 'This post has been orphaned');
            }

            if ($action === "read") {
                if ($post->thread->forum->canRead($user) === false) {
                    throw new ForbiddenError('', "You do not have permission to {$action} post in this forum");
                }
            } else {
                # Make sure they aren't trying to edit posts they shouldn't
                if ($post->thread->forum->canWrite($user) === false) {
                    throw new ForbiddenError('', "You do not have permission to {$action} post in this forum");
                }
            }
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postTrash($postID) {
        $this->ajaxPostValidate($postID, 'forum_post_trash', 'trash');
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.

        try {
            $this->auth->checkAllowed('forum_post_trash');
            $user = $this->request->user;
            $post = $this->repos->forumPosts->load($postID);

            if ($post->thread->numPosts === 1) {
                throw new UserError("You cannot trash ALL the posts from a thread");
            }

            if ($post->thread->lastPost->ID === $post->ID) {
                # We're trashing the last post in the thread,
                # need to uncache the thread and forum objects
                if ($post->thread->ID === $post->thread->forum->lastThread->ID) {
                    $this->repos->forums->uncache($post->thread->forum->ID);
                }
                $this->repos->forumThreads->uncache($post->thread->ID);
            }

            # In rare occasions, a user may have been notified of a new post between its creation and deletion.
            # Because a direct deletion does not leave any notification in the thread
            # and in order to avoid a subscription counting bug we must delete the cache
            # key of all users subscribed to this thread.
            $userIDs = $this->db->rawQuery(
                'SELECT UserID
                   FROM forums_subscriptions
                  WHERE ThreadID = ?',
                [$post->thread->ID]
            )->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($userIDs as $userID) {
                $this->cache->deleteValue("subscriptions_user_new_{$userID}");
            }

            $token = $this->request->getPostString('token');
            $this->secretary->checkToken($token, 'post.trash');
            $status = $this->request->getPostBool('status');

            if ($status === true) {
                $post->setFlags(ForumPost::TRASHED);
                $action = 'trashed';
            } else {
                $post->unsetFlags(ForumPost::TRASHED);
                $action = 'restored';
            }
            $this->repos->forumPosts->save($post);

            $sslurl = $this->settings->main->ssl_site_url;
            $this->irker->announcelab('Forum post '.$postID.' has been '.$action.' in thread https://'.$sslurl.'/forum/thread/'.$post->ThreadID.'?postid='.$post->ID.'#post'.$post->ID.' by '.$this->request->user->Username);

            return new Response($this->render->post('forum', $post), 200);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postDelete($postID) {
        $this->ajaxPostValidate($postID, 'forum_post_delete', 'delete');
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.

        try {
            $user = $this->request->user;
            $post = $this->repos->forumPosts->load($postID);

            if ($post->thread->lastPost->ID === $post->ID) {
                # We're deleting the last post in the thread,
                # need to uncache the thread and forum objects
                if ($post->thread->ID === $post->thread->forum->lastThread->ID) {
                    $this->repos->forums->uncache($post->thread->forum->ID);
                }
                $this->repos->forumThreads->uncache($post->thread->ID);
            }

            # In rare occasions, a user may have been notified of a new post between its creation and deletion.
            # Because a direct deletion does not leave any notification in the thread
            # and in order to avoid a subscription counting bug we must delete the cache
            # key of all users subscribed to this thread.
            $userIDs = $this->db->rawQuery(
                'SELECT UserID
                   FROM forums_subscriptions
                  WHERE ThreadID = ?',
                [$post->thread->ID]
            )->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($userIDs as $userID) {
                $this->cache->deleteValue("subscriptions_user_new_{$userID}");
            }

            # Finally delete the post
            $this->repos->forumPosts->delete($post);

            $sslurl = $this->settings->main->ssl_site_url;
            $this->irker->announcelab('Forum post '.$postID.' has been deleted in thread https://'.$sslurl.'/forum/thread/'.$post->ThreadID.'?postid='.$post->ID.'#post'.$post->ID.' by '.$this->request->user->Username);

            return new JSON([$postID]);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postGet($postID) {
        $this->ajaxPostValidate($postID, null, 'read');
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.
        try {
            $user = $this->request->user;
            $post = $this->repos->forumPosts->load($postID);

            $bbCode = new Text;
            $body = $bbCode->clean_bbcode($post->Body, get_permissions_advtags($user->ID));

            if ($this->request->getGetString('body') === '1') {
                return new JSON(trim($body));
            } else {
                $bbCode->display_bbcode_assistant("editbox{$post->ID}", get_permissions_advtags($user->ID, $user->legacy['CustomPermissions']));
                $escapedBody = display_str($body);
                return new Response("<textarea id=\"editbox{$post->ID}\" class=\"long\" onkeyup=\"resize('editbox{$post->ID}');\" name=\"body\" rows=\"10\">{$escapedBody}</textarea>");
            }
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postEditForm($postID) {
        $this->ajaxPostValidate($postID, null, 'read');

        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.
        try {
            $user = $this->request->user;
            $post = $this->repos->forumPosts->load($postID);

            // carry over from legacy
            $this->auth->checkAllowed('forum_moderate');

            $depth = $this->request->getGetInt('depth');
            $edits = $this->repos->commentEdits->find(
                'PostID = ? and Page = ?',
                [$post->ID, 'forums'],
                'EditTime DESC',
                null,
                "forums_edits_{$post->ID}"
            );

            if (!($depth === 0)) {
                $body = $edits[$depth - 1]->Body;
            } else {
                // Not an edit, have to get from the original
                $body = $post->Body;
            }

            $params = [
                'body'     => $body,
                'depth'    => $depth,
                'edits'    => $edits,
                'postID'   => $post->ID,
                'section'  => 'forum',
            ];
            return new Rendered('snippets/post_edit.html.twig', $params);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postEdit($postID) {
        $this->ajaxPostValidate($postID, null, 'edit');
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.
        try {
            $user = $this->request->user;
            $post = $this->repos->forumPosts->load($postID);

            $body = $this->request->getPostString('body');

            if ($post->thread->IsLocked === '1' && !$this->auth->isAllowed('forum_moderate')) {
                throw new ForbiddenError('', 'This forum thread is locked');
            }

            validate_edit_comment($post->AuthorID, $post->EditedUserID, $post->AddedTime, $post->EditedTime, $post->Flags);

            # Work-around for references to master inside the legacy Text class
            $master = $this->master;
            $bbCode = new Text;
            $bbCode->validate_bbcode($body, get_permissions_advtags($user->ID));

            # Perform the update
            if (!($user->ID === $post->AuthorID)) {
                $post->Flags |= ForumPost::EDITLOCKED;
            }

            $timestamp = new \DateTime;

            $edit = new CommentEdit([
                'Page'      => 'forums',
                'PostID'    => $post->ID,
                'EditUser'  => $user->ID,
                'EditTime'  => $timestamp,
                'Body'      => $post->Body,
            ]);
            $this->repos->commentEdits->save($edit);

            $post->EditedUserID = $user->ID;
            $post->Body = $body;
            $post->EditedTime = $timestamp;
            $this->repos->forumPosts->save($post);

            if (!($user->ID === $post->AuthorID)) {
                $url = sprintf('/forum/thread/%d?postid=%d#post%d', $post->ThreadID, $post->ID, $post->ID);
                notify_staff_edit($post->AuthorID, $url);
            }

            # Remove once all edits are done via Luminance
            $this->cache->deleteValue("forums_edits_{$post->ID}");
            $result = 'saved';

            $html = $this->render->post('forum', $post);

            return new JSON([$result, $html]);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postRevert($postID) {
        $this->ajaxPostValidate($postID, 'forum_post_restore', 'revert');
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.
        try {
            $user = $this->request->user;
            $post = $this->repos->forumPosts->load($postID);

            $edits = $this->repos->commentEdits->find(
                'Page = ? AND PostID = ?',
                ['forums', $postID],
                'EditTime DESC'
                // null,
                // "forums_edits_{$postID}" Not yet!
            );

            if (count($edits) === 0) {
                // nothing to revert to
                return new NotFoundError;
            } else if (count($edits) === 1) {
                // removing the only edit so revert to original post
                $editUserID   = null;
                $editTime     = null;
            } else {
                // get info for (what will be) the new last edit
                $editUserID   = $edits[1]->EditUser;
                $editTime     = $edits[1]->EditTime;
            }

            $post->Body = $edits[0]->Body;
            $post->EditedUserID = $editUserID;
            $post->EditedTime = $editTime;
            $this->repos->forumPosts->save($post);

            $this->repos->commentEdits->delete($edits[0]);
            $this->cache->deleteValue("forums_edits_$postID");
            $this->repos->forums->uncache($post->thread->forum->ID);

            $html = $this->render->post('forum', $post);

            return new JSON($html);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function editLock($postID) {
        if (!is_integer_string($postID)) {
            throw new NotFoundError('', 'This post does not exist');
        }

        $this->auth->checkAllowed('forum_post_lock');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'post.editlock');
        $status = $this->request->getPostBool('status');

        $post = $this->repos->forumPosts->load($postID);
        if ($status === true) {
            $post->setFlags(ForumPost::EDITLOCKED);
        } else {
            $post->unsetFlags(ForumPost::EDITLOCKED);
        }
        $this->repos->forumPosts->save($post);

        return new Response($this->render->post('forum', $post), 200);
    }

    public function timeLock($postID) {
        if (!is_integer_string($postID)) {
            throw new NotFoundError('', 'This post does not exist');
        }

        $this->auth->checkAllowed('forum_post_lock');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'post.timelock');
        $status = $this->request->getPostBool('status');

        $post = $this->repos->forumPosts->load($postID);
        if ($status === true) {
            $post->setFlags(ForumPost::TIMELOCKED);
        } else {
            $post->unsetFlags(ForumPost::TIMELOCKED);
        }
        $this->repos->forumPosts->save($post);

        return new Response($this->render->post('forum', $post), 200);
    }

    public function postPin($postID) {
        if (!is_integer_string($postID)) {
            throw new NotFoundError('', 'This post does not exist');
        }

        $this->auth->checkAllowed('forum_post_pin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'post.pin');
        $status = $this->request->getPostBool('status');

        $post = $this->repos->forumPosts->load($postID);
        if ($status === true) {
            $post->setFlags(ForumPost::PINNED);
        } else {
            $post->unsetFlags(ForumPost::PINNED);
        }
        $this->repos->forumPosts->save($post);

        return new Response($this->render->post('forum', $post), 200);
    }

    public function postsSplit($option) {
        $this->auth->checkAllowed('forum_thread_split');
        $splitOptions = ["delete", "new", "merge", "trash"];

        if (!in_array($option, $splitOptions)) {
            throw new NotFoundError('', 'This split option does not exist');
        }

        $threadID = $this->request->getPostInt('threadid');
        $thread = $this->repos->forumThreads->load($threadID);
        if (!$thread->forum->canWrite($this->request->user)) {
            throw new ForbiddenError('', 'You do not have permission to split into this forum');
        }

        $postIDs = $this->request->getPostArray('splitids');
        $numSplitPosts = count($postIDs);
        if (!is_array($postIDs) || $numSplitPosts === 0) {
            throw new UserError("No posts selected to split");
        }
        if ($numSplitPosts >= $thread->numPosts) {
            throw new UserError("You cannot split ALL the posts from a thread");
        }
        sort($postIDs);

        $posts = [];
        foreach ($postIDs as $postID) {
            if (!is_integer_string($postID)) throw new UserError("Invalid post ID specified");
            $posts[] = $this->repos->forumPosts->load($postID);
        }

        $forumID = $this->request->getPostInt('forumid');
        $forum = $this->repos->forums->load($forumID);

        $title = $this->request->getPostString('title');
        $redirectPost = $thread->lastPost;

        if ($option === "merge") {
            $splitThreadID = $this->request->getPostInt('splitintothreadid');
            if ($splitThreadID === $threadID) {
                throw new UserError("Split failed: split into thread id cannot be the same as source thread!");
            }

            $splitThread = $this->repos->forumThreads->load($splitThreadID);
            if (!($splitThread instanceof ForumThread)) {
                throw new UserError("Split failed: Could not find thread with id={$splitThreadID}");
            }

            if (!$splitThread->forum->canWrite($this->request->user->ID)) {
                throw new ForbiddenError();
            }

            $redirectPost = $this->repos->forumThreads->split($thread, $posts, $option, $forum, $splitThread, $title);
        } else if (($option === "delete") && ($this->auth->checkAllowed('forum_post_delete') || $this->auth->checkAllowed('forum_thread_delete'))) {
            throw new ForbiddenError();
        } else if ($option === "trash" && $this->auth->checkAllowed('forum_post_trash')) {
            if ($thread->numPosts === count($posts)) {
                throw new UserError("You cannot trash ALL the posts from a thread");
            }

            if (array_key_exists($thread->lastPost->ID, array_column($posts->ID, null, 'ID'))) {
                # We're trashing the last post in the thread,
                # need to uncache the thread and forum objects
                if ($thread->ID === $thread->forum->lastThread->ID) {
                    $this->repos->forums->uncache($thread->forum->ID);
                }
                $this->repos->forumThreads->uncache($thread->ID);
            }

            # In rare occasions, a user may have been notified of a new post between its creation and deletion.
            # Because a direct deletion does not leave any notification in the thread
            # and in order to avoid a subscription counting bug we must delete the cache
            # key of all users subscribed to this thread.
            $userIDs = $this->db->rawQuery(
                'SELECT UserID
                   FROM forums_subscriptions
                  WHERE ThreadID = ?',
                [$thread->ID]
            )->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($userIDs as $userID) {
                $this->cache->deleteValue("subscriptions_user_new_{$userID}");
            }

            $sslurl = $this->settings->main->ssl_site_url;
            foreach ($posts as $post) {
                $post->setFlags(ForumPost::TRASHED);
                $this->repos->forumPosts->save($post);
                $this->irker->announcelab('Forum post '.$post->ID.' has been trashed in thread https://'.$sslurl.'/forum/thread/'.$thread->ID.'?postid='.$post->ID.'#post'.$post->ID.' by '.$this->request->user->Username);
            }
        } else {
            $comment = $this->request->getPostString('comment');
            $redirectPost = $this->repos->forumThreads->split($thread, $posts, $option, $forum, null, $title, $comment);
        }

        return new Redirect("/forum/thread/{$redirectPost->ThreadID}?postid={$redirectPost->ID}#post{$redirectPost->ID}");
    }

    public function newThreadForm($forumID) {
        if (!is_integer_string($forumID)) {
            throw new NotFoundError('This forum does not exist');
        }

        $forum = $this->repos->forums->load($forumID);

        # Check forum exists
        if (!$forum instanceof Forum) {
            throw new NotFoundError('This forum does not exist');
        }

        # Check if user can create a new post
        if (!$forum->canCreate($this->request->user)) {
            throw new ForbiddenError();
        }

        # Check if user can post at all
        if ($this->repos->restrictions->isRestricted($this->request->user->ID, Restriction::POST)) {
            throw new ForbiddenError();
        }

        $bscripts = ['comments', 'subscriptions', 'bbcode', 'jquery', 'jquery.cookie', 'jquery.modal', 'overlib'];
        $params = [
            'forum'    => $forum,
            'bscripts' => $bscripts,
        ];
        return new Rendered('@Forum/new_thread.html.twig', $params);
    }

    public function newThread($forumID) {
        if (!is_integer_string($forumID)) {
            throw new NotFoundError('', 'This forum does not exist');
        }

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'forum.newThread');

        $forum = $this->repos->forums->load($forumID);
        $user = $this->request->user;
        $bugForum = $this->options->SiteBugForum;

        $body = $this->request->getPostString('body');
        $title = $this->request->getPostString('title');
        $title = cut_string(trim($title), 150, 1, 0);

        # Hack to remove tags from threads in the site bugs forum
        if ($forum->ID === $bugForum && !$this->auth->isAllowed('site_debug')) {
            $title = trim(preg_replace('/^\[.*\]/', '', $title));
        }

        if (!$forum->canWrite($user) || !$forum->canCreate($user)) {
            throw new ForbiddenError('You cannot create a new thread in this forum');
        }

        # better to error out as at least they can go back and retreive the other post content
        if (empty($title)) {
            throw new UserError('You cannot create a thread with no title');
        }
        if (empty($body)) {
            throw new UserError('You cannot create a thread with no post content');
        }

        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        # Work-around for references to master inside the legacy Text class
        $master = $this->master;
        $bbCode = new Text;
        $bbCode->validate_bbcode($body, get_permissions_advtags($user->ID));

        flood_check();

        $thread = new ForumThread([
            'Title'     => $title,
            'AuthorID'  => $user->ID,
            'ForumID'   => $forum->ID,
        ]);
        $this->repos->forumThreads->save($thread);

        $post = new ForumPost([
            'ThreadID'  => $thread->ID,
            'AuthorID'  => $thread->AuthorID,
            'AddedTime' => new \DateTime,
            'Body'      => $body,
        ]);
        $this->repos->forumPosts->save($post);

        if (isset($_POST['subscribe'])) {
            $subscription = new ForumSubscription([
                'UserID'    => $user->ID,
                'ThreadID'  => $thread->ID,
            ]);
            $this->repos->forumSubscriptions->save($subscription, true);
            $this->cache->deleteValue("subscriptions_user_{$user->ID}");
        }

        $question = $this->request->getPostString('poll_question');
        $answers = $this->request->getPostArray('poll_answers');
        if (!empty($question) && !empty($answers) && $this->auth->isAllowed('forum_polls_create')) {
            $question = trim($question);

            # This can cause polls to have answer ids of 1 3 4 if the second box is empty
            foreach ($answers as &$answer) {
                if ($answer === '') {
                    unset($answer);
                }
            }

            # Re-Index the array
            $answers = array_values($answers);

            # Bump the indexes up by one to allow for "Blank" vote option
            array_unshift($answers, '');
            unset($answers[0]);

            if (count($answers) < 2) {
                throw new UserError('You cannot create a poll with only one answer');
            }
            if (count($answers) > 25) {
                throw new UserError('You cannot create a poll with greater than 25 answers');
            }

            $poll = new ForumPoll([
                'ThreadID'  => $thread->ID,
                'Question'  => $question,
                'Answers'   => serialize($answers),
            ]);
            $this->repos->forumPolls->save($poll);

            $staffClass = $this->repos->permissions->getMinClassPermission('forum_moderate');
            if ($forum->ID === $this->settings->forums->staff_forum_id) {
                $sslurl = $this->settings->main->ssl_site_url;
                $message = ("New staff poll created by ".$user->Username.": '".$question."' https://".$sslurl."/forum/thread/{$thread->ID}");
                $this->irker->announceReport($message);
            } else {
                if ($forum->MinClassRead <= $staffClass->Level) {
                    $sslurl = $this->settings->main->ssl_site_url;
                    $message = ("New forum poll: '".$question."' https://".$sslurl."/forum/thread/{$thread->ID}");
                    $this->irker->announcePublic($message);
                }
            }
        }

        $this->repos->forums->uncache($forum->ID);

        return new Redirect("/forum/thread/{$thread->ID}");
    }

    public function searchForm() {
        $user = $this->request->user;
        $pageSize = $user->options('PostsPerPage', $this->settings->pagination->posts);
        list($page, $limit) = page_limit($pageSize);

        # get type with defaults.
        $type = $this->request->getGetString('type') ?? 'title';
        if (!in_array($type, ['title', 'body', 'user'])) {
            $type = 'title';
        }

        # Searching for posts by a specific user
        $username = $this->request->getGetString('username');
        $author = null;
        if (!empty($username)) {
            $author = $this->repos->users->getByUsername($username);
            if (!$author instanceof User) {
                $this->flasher->error('User does not exist!');
            }
        }

        # Are we looking in individual forums?
        $forumIDs = $this->request->getGetArray('forums');
        if (!empty($forumIDs)) {
            foreach ($forumIDs as &$forumID) {
                if (!is_integer_string($forumID)) {
                    unset($forumID);
                }
            }
        }

        # Format the array as expected by the select() function
        $forumIDs = array_fill_keys($forumIDs, '1');

        # Searching for posts in a specific thread
        $thread = null;
        $threadID = $this->request->getGetInt('threadid', null);
        if (!(is_null($threadID))) {
            $type='body';

            $thread = $this->repos->forumThreads->load($threadID);
            if (!$thread instanceof ForumThread) {
                throw new NotFoundError('', 'This thread does not exist');
            }
            if (!$thread->forum->canRead($this->request->user)) {
                throw new ForbiddenError();
            }
        }

        # What are we looking for? Let's make sure it isn't dangerous.
        $terms = $this->request->getGetString('terms');
        $terms = trim($terms);

        # Break search string down into individual words
        $words = explode(' ',  $terms);
        foreach ($words as &$word) {
            $word = trim($word);
            $word = "%$word%";
        }

        # Always filter permitted/restricted SQL
        $queryParams = [];
        $where = "((f.MinClassRead <= ?";
        $queryParams[] = $user->class->Level;
        if (!empty($user->legacy['RestrictedForums'])) {
            $restrictedForums = (array)explode(',', $user->legacy['RestrictedForums']);
            $inQuery = implode(',', array_fill(0, count($restrictedForums), '?'));
            $where .=" AND f.ID NOT IN ({$inQuery})";
            $queryParams = array_merge($queryParams, $restrictedForums);
        }
        $where .= ')';
        if (!empty($user->legacy['PermittedForums']) || !empty($user->group->Forums)) {
            $userForums  = (array)explode(',', ($user->legacy['PermittedForums'] ?? ''));
            $groupForums = (array)explode(',', ($user->group->Forums ?? ''));
            $permittedForums = array_merge($userForums, $groupForums);
            $inQuery = implode(',', array_fill(0, count($permittedForums), '?'));
            $where .=" OR f.ID IN  ({$inQuery})";
            $queryParams = array_merge($queryParams, $permittedForums);
        }
        $where .= ') ';

        # Filter for selected forums
        if (!empty($forumIDs)) {
            $inQuery = implode(',', array_fill(0, count($forumIDs), '?'));
            $where .=" AND f.ID IN ({$inQuery})";
            $queryParams = array_merge($queryParams, array_keys($forumIDs));
        }

        $threads = null;
        $posts = null;
        $results = 0;

        if ($type === 'body') {
            # Filter for specified user
            if ($author instanceof User) {
                $where .=" AND fp.AuthorID=? ";
                $queryParams[] = $author->ID;
            }

            # Filter for specified thread
            if ($thread instanceof ForumThread) {
                $where .=" AND ft.ID=? ";
                $queryParams[] = $thread->ID;
            }

            if (!empty($terms)) {
                $likeQuery = implode(" AND fp.Body LIKE ", array_fill(0, count($words), '?'));
                $where .= " AND fp.Body LIKE {$likeQuery} ";
                $queryParams = array_merge($queryParams, $words);
            }
            # Perform the query
            $postIDs = $this->db->rawQuery(
                "SELECT SQL_CALC_FOUND_ROWS
                        fp.ID
                   FROM forums_posts AS fp
                   JOIN forums_threads AS ft ON ft.ID=fp.ThreadID
                   JOIN forums AS f ON f.ID=ft.ForumID
                  WHERE {$where}
               ORDER BY fp.AddedTime DESC
                  LIMIT {$limit}",
                $queryParams
            )->fetchAll(\PDO::FETCH_NUM);

            $posts = [];
            $results = $this->db->foundRows();
            foreach ($postIDs as $postID) {
                $posts[] = $this->repos->forumPosts->load($postID);
            }
        } else {
            # Filter for specified user
            if ($author instanceof User) {
                $where .=" AND ft.AuthorID=? ";
                $queryParams[] = $author->ID;
            }

            if (!empty($terms)) {
                $likeQuery = implode(" AND ft.Title LIKE ", array_fill(0, count($words), '?'));
                $where .= " AND ft.Title LIKE {$likeQuery} ";
                $queryParams = array_merge($queryParams, $words);
            }

            # Perform the query
            $threadIDs = $this->db->rawQuery(
                "SELECT SQL_CALC_FOUND_ROWS
                        ft.ID
                   FROM forums_threads AS ft
                   JOIN forums AS f ON f.ID = ft.ForumID
                   JOIN (SELECT ThreadID, MAX(AddedTime) AS LastPost FROM forums_posts GROUP BY ThreadID) AS fp ON fp.ThreadID = ft.ID
                  WHERE {$where}
               ORDER BY fp.LastPost DESC
                  LIMIT {$limit}",
                $queryParams
            )->fetchAll(\PDO::FETCH_NUM);

            $threads = [];
            $results = $this->db->foundRows();
            foreach ($threadIDs as $threadID) {
                $threads[] = $this->repos->forumThreads->load($threadID);
            }
        }

        $bscripts = ['comments', 'jquery', 'jquery.cookie'];

        $params = [
            'page'       => $page,
            'pageSize'   => $pageSize,
            'thread'     => $thread,
            'threads'    => $threads,
            'posts'      => $posts,
            'results'    => $results,
            'terms'      => $terms,
            'type'       => $type,
            'forums'     => $forumIDs,
            'username'   => $username,
            'categories' => $this->repos->forumCategories->find(null, null, 'Sort', null, 'forums_categories'),
            'bscripts'   => $bscripts,
        ];

        foreach ($params['categories'] as $categoryIndex => $category) {
            # Filter out the forums which the user lacks authorization to view.
            $forums = $category->allForums;
            foreach ($forums as $forumIndex => $forum) {
                if (!$forum->canRead($this->request->user)) {
                    unset($forums[$forumIndex]);
                }
            }

            # Also filter out empty categories
            if (empty($forums)) {
                unset($params['categories'][$categoryIndex]);
            } else {
                $category->forums = $forums;
            }
        }

        return new Rendered('@Forum/search.html.twig', $params);
    }

    public function catchup($forumID = null) {
        $user = $this->request->user;

        if (is_integer_string($forumID)) {
            $threads = $this->db->rawQuery(
                "SELECT ThreadID
                   FROM forums_last_read_threads AS flrt
                   JOIN forums_threads AS ft ON ft.ID=flrt.ThreadID
                  WHERE flrt.UserID = ?
                    AND ft.ForumID = ?",
                [$user->ID, $forumID]
            )->fetchAll(\PDO::FETCH_COLUMN);

            $this->db->rawQuery(
                "INSERT INTO forums_last_read_threads (UserID, ThreadID, PostID)
                  SELECT ?, fp.ThreadID, MAX(fp.ID) AS LastPostID
                    FROM forums_posts AS fp
                    JOIN forums_threads AS ft ON ft.ID=fp.ThreadID
                    JOIN users_info AS i ON i.UserID=?
                   WHERE (fp.AddedTime>i.CatchupTime OR i.CatchupTime is null)
                     AND ForumID = ?
                GROUP BY fp.ThreadID
                 ON DUPLICATE KEY UPDATE PostID=VALUES(PostID)",
                [$user->ID, $user->ID, $forumID]
            );

            foreach ($threads as $thread) {
                $this->repos->forumLastReads->uncache([$thread, $user->ID]);
            }

            return new Redirect("/forum/{$forumID}");
        } else {
            $this->db->rawQuery(
                "UPDATE users_info
                    SET CatchupTime = ?
                  WHERE UserID = ?",
                [sqltime(), $user->ID]
            );
            $this->repos->users->uncache($user->ID);
            return $this->request->back('/forum');
        }
    }

    public function unreadPosts($forumID = null) {
        $user = $this->request->user;
        $pageSize = $user->options('PostsPerPage', $this->settings->pagination->posts);
        list($page, $limit) = page_limit($pageSize);

        # Always filter permitted/restricted SQL
        $queryParams = [];
        $queryParams[] = $user->ID;
        $queryParams[] = $user->ID;
        $queryParams[] = $user->ID;
        $where = "((f.MinClassRead <= ?";
        $queryParams[] = $user->class->Level;
        if (!empty($user->legacy['RestrictedForums'])) {
            $restrictedForums = (array)explode(',', $user->legacy['RestrictedForums']);
            $inQuery = implode(',', array_fill(0, count($restrictedForums), '?'));
            $where .=" AND f.ID NOT IN ({$inQuery})";
            $queryParams = array_merge($queryParams, $restrictedForums);
        }
        $where .= ')';
        if (!empty($user->legacy['PermittedForums']) || !empty($user->group->Forums)) {
            $userForums  = (array)explode(',', $user->legacy['PermittedForums']);
            $groupForums = (array)explode(',', $user->group->Forums);
            $permittedForums = array_merge($userForums, $groupForums);
            $inQuery = implode(',', array_fill(0, count($permittedForums), '?'));
            $where .=" OR f.ID IN  ({$inQuery})";
            $queryParams = array_merge($queryParams, $permittedForums);
        }
        $where .= ') ';

        if (is_integer_string($forumID)) {
            $where .= "AND ft.ForumID = ?";
            $queryParams[] = $forumID;
        }

        # I cannot find any useful way of caching this... problem is this is viewing user dependent, but clearing the cache is any user posting
        $posts = $this->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS
                    l.PostID
               FROM forums_threads AS ft
               LEFT JOIN (
                   SELECT MAX(fp.ID) AS LastPostID, fp.ThreadID
                     FROM forums_posts AS fp
                     JOIN (
                         SELECT MAX(AddedTime) AS AddedTime, l.ThreadID
                           FROM forums_posts AS fp
                           JOIN forums_last_read_threads AS l ON fp.ThreadID=l.ThreadID
                          WHERE l.UserID = ?
                       GROUP BY l.ThreadID
                     ) AS fp2 ON fp.AddedTime=fp2.AddedTime AND fp.ThreadID=fp2.ThreadID
                 GROUP BY fp.ThreadID
               ) AS lp ON lp.ThreadID=ft.ID
               JOIN forums_posts AS fp ON lp.LastPostID=fp.ID
               JOIN forums AS f ON f.ID = ft.ForumID
               JOIN users_info AS i
                 ON i.UserID = ?
                AND (i.CatchupTime < fp.AddedTime OR i.CatchupTime is null)
               JOIN forums_last_read_threads AS l
                 ON l.UserID = ?
                AND l.ThreadID = ft.ID
                AND (l.PostID is NULL OR l.PostID != lp.LastPostID)
              WHERE {$where}
           ORDER BY lp.LastPostID DESC
              LIMIT {$limit}",
            $queryParams
        )->fetchAll(\PDO::FETCH_COLUMN);

        $results = $this->db->foundRows();

        foreach ($posts as &$post) {
            $post = $this->repos->forumPosts->load($post);
        }

        $params = [
            'page'     => $page,
            'pageSize' => $pageSize,
            'posts'    => $posts,
            'results'  => $results,
        ];

        return new Rendered('@Forum/unread_posts.html.twig', $params);
    }

    public function manage() {
        $this->auth->checkAllowed('forum_admin');

        $categories = $this->repos->forumCategories->find(null, null, 'Sort', null, 'forums_categories');
        $forums = [];

        foreach ($categories as $category) {
            $forums = array_merge($forums, $category->allForums);
        }

        $params = [
            'forums'     => $forums,
            'classes'    => $this->repos->permissions->getClasses(),
            'categories' => $categories,
        ];

        return new Rendered('@Forum/manage.html.twig', $params);
    }

    protected function forumValidate() {
        $validate = new Validate;

        # Validate the "" field
        $validate->SetFields(
            'categoryid',
            true,
            'number',
            'Category must be set'
        );

        # Validate the "sort" field
        $validate->SetFields(
            'sort',
            true,
            'number',
            'Sort must be set'
        );

        # Validate the "name" field
        $validate->SetFields(
            'name',
            true,
            'string',
            'The name must be set, and has a max length of 40 characters',
            ['maxlength'=>40, 'minlength'=>1]
        );

        # Validate the "description" field
        $validate->SetFields(
            'description',
            false,
            'string',
            'The description has a max length of 255 characters',
            ['maxlength'=>255]
        );

        # Validate the "minclassread" field
        $validate->SetFields(
            'minclassread',
            true,
            'number',
            'MinClassRead must be set'
        );

        # Validate the "minclasswrite" field
        $validate->SetFields(
            'minclasswrite',
            true,
            'number',
            'MinClassWrite must be set'
        );

        # Validate the "minclasscreate" field
        $validate->SetFields(
            'minclasscreate',
            true,
            'number',
            'MinClassCreate must be set'
        );

        $errors = $validate->ValidateForm($this->request->post);

        if (empty($errors) === false) {
            throw new UserError($errors);
        }
    }

    public function forumCreate() {
        $this->auth->checkAllowed('forum_admin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'forum.create');

        $this->forumValidate();

        $forum = new Forum([
            'CategoryID'      => $this->request->getPostInt('categoryid'),
            'Sort'            => $this->request->getPostInt('sort'),
            'Name'            => $this->request->getPostString('name'),
            'Description'     => $this->request->getPostString('description'),
            'MinClassRead'    => $this->request->getPostInt('minclassread'),
            'MinClassWrite'   => $this->request->getPostInt('minclasswrite'),
            'MinClassCreate'  => $this->request->getPostInt('minclasscreate'),
            'AutoLock'        => $this->request->getPostBool('autolock'),
        ]);
        $this->repos->forums->save($forum);

        return new Redirect("/forum/manage");
    }

    public function forumEdit() {
        $this->auth->checkAllowed('forum_admin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'forum.edit');

        $id = $this->request->getPostInt('id');
        if (is_integer_string($id)) {
            $forum = $this->repos->forums->load($id);
            if ($forum instanceof Forum) {
                $this->forumValidate();

                $forum->CategoryID = $this->request->getPostInt('categoryid');
                $forum->Sort = $this->request->getPostInt('sort');
                $forum->Name = $this->request->getPostString('name');
                $forum->Description = $this->request->getPostString('description');
                $forum->MinClassRead = $this->request->getPostInt('minclassread');
                $forum->MinClassWrite = $this->request->getPostInt('minclasswrite');
                $forum->MinClassCreate = $this->request->getPostInt('minclasscreate');
                $forum->AutoLock = $this->request->getPostBool('autolock');
                $this->repos->forums->save($forum);

                return new Redirect("/forum/manage");
            } else {
                throw new NotFoundError('', 'This forum does not exist');
            }
        }

        throw new UserError('Malformed POST submission');
    }

    public function forumDelete() {
        $this->auth->checkAllowed('forum_admin');
        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'forum.delete');

        $id = $this->request->getPostInt('id');
        if (is_integer_string($id)) {
            $forum = $this->repos->forums->load($id);
            if ($forum instanceof Forum) {
                $this->repos->forums->delete($forum);

                return new Redirect("/forum/manage");
            } else {
                throw new NotFoundError('', 'This forum does not exist');
            }
        }

        throw new UserError('Malformed POST submission');
    }

    protected function categoryValidate() {
        $validate = new Validate;

        # Validate the "sort" field
        $validate->SetFields(
            'sort',
            true,
            'number',
            'Sort must be set'
        );

        # Validate the "name" field
        $validate->SetFields(
            'name',
            true,
            'string',
            'The name must be set, and has a max length of 40 characters',
            ['maxlength'=>40, 'minlength'=>1]
        );

        $errors = $validate->ValidateForm($this->request->post);

        if (empty($errors) === false) {
            throw new UserError($errors);
        }
    }

    public function categoryCreate() {
        $this->auth->checkAllowed('forum_admin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'forum.category.create');

        $this->categoryValidate();

        $category = new ForumCategory([
            'Sort'  => $this->request->getPostInt('sort'),
            'Name'  => $this->request->getPostString('name'),
        ]);
        $this->repos->forumCategories->save($category);

        $this->cache->deleteValue('forums_categories');

        return new Redirect("/forum/manage");
    }

    public function categoryEdit() {
        $this->auth->checkAllowed('forum_admin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'forum.category.edit');

        $id = $this->request->getPostInt('id');
        if (is_integer_string($id)) {
            $category = $this->repos->forumCategories->load($id);
            if ($category instanceof ForumCategory) {
                $this->categoryValidate();

                $category->Sort = $this->request->getPostInt('sort');
                $category->Name = $this->request->getPostString('name');
                $this->repos->forumCategories->save($category);
                $this->cache->deleteValue('forums_categories');

                return new Redirect("/forum/manage");
            } else {
                throw new NotFoundError('', 'This forum category does not exist');
            }
        }

        throw new UserError('Malformed POST submission');
    }

    public function categoryDelete() {
        $this->auth->checkAllowed('forum_admin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'forum.category.delete');

        $id = $this->request->getPostInt('id');
        if (is_integer_string($id)) {
            $category = $this->repos->forumCategories->load($id);
            if ($category instanceof ForumCategory) {
                $this->repos->forumCategories->delete($category);
                $this->cache->deleteValue('forums_categories');

                return new Redirect("/forum/manage");
            } else {
                throw new NotFoundError('', 'This forum category does not exist');
            }
        }

        throw new UserError('Malformed POST submission');
    }

    public function recent() {
        $this->auth->checkAllowed('users_fls');
        $queryParams = [];
        $user = $this->request->user;

        # Are we looking in individual forums?
        $forumIDs = $this->request->getGetArray('forums');

        # If nothing is being filtered load the defaults
        if (empty($forumIDs)) {
            $forumIDs = $user->options('allposts_forums', []);
        }

        # If any filters are applied then format the array correctly
        if (!empty($forumIDs)) {
            foreach ($forumIDs as &$forumID) {
                if (!is_integer_string($forumID)) {
                    unset($forumID);
                }
            }
        }

        if ($this->request->getGetBool('cleardefault')) {
            $user->unsetOption('allposts_forums');
            $forumIDs = [];
        }

        if ($this->request->getGetBool('makedefault')) {
            $user->setOption('allposts_forums', $forumIDs);
        }

        # Format the array as expected by the select() function
        $forumIDs = array_fill_keys($forumIDs, '1');

        $where = "((f.MinClassRead <= ?";

        $queryParams[] = $user->class->Level;
        if (!empty($user->legacy['RestrictedForums'])) {
            $restrictedForums = (array)explode(',', $user->legacy['RestrictedForums']);
            $inQuery = implode(',', array_fill(0, count($restrictedForums), '?'));
            $where .=" AND f.ID NOT IN ({$inQuery})";
            $queryParams = array_merge($queryParams, $restrictedForums);
        }
        $where .= ')';
        if (!empty($user->legacy['PermittedForums']) || !empty($user->group->Forums)) {
            $userForums  = (array)explode(',', $user->legacy['PermittedForums']);
            $groupForums = (array)explode(',', $user->group->Forums);
            $permittedForums = array_merge($userForums, $groupForums);
            $inQuery = implode(',', array_fill(0, count($permittedForums), '?'));
            $where .=" OR f.ID IN  ({$inQuery})";
            $queryParams = array_merge($queryParams, $permittedForums);
        }
        $where .= ') ';

        # Filter for selected forums
        if (!empty($forumIDs)) {
            $inQuery = implode(',', array_fill(0, count($forumIDs), '?'));
            $where .=" AND f.ID IN ({$inQuery})";
            $queryParams = array_merge($queryParams, array_keys($forumIDs));
        }

        $pageSize = $user->options('PostsPerPage', $this->settings->pagination->posts);
        list($page, $limit) = page_limit($pageSize);

        # Perform the query
        $postIDs = $this->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS fp.ID
               FROM forums_posts as fp
               JOIN forums_threads AS ft ON fp.ThreadID=ft.ID
               JOIN forums AS f ON ft.ForumID=f.ID
              WHERE {$where}
           ORDER BY fp.ID DESC
              LIMIT {$limit}",
            $queryParams
        )->fetchAll(\PDO::FETCH_NUM);

        $posts = [];
        $results = $this->db->foundRows();
        foreach ($postIDs as $postID) {
            $posts[] = $this->repos->forumPosts->load($postID);
        }

        $bscripts = ['comments', 'bbcode', 'jquery', 'jquery.cookie', 'overlib'];

        $params = [
            'bscripts'    => $bscripts,
            'page'        => $page,
            'posts'       => $posts,
            'forums'      => $forumIDs,
            'results'     => $results,
            'pageSize'    => $pageSize,
            'categories'  => $this->repos->forumCategories->find(null, null, 'Sort', null, 'forums_categories'),
        ];

        foreach ($params['categories'] as $categoryIndex => $category) {
            # Filter out the forums which the user lacks authorization to view.
            $forums = $category->allForums;
            foreach ((array)$forums as $forumIndex => $forum) {
                if (!$forum->canRead($user)) {
                    unset($forums[$forumIndex]);
                }
            }

            # Also filter out empty categories
            if (empty($forums)) {
                unset($params['categories'][$categoryIndex]);
            } else {
                $category->forums = $forums;
            }
        }

        return new Rendered('@Forum/recent.html.twig', $params);
    }

    public function rules($forumID) {
        $this->auth->checkAllowed('forum_set_rules');

        if (!is_integer_string($forumID)) {
            throw new NotFoundError('', 'This forum does not exist');
        }

        $forum = $this->repos->forums->load($forumID);

        # Check forum exists
        if (!$forum instanceof Forum) {
            throw new NotFoundError('', 'This forum does not exist');
        }

        $rules = $this->repos->forumRules->find('ForumID = ?', [$forumID], null, null, "forum_{$forumID}_rules");

        $params = [
            'forum' => $forum,
            'rules' => $rules,
        ];

        return new Rendered('@Forum/rules.html.twig', $params);
    }

    public function ruleAdd($forumID) {
        $forumID = intval($forumID);

        $this->auth->checkAllowed('forum_set_rules');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'forum.rules.add');

        if (!is_integer_string($forumID)) {
            throw new NotFoundError('', 'This forum does not exist');
        }

        $forum = $this->repos->forums->load($forumID);

        # Check forum exists
        if (!$forum instanceof Forum) {
            throw new NotFoundError('', 'This forum does not exist');
        }

        $threadID = $this->request->getPostInt('threadid');
        $thread = $this->repos->forumThreads->load($threadID);

        if (!$thread instanceof ForumThread) {
            throw new NotFoundError('', 'This forum thread does not exist');
        }

        if (!($thread->ForumID === $forumID)) {
            throw new UserError('This forum thread does not belong to this forum');
        }

        $rule = $this->repos->forumRules->get('ForumID = ? AND ThreadID = ?', [$forumID, $threadID]);

        if ($rule instanceof ForumRule) {
            throw new UserError('This forum thread is already a rule');
        }

        $rule = new ForumRule([
            'ForumID'   => $forumID,
            'ThreadID'  => $threadID,
        ]);
        $this->repos->forumRules->save($rule);
        $this->cache->deleteValue("forum_{$forumID}_rules");

        return new Redirect("/forum/{$forumID}/rules");
    }

    public function ruleDelete($forumID) {
        $forumID = intval($forumID);

        $this->auth->checkAllowed('forum_set_rules');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'forum.rules.delete');

        if (!is_integer_string($forumID)) {
            throw new NotFoundError('', 'This forum does not exist');
        }

        $forum = $this->repos->forums->load($forumID);

        # Check forum exists
        if (!$forum instanceof Forum) {
            throw new NotFoundError('', 'This forum does not exist');
        }

        $ruleID = $this->request->getPostInt('ruleid');
        $rule = $this->repos->forumRules->load($ruleID);

        if (!$rule instanceof ForumRule) {
            throw new NotFoundError('', 'This forum rule does not exist');
        }

        if (!($rule->ForumID === $forumID)) {
            throw new UserError('This forum rule does not belong to this forum');
        }

        $this->repos->forumRules->delete($rule);
        $this->cache->deleteValue("forum_{$forumID}_rules");

        return new Redirect("/forum/{$forumID}/rules");
    }
}
