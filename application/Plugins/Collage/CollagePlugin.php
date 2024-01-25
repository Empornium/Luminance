<?php
namespace Luminance\Plugins\Collage;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Errors\Error;
use Luminance\Errors\UserError;
use Luminance\Errors\SystemError;
use Luminance\Errors\NotFoundError;
use Luminance\Errors\ForbiddenError;

use Luminance\Entities\CommentEdit;
use Luminance\Entities\Restriction;
use Luminance\Entities\TorrentGroup;

use Luminance\Entities\Collage;
use Luminance\Entities\CollageComment;
use Luminance\Entities\CollageCategory;
use Luminance\Entities\CollageTorrent;

use Luminance\Services\Auth;

use Luminance\Responses\JSON;
use Luminance\Responses\Redirect;
use Luminance\Responses\Rendered;
use Luminance\Responses\Response;

use Luminance\Legacy\Text;
use Luminance\Legacy\Validate;

use ZipStream\ZipStream;

class CollagePlugin extends Plugin {

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  '*',                     Auth::AUTH_LOGIN,  'collage'                  ],
        [ 'GET',  'mine',                  Auth::AUTH_LOGIN,  'personalCollages'         ],
        [ 'GET',  'user/*',                Auth::AUTH_LOGIN,  'userCollages'             ],
        [ 'GET',  'user/*/contributions',  Auth::AUTH_LOGIN,  'userCollageContributions' ],
        [ 'POST', '*/download',            Auth::AUTH_LOGIN,  'download'                 ],
        [ 'GET',  '*/manage',              Auth::AUTH_LOGIN,  'manageForm'               ],
        [ 'POST', '*/manage/*/edit',       Auth::AUTH_LOGIN,  'manageEdit'               ],
        [ 'POST', '*/manage/*/remove',     Auth::AUTH_LOGIN,  'removeTorrent'            ],
        [ 'GET',  'bookmarks',             Auth::AUTH_LOGIN,  'bookmarks'                ],
        [ 'GET',  'create',                Auth::AUTH_2FA,    'createCollageForm'        ],
        [ 'POST', 'create',                Auth::AUTH_2FA,    'createCollage'            ],
        [ 'GET',  '*/edit',                Auth::AUTH_LOGIN,  'editCollageForm'          ],
        [ 'POST', '*/edit',                Auth::AUTH_LOGIN,  'editCollage'              ],
        [ 'GET',  'recent',                Auth::AUTH_IPLOCK, 'recent'                   ],
        [ 'POST', '*/add',                 Auth::AUTH_LOGIN,  'addTorrent'               ],
        [ 'GET',  '*/remove',              Auth::AUTH_LOGIN,  'removeForm'               ],
        [ 'POST', '*/trash',               Auth::AUTH_LOGIN,  'trash'                    ],
        [ 'POST', '*/delete',              Auth::AUTH_2FA,    'delete'                   ],
        [ 'POST', '*/comment',             Auth::AUTH_LOGIN,  'comment'                  ],
        [ 'POST', '*/level/assign',        Auth::AUTH_LOGIN,  'levelAssign'              ],
        [ 'POST', '*/groups/assign',       Auth::AUTH_LOGIN,  'groupsAssign'             ],
        [ 'GET',  'category/manage',       Auth::AUTH_2FA,    'categoryManage'           ],
        [ 'POST', 'category/create',       Auth::AUTH_2FA,    'categoryCreate'           ],
        [ 'POST', 'category/edit',         Auth::AUTH_2FA,    'categoryEdit'             ],
        [ 'POST', 'category/delete',       Auth::AUTH_2FA,    'categoryDelete'           ],
        [ 'GET',  'post/*/remove',         Auth::AUTH_IPLOCK, 'postRemoveForm'           ],
        [ 'POST', 'post/*/trash',          Auth::AUTH_IPLOCK, 'postTrash'                ],
        [ 'POST', 'post/*/delete',         Auth::AUTH_2FA,    'postDelete'               ],
        [ 'GET',  'post/*/get',            Auth::AUTH_LOGIN,  'postGetForm'              ],
        [ 'GET',  'post/*/edit',           Auth::AUTH_LOGIN,  'postEditForm'             ],
        [ 'POST', 'post/*/get',            Auth::AUTH_LOGIN,  'postGet'                  ],
        [ 'POST', 'post/*/quote',          Auth::AUTH_LOGIN,  'postQuote'                ],
        [ 'POST', 'post/*/edit',           Auth::AUTH_LOGIN,  'postEdit'                 ],
        [ 'POST', 'post/*/revert',         Auth::AUTH_IPLOCK, 'postRevert'               ],
        [ 'POST', 'post/*/editlock',       Auth::AUTH_IPLOCK, 'editLock'                 ],
        [ 'POST', 'post/*/timelock',       Auth::AUTH_IPLOCK, 'timeLock'                 ],
        [ 'POST', 'post/*/pinpost',        Auth::AUTH_IPLOCK, 'postPin'                  ],
    ];

    protected static $useServices = [
        'auth'      => 'Auth',
        'db'        => 'DB',
        'cache'     => 'Cache',
        'irker'     => 'Irker',
        'secretary' => 'Secretary',
        'options'   => 'Options',
        'settings'  => 'Settings',
        'repos'     => 'Repos',
        'render'    => 'Render',
    ];

    protected static $userinfoTools = [
        [
            'collage_admin',           # permission
            'collage/category/manage', # action
            'Collage Categories'       # title
        ],
        [
            'users_fls',               # permission
            'collage/recent',          # action
            'Recent Collage Comments'  # title
        ],
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ '*', 'collage/**', Auth::AUTH_LOGIN, 'plugin', 'Collage' ]);
    }

    private function getCollageSort() {
        $orderWay = $this->request->getGetString(
            'order_way',
            'desc',
            [
                'desc',
                'asc'
            ]
        );

        $orderBy  = $this->request->getGetString(
            'order_by',
            'LastDate',
            [
                'Name',
                'NumTorrents',
                'StartDate',
                'LastDate',
                'Username',
                'Subscribers'
            ]
        );

        return [$orderWay, $orderBy];
    }

    private function getTorrentSort() {
        $orderWay = $this->request->getGetString(
            'order_way',
            'asc',
            [
                'desc',
                'asc'
            ]
        );

        $orderBy  = $this->request->getGetString(
            'order_by',
            'Sort',
            [
                'Added',
                'Title',
                'Size',
                'UploadDate',
                'Snatched',
                'Seeders',
                'Leechers',
                'Sort'
            ]
        );

        return [$orderWay, $orderBy];
    }

    public function collage($collageID = null) {
        if (is_integer_string($collageID)) {
            return $this->displayCollage($collageID);
        }
        return $this->displayCollages();
    }

    public function userCollages($userID) {
        return $this->displayCollages($userID);
    }

    public function personalCollages() {
        $user = $this->request->user;
        return $this->displayCollages($user->ID, true);
    }

    public function userCollageContributions($userID) {
        return $this->displayCollages($userID, false, true);
    }

    private function displayCollage($collageID = null, $userID = null) {
        $user = $this->request->user;
        $collage = $this->repos->collages->load($collageID);
        if (!$collage instanceof Collage) {
            throw new NotFoundError('This collage does not exist');
        }

        if ($collage->isTrashed() && !$this->auth->isAllowed('collage_trash')) {
            return new Redirect('log.php?search=Collage+'.$collage->ID);
        }

        $collagePageSize = $user->options('TorrentsPerPage', $this->settings->pagination->torrents);

        list($orderWay, $orderBy) = $this->getTorrentSort();
        list($collagePage, $collageLimit) = page_limit($collagePageSize);

        if (($collageSubscriptions = $this->cache->getValue('collage_subs_user_'.$user->ID)) === false) {
            $collageSubscriptions = $this->db->rawQuery(
                "SELECT CollageID
                   FROM collages_subscriptions
                  WHERE UserID = ?",
                [$user->ID]
            )->fetchAll(\PDO::FETCH_COLUMN);
            $this->cache->cacheValue('collage_subs_user_'.$user->ID, $collageSubscriptions, 0);
        }

        if (empty($collageSubscriptions)) {
            $collageSubscriptions = [];
        }

        if (in_array($collage->ID, $collageSubscriptions)) {
            $this->cache->deleteValue('collage_subs_user_new_'.$user->ID);
        }

        $this->db->rawQuery(
            "UPDATE collages_subscriptions SET LastVisit=NOW() WHERE UserID = ? AND CollageID=?",
            [$user->ID, $collage->ID]
        );

        $ctlist = $this->db->rawQuery(
            "SELECT ct.GroupID,
                    ct.UserID,
                    tg.Time as UploadDate,
                    tg.Name as Title,
                    ct.AddedOn AS Added,
                    t.Size,
                    t.Snatched,
                    t.Seeders,
                    t.Leechers
               FROM collages_torrents AS ct
               JOIN torrents_group AS tg ON tg.ID=ct.GroupID
               JOIN torrents AS t ON t.GroupID=tg.ID
              WHERE ct.CollageID = ?
           ORDER BY {$orderBy} {$orderWay}
              LIMIT {$collageLimit}",
            [$collage->ID]
        )->fetchAll(\PDO::FETCH_ASSOC|\PDO::FETCH_UNIQUE);

        $groups = [];
        if (is_array($ctlist)) {
            $groupIDs = array_keys($ctlist);
            if (count($groupIDs)>0) {
                $groups = get_groups($groupIDs, true, true, true);
                $groups = $groups['matches'];
            }
        }

        $bookmarks = all_bookmarks('torrent');

        if ($this->auth->isAllowed('collage_post_trash')) {
            $checkFlags = CollageComment::PINNED;
        } else {
            $checkFlags = CollageComment::PINNED | CollageComment::TRASHED;
        }

        $commentPageSize = $user->options('PostsPerPage', $this->settings->pagination->torrent_comments);
        list($commentPage, $commentLimit) = page_limit($commentPageSize, 1, 'commentPage');

        # We could implement catalog caching for the thread IDs, but it's not worth it
        $comments = $this->repos->collagecomments->find(
            'CollageID = ? AND Flags & ? = 0',
            [$collage->ID, $checkFlags],
            'AddedTime',
            $commentLimit
        );

        $pinnedComments = $this->repos->collagecomments->find(
            'CollageID = ? AND Flags & ? = ?',
            [$collage->ID, $checkFlags, CollageComment::PINNED],
            'AddedTime',
            $commentLimit
        );

        $comments = array_merge($pinnedComments, $comments);

        foreach ($groups as $index => &$group) {
            $torrent          = end($group['Torrents']);
            $username         = anon_username($torrent['Username'], $torrent['Anonymous']);
            $group['Image']   = fapping_preview($group['Image'], 300);
            $group['review']  = get_last_review($index);
            $group['icons']   = torrent_icons($torrent, $torrent['ID'], $group['review'], in_array($groups[$index]['ID'], $bookmarks));
            $group['mfd']     = $group['review']['Status'] === 'Warned' || $group['review']['Status'] === 'Pending';
            $group['added']   = $ctlist[$index]['Added'];
            $group['overlay'] = get_overlay_html($group['Name'], $username, $group['Image'], $torrent['Seeders'], $torrent['Leechers'], $torrent['Size'], $torrent['Snatched']);
        }

        $classLevels      = $this->availablePermissions();

        $bscripts = ['comments', 'collage', 'bbcode', 'jquery', 'jquery.cookie', 'jquery.modal', 'overlib'];

        $params = [
            'collage'         => $collage,
            'categories'      => getNewCategories(),
            'classLevels'     => $classLevels,
            'collagePage'     => $collagePage,
            'collagePageSize' => $collagePageSize,
            'groups'          => $groups,
            'commentPage'     => $commentPage,
            'commentPageSize' => $commentPageSize,
            'comments'        => $comments,
            'userID'          => $user->ID,
            'bscripts'        => $bscripts,
        ];

        return new Rendered('@Collage/collage.html.twig', $params);
    }


    private function displayCollages($userID = null, $personalOnly = false, $contributions = false) {
        $user = $this->request->user;
        $collagesPerPage = $user->options('CollagesPerPage', $this->settings->pagination->collages);

        $categories = $this->repos->collageCategories->find(null, null, 'sort', null, 'collages_categories');

        # Unfortunately we need to do table joins in the query that will eventually follow,
        # and the ORM can't handle that just yet so this fugly Gazelle looking code is what
        # we're stuck with for now.
        $where = '';
        $queryParams = [];

        # Category selection, default to all categories
        $searchCategories = $this->request->getGetArray('cats') ?? [];

        if (is_integer_string($userID) && $contributions === false) {
            $user = $this->repos->users->load($userID);
            if (!check_paranoia('collages', $user->legacy['Paranoia'], $user->class->Level, $user->ID)) {
                throw new UserError('This users privacy (paranoia) settings mean you cannot view this page');
            }

            if ($personalOnly === true) {
                # For personal collage foce the user object back to request user (should be already) and abuse
                # category search a little bit.
                $user = $this->request->user;
                $personalCategories = $this->repos->collageCategories->find('Flags & ? != 0', [CollageCategory::PERSONAL]);
                $searchCategories = array_column($personalCategories, 'ID', 'ID');
            }

            $where .= " AND c.UserID = ?";
            $queryParams[] = $user->ID;
        }

        if (is_integer_string($userID) && $contributions === true) {
            $user = $this->repos->users->load($userID);
            if (!check_paranoia('collagecontribs', $user->legacy['Paranoia'], $user->class->Level, $user->ID)) {
                throw new UserError('This users privacy (paranoia) settings mean you cannot view this page');
            }

            $where .= " AND ct.UserID = ?";
            $queryParams[] = $user->ID;
        }

        if (empty($searchCategories)) {
            $searchCategories = $categories;
        } else {
            $inQuery = implode(',', array_fill(0, count($searchCategories), '?'));
            $where .= " AND CategoryID IN ({$inQuery})";
            $queryParams = array_merge($queryParams, array_keys($searchCategories));
        }
        foreach ($searchCategories as &$category) {
            $category = '1';
        }

        # What are we looking for? Let's make sure it isn't dangerous.
        $searchTerms = $this->request->getGetString('terms');
        $searchTags  = $this->request->getGetString('tags');
        $searchTerms = trim($searchTerms);
        $searchTags  = trim($searchTags);

        # Break search string down into individual words
        $words = explode(' ',  $searchTerms);
        foreach ($words as &$word) {
            $word = trim($word);
            $word = "%$word%";
        }

        # Break tag string down into individual tags
        $tags = preg_split('/( |,)/',  $searchTags);
        foreach ($tags as $idx => &$tag) {
            $tag = trim($tag);
            if (empty($tag)) {
                unset($tags[$idx]);
                continue;
            }
            $tag = sanitize_tag($tag);
            $tag = "%$tag%";
        }

        # get type with defaults.
        $searchType = $this->request->getGetString('type') ?? 'title';
        if (!in_array($searchType, ['title', 'description'])) {
            $searchType = 'title';
        }

        if (!empty($searchTerms)) {
            switch ($searchType) {
                case 'description':
                    $column = 'c.Description';
                    break;
                case 'title':
                default:
                    $column = 'c.Name';
            }
            $likeQuery = implode(" AND {$column} LIKE ", array_fill(0, count($words), '?'));
            $where .= " AND {$column} LIKE {$likeQuery} ";
            $queryParams = array_merge($queryParams, $words);
        }

        if (!empty($searchTags)) {
            $likeQuery = implode(" AND c.TagList LIKE ", array_fill(0, count($tags), '?'));
            $where .= " AND c.TagList LIKE {$likeQuery} ";
            $queryParams = array_merge($queryParams, $tags);
        }

        # Allow staff to see deleted collages
        if ($this->auth->isAllowed('collage_trash')) {
            $checkFlags = 0;
        } else {
            $checkFlags = Collage::TRASHED;
        }

        $queryParams = array_merge([$checkFlags], $queryParams);
        list($orderWay, $orderBy) = $this->getCollageSort();
        list($page, $limit) = page_limit($collagesPerPage);

        $collages = $this->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS
                    c.ID,
                    Max(ct.AddedOn) AS LastDate,
                    (SELECT COUNT(*)
                       FROM collages_subscriptions
                      WHERE CollageID = c.ID ) AS Subscribers
               FROM collages AS c
               LEFT JOIN collages_torrents AS ct ON ct.CollageID=c.ID
              WHERE Flags & ? = '0' {$where}
           GROUP BY c.ID
           ORDER BY {$orderBy} {$orderWay}
              LIMIT {$limit}",
            $queryParams
        )->fetchAll(\PDO::FETCH_COLUMN);

        $results = $this->db->foundRows();

        $pages = get_pages($page, $results, $collagesPerPage, 8, '#torrent_table');

        foreach ($collages as &$collage) {
            $collage = $this->repos->collages->load($collage);
        }

        $personalCollages = $this->db->rawQuery(
            "SELECT c.ID
               FROM collages AS c
               JOIN collage_categories AS cc ON c.CategoryID = cc.ID
              WHERE c.UserID = ?
                AND c.Flags & ? = 0
                AND cc.Flags & ? != 0",
            [$user->ID, $checkFlags, CollageCategory::PERSONAL]
        )->fetchAll(\PDO::FETCH_OBJ);

        $minPersonalClass = $this->repos->permissions->getMinClassPermission('collage_create');

        $bscripts = ['data_action', 'collage', 'jquery', 'jquery.cookie', 'jquery.modal'];

        $params = [
            'type'              => $searchType,
            'terms'             => $searchTerms,
            'tags'              => $searchTags,
            'page'              => $page,
            'results'           => $results,
            'collagesPerPage'   => $collagesPerPage,
            'categories'        => $categories,
            'collages'          => $collages,
            'minPersonalClass'  => $minPersonalClass,
            'personalCollages'  => $personalCollages,
            'searchCategories'  => $searchCategories,
            'bscripts'          => $bscripts,
        ];

        return new Rendered('@Collage/collages.html.twig', $params);
    }

    public function download($collageID) {
        $user = $this->request->user;

        $this->auth->checkAllowed('site_zip_downloader');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.download');

        $preference = $this->request->getPostInt('preference');

        if (!in_array($preference, [0, 1, 2])) {
            throw new UserError("Unknown Download Option");
        }

        if (!$this->options->EnableDownloads) {
            throw new UserError("Downloads are currently disabled");
        }

        $collage = $this->repos->collages->load($collageID);
        if (!($collage instanceof Collage)) {
            throw new SystemError("Could not load Collage data");
        }

        $where = '';
        switch ($preference) {
            case 1:
                $where = 'WHERE t.Seeders >= 1';
                break;
            case 2:
                $where = 'WHERE t.Seeders >= 5';
                break;
            default:
                break;
        }

        $downloads = $this->db->rawQuery(
            "SELECT t.GroupID,
                    t.ID,
                    tg.Name
               FROM torrents AS t
         INNER JOIN collages_torrents AS c ON t.GroupID = c.GroupID
                AND c.CollageID = ?
         INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID
                    {$where}
           ORDER BY t.GroupID ASC",
            [$collage->ID]
        )->fetchAll(\PDO::FETCH_OBJ);

        $stats = $this->db->rawQuery(
            "SELECT COUNT(*) AS Downloaded,
                    SUM(t.Size) AS TotalSize
               FROM torrents AS t
         INNER JOIN collages_torrents AS c ON t.GroupID= c .GroupID
                AND c.CollageID = ?
         INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID
                    {$where}",
            [$collage->ID]
        )->fetch(\PDO::FETCH_OBJ);

        $date = date('M d Y, H:i');

        $zipFileName = file_string($collage->Name.'.zip');
        $summary = "Collage Archive Summary - {$this->settings->main->site_name}\r\n\r\n".
                   "Date:\t\t{$date}\r\n\r\n".
                   "User:\t\t{$user->Username}\r\n".
                   "Passkey:\t{$user->legacy['torrent_pass']}\r\n\r\n".
                   "Torrents Downloaded:\t\t{$stats->Downloaded}\r\n\r\n".
                   "Total Size of Torrents (Ratio Hit): ".get_size($stats->TotalSize)."\r\n";

        # Turn off *ALL* output buffering
        for ($i=0; $i <= ob_get_level(); $i++) {
            ob_end_clean();
        }

        # Detect Zipstream >= 1.0.0 by presence of the Option classes
        if (class_exists('ZipStream\Option\Archive')) {
            # enable output of HTTP headers
            $options = new \ZipStream\Option\Archive();
            $options->setSendHttpHeaders(true);
            $options->setComment($summary);
            $zipFile = new ZipStream($zipFileName, $options);
        } else {
            $zipFile = new ZipStream($zipFileName);
        }

        foreach ($downloads as $download) {
            # Timeout of 60 seconds per torrent
            set_time_limit(60);
            $torrent = getTorrentFile($download->ID, $user->legacy['torrent_pass']);

            $torrentName = '['.$this->settings->main->site_name.']'.((!empty($download->Name)) ? $download->Name : 'No Name');
            $fileName = trim(file_string($torrentName));
            $fileName = cut_string($fileName, 192, true, false);
            $fileName .= '.torrent';

            $zipFile->addFile($fileName, $torrent->enc());
            # Delete the torrent from memory so GC can collect it later if needed
            unset($torrent);
            # Flush chunk to browser
            @ob_flush();
            flush();
        }

        // 60 seconds to complete the zip download etc
        set_time_limit(60);
        $zipFile->addFile('Summary.txt', $summary);
        $zipFile->finish();
    }

    public function manageForm($collageID) {
        $collage = $this->repos->collages->load($collageID);
        if (!($collage instanceof Collage)) {
            throw new NotFoundError('This collage does not exist');
        }

        if (!$collage->canManage()) {
            return new ForbiddenError('You cannot manage this collage');
        }

        return new Rendered('@Collage/manage.html.twig', compact('collage'));
    }

    public function manageEdit($collageID, $groupID) {
        $user = $this->request->user;

        $collage = $this->repos->collages->load($collageID);
        if (!($collage instanceof Collage)) {
            throw new NotFoundError('This collage does not exist');
        }

        if (!$collage->canManage()) {
            return new ForbiddenError('You cannot manage this collage');
        }

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.manage');

        $group = $this->repos->torrentGroups->load($groupID);
        if (!($group instanceof TorrentGroup)) {
            throw new NotFoundError('This torrent does not exist');
        }

        $collageTorrent = $this->repos->collageTorrents->load([$collage->ID, $group->ID]);
        if (!($collageTorrent instanceof CollageTorrent)) {
            throw new NotFoundError('This torrent is not part of this collage');
        }

        $collageTorrent->Sort = $this->request->getPostInt('sort');
        $this->repos->collageTorrents->save($collageTorrent);

        return new Redirect("/collage/{$collage->ID}/manage");
    }

    public function removeTorrent($collageID, $groupID) {
        $user = $this->request->user;

        $collage = $this->repos->collages->load($collageID);
        if (!($collage instanceof Collage)) {
            throw new NotFoundError('This collage does not exist');
        }

        if (!$collage->canManage()) {
            return new ForbiddenError('You cannot manage this collage');
        }

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.manage');

        $group = $this->repos->torrentGroups->load($groupID);
        if (!($group instanceof TorrentGroup)) {
            throw new NotFoundError('This torrent does not exist');
        }

        $collageTorrent = $this->repos->collageTorrents->load([$collage->ID, $group->ID]);
        if (!($collageTorrent instanceof CollageTorrent)) {
            throw new NotFoundError('This torrent is not part of this collage');
        }

        $this->repos->collageTorrents->delete($collageTorrent);
        $this->cache->deleteValue('torrents_details_'.$group->ID);
        $this->cache->deleteValue('torrent_collages_'.$group->ID);
        $this->cache->deleteValue('torrent_collages_personal_'.$group->ID);
        write_log("Collage {$collageID} ({$collage->Name}) was edited by {$user->Username} - removed torrent {$group->ID}");

        return new Redirect("/collage/{$collage->ID}/manage");
    }

    public function bookmarks($userID = null) {
        if ($userID && is_integer_string($userID)) {
            $user = $this->repos->users->load($userID);
        } else {
            $user = $this->request->user;
        }

        $pageSize = $user->options('TorrentsPerPage', $this->settings->pagination->torrents);
        list($page, $limit) = page_limit($pageSize);
        list($orderWay, $orderBy) = $this->getCollageSort();

        $categories = $this->repos->collageCategories->find(null, null, 'sort', null, 'collages_categories');

        # Allow staff to see deleted collages
        if ($this->auth->isAllowed('collage_trash')) {
            $checkFlags = 0;
        } else {
            $checkFlags = Collage::TRASHED;
        }

        # This query is necessary due to the column sorting on the main collage index
        $collages = $this->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS
                    c.ID,
                    Max(ct.AddedOn) AS LastDate,
                    (SELECT COUNT(*)
                       FROM collages_subscriptions
                      WHERE CollageID = c.ID ) AS Subscribers
               FROM collages AS c
               LEFT JOIN collages_torrents AS ct ON ct.CollageID=c.ID
               INNER JOIN bookmarks_collages AS bc ON c.ID = bc.CollageID
              WHERE Flags & ? = '0'
                AND bc.UserID = ?
           GROUP BY c.ID
           ORDER BY {$orderBy} {$orderWay}
              LIMIT {$limit}",
            [$checkFlags, $user->ID]
        )->fetchAll(\PDO::FETCH_COLUMN);

        $results = $this->db->foundRows();

        foreach ($collages as &$collage) {
            $collage = $this->repos->collages->load($collage);
        }


        $params = [
            'page'        => $page,
            'pageSize'    => $pageSize,
            'categories'  => $categories,
            'collages'    => $collages,
        ];

        return new Rendered('@Collage/bookmarks.html.twig', $params);
    }

    private function availablePermissions() {
        $classLevels = $this->repos->permissions->getLevels();

        $minStaffLevel = $this->repos->permissions->getMinStaffLevel();
        $minUserLevel  = $this->repos->permissions->getMinUserLevel();

        # Filter available class levels (ugly legacy code)
        foreach ($classLevels as $idx => $classLevel) {
            # dont display staff levels
            if ($classLevel['Level'] >= $minStaffLevel) {
                unset($classLevels[$idx]);
            }

            # dont display gimp like levels
            if ($classLevel['Level'] < $minUserLevel) {
                unset($classLevels[$idx]);
            }

            # dont display non ranks (ie. FLS/group permissions)
            if ($classLevel['IsUserClass'] === "0") {
                unset($classLevels[$idx]);
            }
        }

        return $classLevels;
    }

    private function availableCategories() {
        $user = $this->request->user;

        $categories = $this->repos->collageCategories->find(null, null, 'sort', null, 'collages_categories', 'ID');

        # This is fugly and needs changed!
        $maxPersonalCollages = $this->auth->getUserPermissions($user)['MaxCollages'];

        # Filter categories available to the user
        foreach ($categories as $idx => $category) {
            if ($category->isPersonal()) {
                if (!$this->auth->isAllowed('collage_personal')) {
                    unset($categories[$idx]);
                }
                if ($user->personalCollageCount >= $maxPersonalCollages) {
                    unset($categories[$idx]);
                }
            }
            if ($category->MinClassCreate > $user->class->Level) {
                unset($categories[$idx]);
            }
            if ($category->isLocked()) {
                unset($categories[$idx]);
            }
        }

        return $categories;
    }

    public function createCollageForm() {
        $user = $this->request->user;

        $this->auth->checkAllowed('collage_create');

        $name             = $this->request->getRequestString('name');
        $selectedCategory = $this->request->getRequestInt('selectedCategory');
        $description      = $this->request->getRequestString('description');
        $tags             = $this->request->getRequestString('tags');
        $permission       = $this->request->getRequestInt('permission');
        $classLevels      = $this->availablePermissions();

        $minStaffLevel = $this->repos->permissions->getMinStaffLevel();
        $minUserLevel  = $this->repos->permissions->getMinUserLevel();

        # Filter pre-selected permission to ensure it exists
        if (!empty($permission)) {
            if ($permission > $minUserLevel && $permission < $minStaffLevel) {
                if (!array_key_exists($permission, $classLevels)) {
                    $permission = $minUserLevel;
                }
            }
        }

        $categories = $this->availableCategories();

        $imageWhitelist = $this->repos->imagehosts->find("Hidden='0'", null, 'Time DESC', null, 'imagehost_whitelist');
        $imageWhitelistUpdated = $this->db->rawQuery(
            "SELECT MAX(Time) FROM imagehost_whitelist"
        )->fetchColumn();

        $bscripts = ['bbcode', 'jquery'];
        $params = compact(
            'name',
            'selectedCategory',
            'description',
            'tags',
            'permission',
            'categories',
            'classLevels',
            'imageWhitelist',
            'imageWhitelistUpdated',
            'bscripts'
        );

        return new Rendered('@Collage/create.html.twig', $params);
    }

    public function createCollage() {
        $user = $this->request->user;

        $this->auth->checkAllowed('collage_create');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.create');

        $permission  = $this->request->getInt('permission');
        $classLevels = $this->availablePermissions();

        # Validate permission
        if (!($permission === 0)) {
            if (array_key_exists($permission, $classLevels) === false) {
                throw new UserError('Unknown editing permission');
            }
        }

        $this->collageValidate();

        # Process the tags
        $tagList  = $this->request->getPostString('tags');
        $tagList  = trim($tagList);

        # Break tag string down into individual tags
        $tags = preg_split('/( |,)/',  $tagList);
        foreach ($tags as $idx => &$tag) {
            $tag = trim($tag);
            if (empty($tag)) {
                unset($tags[$idx]);
                continue;
            }
            $tag = get_tag_synonym($tag);
        }
        $tagList = implode(' ', array_unique($tags));

        $collage = new Collage([
            'Name'             => $this->request->getPostString('name'),
            'Description'      => $this->request->getPostString('description'),
            'UserID'           => $user->ID,
            'Permissions'      => $permission,
            'CategoryID'       => $this->request->getPostInt('category'),
            'TagList'          => $tagList,
            'StartDate'        => new \DateTime,
        ]);
        $this->repos->collages->save($collage);

        write_log("Collage $collage->ID ({$collage->Name}) was created by {$user->Username}");

        $this->cache->deleteValue('collages_categories');

        return new Redirect("/collage/{$collage->ID}");
    }

    public function editCollageForm($collageID) {
        $collage = $this->repos->collages->load($collageID);
        if (!($collage instanceof Collage)) {
            throw new NotFoundError('This collage does not exist');
        }

        if (!($collage->canEdit() || $collage->canRename())) {
            return new ForbiddenError('You cannot edit this collage');
        }

        $categories = $this->repos->collageCategories->find(null, null, 'sort', null, 'collages_categories');
        $bscripts = ['bbcode', 'jquery'];

        $params = compact(
            'bscripts',
            'collage',
            'categories'
        );

        return new Rendered('@Collage/edit.html.twig', $params);
    }

    public function editCollage($collageID) {
        $user = $this->request->user;
        $collage = $this->repos->collages->load($collageID);
        if (!($collage instanceof Collage)) {
            throw new NotFoundError('This collage does not exist');
        }

        if (!($collage->canEdit() || $collage->canRename())) {
            return new ForbiddenError('You cannot edit this collage');
        }

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.edit');

        $this->collageValidate($collage);

        # Process the tags
        $tagList  = $this->request->getPostString('tags');
        $tagList  = trim($tagList);

        # Break tag string down into individual tags
        $tags = preg_split('/( |,)/',  $tagList);
        foreach ($tags as $idx => &$tag) {
            $tag = trim($tag);
            if (empty($tag)) {
                unset($tags[$idx]);
                continue;
            }
            $tag = get_tag_synonym($tag);
        }
        $tagList = implode(' ', array_unique($tags));

        if ($collage->canRename()) {
            $collage->Name        = $this->request->getPostString('name');
        }
        $collage->Description = $this->request->getPostString('description');
        $collage->TagList     = $tagList;

        if (!empty($this->request->getPostInt('category'))) {
            $collage->CategoryID  = $this->request->getPostInt('category');
        }

        if ($this->request->getPostBool('featured')) {
            $previous = $this->repos->collages->get('ID != ? AND UserID = ? AND Flags & ? != 0', [$collage->ID, $user->ID, Collage::FEATURED]);
            if ($previous instanceof Collage) {
                $previous->unsetFlags(Collage::FEATURED);
                $this->repos->collages->save($previous);
            }
        }

        $collage->unsetFlags(Collage::FEATURED);
        if ($collage->isPersonal()) {
            if ($this->request->getPostBool('featured')) {
                $collage->setFlags(Collage::FEATURED);
            }
        }

        if ($this->auth->isAllowed('collage_moderate')) {
            if ($this->request->getPostBool('locked')) {
                $collage->setFlags(Collage::LOCKED);
            } else {
                $collage->unsetFlags(Collage::LOCKED);
            }
            $collage->MaxGroups = $this->request->getPostInt('maxgroups');
            $collage->MaxGroupsPerUser = $this->request->getPostInt('maxgroupsperuser');
        }

        $this->repos->collages->save($collage);
        write_log("Collage {$collage->ID} ({$collage->Name}) was edited by {$user->Username} - edited details");

        return new Redirect("/collage/{$collage->ID}");
    }

    protected function collageValidate($collage = null) {
        $user = $this->request->user;

        $validate = new Validate;
        $bbCode   = new Text;

        $name = $this->request->getPostString('name');
        $description = $this->request->getPostString('description');
        $bbCode->validate_bbcode($description, get_permissions_advtags($user->ID));

        $checkName = true;
        $categoryID = $this->request->getPostInt('category');
        if ($collage instanceof Collage) {
            if (empty($categoryID)) {
                    $categoryID = $collage->CategoryID;
            }
            $checkName = $collage->canRename();
        }
        $categories = $this->availableCategories();

        if (array_key_exists($categoryID, $categories)) {
            $category = $categories[$categoryID];

            if ($checkName === true) {
                # Validate the "name" field
                $validate->SetFields(
                    'name',
                    true,
                    'string',
                    'The name must be between 3 and 100 characters',
                    ['maxlength'=>100, 'minlength'=>3]
                );
            }

            # Validate the "description" field
            $validate->SetFields(
                'description',
                true,
                'string',
                'he description must be at least 10 characters',
                ['maxlength'=>65535, 'minlength'=>10]
            );

            $errors = $validate->ValidateForm($this->request->post);

            if (empty($errors) === false) {
                throw new UserError($errors);
            }

            # Validate personal collage restrictions
            if ($category->isPersonal()) {
                if (!$this->auth->isAllowed('collage_personal')) {
                    throw new UserError("You are not allowed to create a personal collage");
                }
                if ($user->personalCollageCount >= $this->auth->getUserPermissions($user)['MaxCollages']) {
                    throw new UserError("You already have the maximum number of personal collages");
                }
                if ($this->auth->isAllowed('collage_renamepersonal')) {
                    if (!stristr($name, $user->Username)) {
                        throw new UserError("The title of your personal collage must include your username.");
                    }
                }
            }

            if ($collage instanceof Collage) {
                $collage = $this->repos->collages->get('Name = ? AND ID != ?', [$name, $collage->ID]);
            } else {
                $collage = $this->repos->collages->get('Name = ?', [$name]);
            }
            if ($collage instanceof Collage) {
                if ($collage->isTrashed()) {
                    throw new UserError('That collection already exists but needs to be recovered, please <a href="/staffpm.php">contact</a> the staff team!');
                } else {
                    throw new UserError("That collection already exists: <a href=\"/collage/{$collage->ID}\">{$collage->Name}</a>.");
                }
            }
        } else {
            throw new NotFoundError('This collage category does not exist');
        }
    }

    public function categoryManage() {
        $this->auth->checkAllowed('collage_admin');

        $categories = $this->repos->collageCategories->find(null, null, 'sort', null, 'collages_categories');

        $images = scandir($this->master->publicPath . '/static/common/collageicons', 0);
        $images = array_diff($images, ['.', '..']);

        $bscripts = ['jquery'];

        $params = [
            'images'     => $images,
            'classes'    => $this->repos->permissions->getClasses(),
            'categories' => $categories,
            'bscripts'   => $bscripts,
        ];

        return new Rendered('@Collage/category_manage.html.twig', $params);
    }

    public function recent() {
        $this->auth->checkAllowed('users_fls');

        $results = $this->db->rawQuery('SELECT COUNT(*) FROM collages_comments')->fetchColumn();
        $pageSize = $this->request->user->options('PostsPerPage', $this->settings->pagination->torrent_comments);
        list($page, $limit) = page_limit($pageSize);

        # We could implement catalog caching for the comment IDs, but it's not worth it
        $comments = $this->repos->collagecomments->find('', [], 'AddedTime DESC', $limit);

        $bscripts = ['comments', 'bbcode', 'jquery', 'jquery.cookie', 'jquery.modal', 'overlib'];
        $params = compact('bscripts', 'comments', 'page', 'results', 'pageSize');
        return new Rendered('@Collage/recent.html.twig', $params);
    }

    public function addTorrent($collageID) {
        $user = $this->request->user;

        $collage = $this->repos->collages->load($collageID);

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.add');

        if (!$collage instanceof Collage) {
            throw new NotFoundError('This collage does not exist');
        }

        if ($collage->isTrashed()) {
            throw new NotFoundError('This collage does not exist');
        }

        if (!$collage->canAdd()) {
            throw new UserError('You cannot add torrents to this collage');
        }

        $url = $this->request->getPostString('url');
        $urls = explode("\n", $this->request->getPostString('urls'));
        if (!empty($url)) {
            $urls = [$url];
        }

        # Yuk!
        $this->settings->setRegexConstants();
        $URLRegex = '/'.TORRENT_REGEX.'/i';

        $groupIDs = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) {
                continue;
            }

            //var_dump($URLRegex); die();

            $matches = [];
            if (preg_match($URLRegex, $url, $matches)) {
                $groupIDs[] = $matches[2];
                $groupID    = $matches[2];
            } else {
                throw new UserError("One of the entered URLs ({$url}) does not correspond to a torrent on the site.");
            }


            $group = $this->repos->torrentGroups->load($groupID);
            if (!($group instanceof TorrentGroup)) {
                throw new UserError("One of the entered URLs ({$url}) does not correspond to a torrent on the site.");
            }
        }

        foreach ($groupIDs as $groupID) {
            $collage->addTorrent($groupID);
        }

        write_log("Collage ".$collageID." (".$collage->Name.") was edited by {$user->Username} - added torrents ".implode(',', $groupIDs));

        return new Redirect("/collage/{$collage->ID}");
    }

    public function removeForm($collageID) {
        $user = $this->request->user;
        $collage = $this->repos->collages->load($collageID);

        if (!($collage instanceof Collage)) {
            throw new NotFoundError('This collage does not exist');
        }

        if ($this->auth->isAllowed('collage_trash') === false) {
            if ($collage->UserID === $user->ID) {
                if ($collage->userCount > 1) {
                    throw new UserError('Count You cannot trash a collage that other users have contributed torrents to');
                }

                # First get array of all contributors
                $userIDs = array_flip(array_column($collage->users, 'userID'));

                # Remove collage creator from array
                if (array_key_exists($collage->UserID, $userIDs)) {
                    unset($userIDs[$collage->UserID]);
                }

                # Check if array is empty
                if (!empty($userIDs)) {
                    throw new UserError('You cannot trash a collage that other users have contributed torrents to');
                }
            } else {
                throw new UserError('You cannot trash a collage you did not create');
            }
        }

        $bscripts = ['jquery', 'jquery.modal'];

        $params = compact('bscripts', 'collage');

        return new Rendered('@Collage/remove.html.twig', $params);
    }

    public function trash($collageID) {
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.

        try {
            $token = $this->request->getPostString('token');
            $this->secretary->checkToken($token, 'collage.trash');
            $status = $this->request->getPostBool('status');

            $user = $this->request->user;
            $collage = $this->repos->collages->load($collageID);

            if (!($collage instanceof Collage)) {
                throw new NotFoundError('This collage does not exist');
            }

            if ($this->auth->isAllowed('collage_trash') === false) {
                if ($collage->UserID === $user->ID) {
                    if ($collage->userCount > 1) {
                        throw new UserError('Count You cannot trash a collage that other users have contributed torrents to');
                    }

                    # First get array of all contributors
                    $userIDs = array_flip(array_column($collage->users, 'userID'));

                    # Remove collage creator from array
                    if (array_key_exists($collage->UserID, $userIDs)) {
                        unset($userIDs[$collage->UserID]);
                    }

                    # Check if array is empty
                    if (!empty($userIDs)) {
                        throw new UserError('You cannot trash a collage that other users have contributed torrents to');
                    }
                } else {
                    throw new UserError('You cannot trash a collage you did not create');
                }
            }

            $reason = trim($this->request->getPostString('reason'));
            if (empty($reason)) {
                throw new UserError('You must enter a reason');
            }

            $action = '';
            if ($status === true) {
                $collage->setFlags(Collage::TRASHED);
                $action = 'trashed';
            } else {
                $collage->unsetFlags(Collage::TRASHED);
                $action = 'restored';
            }

            $this->repos->collages->save($collage);
            write_log("Collage {$collage->ID} ({$collage->Name}) was {$action} by {$user->Username}: {$reason}");

            $params = compact('collage');
            return new Rendered('@Collage/snippets/collage_table.html.twig', $params, 200, 'collage_table_row');
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function delete($collageID) {
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.

        try {
            $token = $this->request->getPostString('token');
            $this->secretary->checkToken($token, 'collage.delete');

            $this->auth->checkAllowed('collage_delete');

            $user = $this->request->user;
            $collage = $this->repos->collages->load($collageID);

            if (!($collage instanceof Collage)) {
                throw new NotFoundError('This collage does not exist');
            }

            $reason = trim($this->request->getPostString('reason'));
            if (empty($reason)) {
                throw new UserError('You must enter a reason');
            }

            $torrents = $this->repos->collageTorrents->find('CollageID = ?', [$collage->ID]);
            foreach ($torrents as $torrent) {
                $this->repos->collageTorrents->delete($torrent);
            }

            $comments = $this->repos->collageComments->find('CollageID = ?', [$collage->ID]);
            foreach ($comments as $comment) {
                $this->repos->collageComments->delete($comment);
            }

            write_log("Collage {$collage->ID} ({$collage->Name}) was deleted by {$user->Username}: {$reason}");
            $this->repos->collages->delete($collage);

            return new JSON([$collageID]);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function comment($collageID) {
        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.comment');

        $user = $this->request->user;

        $collage = $this->repos->collages->load($collageID);

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::POST);

        if (!$collage instanceof Collage) {
            throw new NotFoundError('This collage does not exist');
        }

        if ($collage->isTrashed()) {
            throw new NotFoundError('This collage does not exist');
        }

        $body = $this->request->getPostString('body');
        $timestamp = new \DateTime;

        # If you're not sending anything, go back
        if (empty($body)) {
            throw new UserError('You cannot post a comment with no content');
        }

        # Work-around for references to master inside the legacy Text class
        $master = $this->master;
        $bbCode = new Text;
        $bbCode->validate_bbcode($body, get_permissions_advtags($user->ID));

        flood_check('collages_comments');

        $comment = new CollageComment([
            'CollageID' => $collage->ID,
            'AuthorID'  => $user->ID,
            'AddedTime' => $timestamp,
            'Body'      => $body,
        ]);
        $master->repos->collagecomments->save($comment);
        $postID = $comment->ID;

        return new Redirect("/collage/{$collage->ID}?postid={$comment->ID}#post{$comment->ID}");
    }

    public function levelAssign($collageID) {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.level.assign');

        $collage = $this->repos->collages->load($collageID);
        if (!($collage instanceof Collage)) {
            throw new NotFoundError('This collage does not exist');
        }

        if (!($collage->UserID === $user->ID)) {
            $this->auth->checkAllowed('collage_moderate');
        }

        $permission  = $this->request->getInt('permission');

        $classLevels = $this->availablePermissions();

        # Validate permission
        if (!($permission === 0)) {
            if (array_key_exists($permission, $classLevels) === false) {
                throw new UserError('Unknown editing permission');
            }
        }

        $collage->Permissions = $permission;
        $this->repos->collages->save($collage);

        return new Redirect("/collage/{$collage->ID}");
    }

    public function groupsAssign($collageID) {
        $this->auth->checkAllowed('collage_assign_groups');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.groups.assign');

        $collage = $this->repos->collages->load($collageID);
        if (!($collage instanceof Collage)) {
            throw new NotFoundError('This collage does not exist');
        }

        $newGroups = $this->request->getPostArray('groups');
        $oldGroups = array_keys($collage->groups);

        $insert = array_diff($newGroups, $oldGroups);
        $remove = array_diff($oldGroups, $newGroups);

        $collage->addGroupAccess($insert);
        $collage->deleteGroupsAccess($remove);

        return new Redirect("/collage/{$collage->ID}");
    }

    public function categoryCreate() {
        $this->auth->checkAllowed('collage_admin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.category.create');

        $this->categoryValidate();

        $category = new CollageCategory([
            'Image'          => $this->request->getPostString('image'),
            'Sort'           => $this->request->getPostInt('sort'),
            'Name'           => $this->request->getPostString('name'),
            'Description'    => $this->request->getPostString('description'),
            'MinClassView'   => $this->request->getPostInt('minclassview'),
            'MinClassCreate' => $this->request->getPostInt('minclasscreate'),
        ]);

        $flags = 0;
        if ($this->request->getPostBool('locked')) {
            $flags |= CollageCategory::LOCKED;
        }
        if ($this->request->getPostBool('personal')) {
            $flags |= CollageCategory::PERSONAL;
        }
        $category->Flags = $flags;

        $this->repos->collageCategories->save($category);

        $this->cache->deleteValue('collages_categories');

        return new Redirect("/collage/category/manage");
    }

    public function categoryEdit() {
        $this->auth->checkAllowed('collage_admin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.category.edit');

        $id = $this->request->getPostInt('id');
        if (is_integer_string($id)) {
            $category = $this->repos->collageCategories->load($id);
            if ($category instanceof CollageCategory) {
                $this->categoryValidate();

                $category->Image          = $this->request->getPostString('image');
                $category->Sort           = $this->request->getPostInt('sort');
                $category->Name           = $this->request->getPostString('name');
                $category->Description    = $this->request->getPostString('description');
                $category->MinClassView   = $this->request->getPostInt('minclassview');
                $category->MinClassCreate = $this->request->getPostInt('minclasscreate');

                $flags = 0;
                if ($this->request->getPostBool('locked')) {
                    $flags |= CollageCategory::LOCKED;
                }
                if ($this->request->getPostBool('personal')) {
                    $flags |= CollageCategory::PERSONAL;
                }
                $category->Flags = $flags;

                $this->repos->collageCategories->save($category);
                $this->cache->deleteValue('collages_categories');

                return new Redirect("/collage/category/manage");
            } else {
                throw new NotFoundError('This collage category does not exist');
            }
        }

        throw new UserError('Malformed POST submission');
    }

    public function categoryDelete() {
        $this->auth->checkAllowed('collage_admin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'collage.category.delete');

        $id = $this->request->getPostInt('id');
        if (is_integer_string($id)) {
            $category = $this->repos->collageCategories->load($id);
            if ($category instanceof CollageCategory) {
                $this->repos->collageCategories->delete($category);
                $this->cache->deleteValue('collages_categories');

                return new Redirect("/collage/category/manage");
            } else {
                throw new NotFoundError('This collage category does not exist');
            }
        }

        throw new UserError('Malformed POST submission');
    }

    protected function categoryValidate() {
        $validate = new Validate;

        # Validate the "image" field
        $validate->SetFields(
            'image',
            true,
            'string',
            'The image must be set',
            ['maxlength'=>255, 'minlength'=>1]
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
            'The name must be set, and has a max length of 64 characters',
            ['maxlength'=>64, 'minlength'=>1]
        );

        # Validate the "description" field
        $validate->SetFields(
            'description',
            true,
            'string',
            'The description must be set, and has a max length of 128 characters',
            ['maxlength'=>128, 'minlength'=>1]
        );

        # Validate the "minclassview" field
        $validate->SetFields(
            'minclassview',
            true,
            'number',
            'MinClassView must be set'
        );

        # Validate the "minclasscreate" field
        $validate->SetFields(
            'minclasscreate',
            true,
            'number',
            'MinClassCreate must be set'
        );

        # Validate the "open" field
        $validate->SetFields(
            'locked',
            true,
            'inarray',
            'The open field has invalid input',
            ['inarray' => [0,1]]
        );

        # Validate the "personal" field
        $validate->SetFields(
            'personal',
            true,
            'inarray',
            'The personal field has invalid input',
            ['inarray' => [0,1]]
        );

        $errors = $validate->ValidateForm($this->request->post);

        if (empty($errors) === false) {
            throw new UserError($errors);
        }
    }

    public function postRemoveForm($postID) {
        $this->ajaxPostValidate($postID, 'collage_post_trash', 'trash');
        $params = ['post' => $this->repos->collageComments->load($postID)];
        return new Rendered('@Collage/post_remove.html.twig', $params);
    }

    protected function ajaxPostValidate($postID, $permission = null, $action = "edit") {
        # Do user permission and post validation common to all AJAX post requests.
        try {
            if (!is_integer_string($postID)) {
                throw new NotFoundError('This post does not exist');
            }

            if (!empty($permission)) {
                $this->auth->checkAllowed($permission);
            }

            if (!in_array($action, ["read", "trash"])) {
                $token = $this->request->getPostString('token');
                $this->secretary->checkToken($token, "post.{$action}");
            }

            $user = $this->request->user;
            $post = $this->repos->collageComments->load($postID);

            if ($action === "edit") {
                $this->repos->restrictions->checkRestricted($user, Restriction::POST);
            }

            if (!($post instanceof CollageComment)) {
                throw new NotFoundError('This post does not exist');
            }

            if (!($post->collage instanceof Collage)) {
                throw new NotFoundError('This post has been orphaned');
            }
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postTrash($postID) {
        $this->ajaxPostValidate($postID, 'collage_post_trash', 'trash');
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.

        try {
            $user = $this->request->user;
            $post = $this->repos->collageComments->load($postID);

            $token = $this->request->getPostString('token');
            $this->secretary->checkToken($token, 'post.trash');
            $status = $this->request->getPostBool('status');

            if ($status === true) {
                $post->setFlags(CollageComment::TRASHED);
                $action = 'trashed';
            } else {
                $post->unsetFlags(CollageComment::TRASHED);
                $action = 'restored';
            }
            $this->repos->collageComments->save($post);

            $sslurl = $this->settings->main->ssl_site_url;
            $this->irker->announcelab('Collage comment '.$postID.' has been '.$action.' in collage https://'.$sslurl.'/collage/'.$post->CollageID.'?postid='.$post->ID.'#post'.$post->ID.' by '.$this->request->user->Username);

            return new Response($this->render->post('collage comment', $post), 200);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postDelete($postID) {
        $this->ajaxPostValidate($postID, 'collage_post_delete', 'delete');
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.

        try {
            $user = $this->request->user;
            $post = $this->repos->collageComments->load($postID);

            # Delete the post
            $this->repos->collageComments->delete($post);

            $sslurl = $this->settings->main->ssl_site_url;
            $this->irker->announcelab('Collage comment '.$postID.' has been deleted in collage https://'.$sslurl.'/collage/'.$post->CollageID.'?postid='.$post->ID.'#post'.$post->ID.' by '.$this->request->user->Username);

            return new JSON([$postID]);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postGetForm($postID) {
        $this->ajaxPostValidate($postID, null, 'read');
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.
        try {
            $user = $this->request->user;
            $post = $this->repos->collageComments->load($postID);

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
            $post = $this->repos->collageComments->load($postID);

            # carry over from legacy
            $this->auth->checkAllowed('collage_moderate');

            $depth = $this->request->getGetInt('depth');
            $edits = $this->repos->commentEdits->find(
                'PostID = ? and Page = ?',
                [$post->ID, 'collages'],
                'EditTime DESC',
                null,
                "collage_edits_{$post->ID}"
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
                'section'  => 'collage comment',
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
            $post = $this->repos->collageComments->load($postID);

            $body = $this->request->getPostString('body');

            validate_edit_comment($post->AuthorID, $post->EditedUserID, $post->AddedTime, $post->EditedTime, $post->Flags);

            # Work-around for references to master inside the legacy Text class
            $master = $this->master;
            $bbCode = new Text;
            $bbCode->validate_bbcode($body, get_permissions_advtags($user->ID));

            # Perform the update
            if (!($user->ID === $post->AuthorID)) {
                $post->Flags |= CollageComment::EDITLOCKED;
            }

            $timestamp = new \DateTime;

            $edit = new CommentEdit([
                'Page'      => 'collages',
                'PostID'    => $post->ID,
                'EditUser'  => $user->ID,
                'EditTime'  => $timestamp,
                'Body'      => $post->Body,
            ]);
            $this->repos->commentEdits->save($edit);

            $post->EditedUserID = $user->ID;
            $post->Body = $body;
            $post->EditedTime = $timestamp;
            $this->repos->collageComments->save($post);

            if (!($user->ID === $post->AuthorID)) {
                $url = sprintf('/collage/%d?postid=%d#post%d', $post->CollageID, $post->ID, $post->ID);
                notify_staff_edit($post->AuthorID, $url);
            }

            # Remove once all edits are done via Luminance
            $this->cache->deleteValue("collage_edits_{$post->ID}");
            $result = 'saved';

            $html = $this->render->post('collage comment', $post);


            return new JSON([$result, $html]);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function postRevert($postID) {
        $this->ajaxPostValidate($postID, 'collage_post_restore', 'revert');
        # This should only be called via AJAX, so catch any exceptions, set them
        # to return JSON and rethrow them.
        try {
            $user = $this->request->user;
            $post = $this->repos->collageComments->load($postID);

            $edits = $this->repos->commentEdits->find(
                'Page = ? AND PostID = ?',
                ['collages', $postID],
                'EditTime DESC',
                null,
                "collage_edits_{$post->ID}"
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
            $this->repos->collageComments->save($post);

            $this->repos->commentEdits->delete($edits[0]);
            $this->cache->deleteValue("collage_edits_$postID");

            $html = $this->render->post('collage comment', $post);

            return new JSON($html);
        } catch (Error $e) {
            $e->returnJSON(true);
            throw $e;
        }
    }

    public function editLock($postID) {
        if (!is_integer_string($postID)) {
            throw new NotFoundError('This post does not exist');
        }

        $this->auth->checkAllowed('collage_post_lock');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'post.editlock');
        $status = $this->request->getPostBool('status');

        $post = $this->repos->collageComments->load($postID);
        if ($status === true) {
            $post->setFlags(CollageComment::EDITLOCKED);
        } else {
            $post->unsetFlags(CollageComment::EDITLOCKED);
        }
        $this->repos->collageComments->save($post);

        return new Response($this->render->post('collage comment', $post), 200);
    }

    public function timeLock($postID) {
        if (!is_integer_string($postID)) {
            throw new NotFoundError('This post does not exist');
        }

        $this->auth->checkAllowed('collage_post_lock');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'post.timelock');
        $status = $this->request->getPostBool('status');

        $post = $this->repos->collageComments->load($postID);
        if ($status === true) {
            $post->setFlags(CollageComment::TIMELOCKED);
        } else {
            $post->unsetFlags(CollageComment::TIMELOCKED);
        }
        $this->repos->collageComments->save($post);

        return new Response($this->render->post('collage comment', $post), 200);
    }

    public function postPin($postID) {
        if (!is_integer_string($postID)) {
            throw new NotFoundError('This post does not exist');
        }

        $this->auth->checkAllowed('collage_post_pin');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'post.pin');
        $status = $this->request->getPostBool('status');

        $post = $this->repos->collageComments->load($postID);
        if ($status === true) {
            $post->setFlags(CollageComment::PINNED);
        } else {
            $post->unsetFlags(CollageComment::PINNED);
        }
        $this->repos->collageComments->save($post);

        return new Response($this->render->post('collage comment', $post), 200);
    }
}
