<?php
namespace Luminance\Plugins\Articles;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Services\Auth;

use Luminance\Errors\InputError;
use Luminance\Errors\NotFoundError;
use Luminance\Errors\ForbiddenError;

use Luminance\Entities\Article;
use Luminance\Entities\CommentEdit;

use Luminance\Responses\Redirect;
use Luminance\Responses\Rendered;

use Luminance\Legacy\Text;

class ArticlesPlugin extends Plugin {

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  '*',       Auth::AUTH_LOGIN,  'article' ],
        [ 'GET',  'view/*',  Auth::AUTH_LOGIN,  'article' ],
        [ 'GET',  'search',  Auth::AUTH_LOGIN,  'search'  ],
        [ 'GET',  'manage',  Auth::AUTH_LOGIN,  'manage'  ],
        [ 'GET',  'edit/*',  Auth::AUTH_LOGIN,  'manage'  ],

        [ 'POST', 'create',  Auth::AUTH_LOGIN,  'create'  ],
        [ 'POST', 'edit',    Auth::AUTH_LOGIN,  'edit'    ],
        [ 'POST', 'delete',  Auth::AUTH_LOGIN,  'delete'  ],
    ];

    protected static $useServices = [
        'auth'      => 'Auth',
        'db'        => 'DB',
        'cache'     => 'Cache',
        'render'    => 'Render',
        'secretary' => 'Secretary',
        'irker'     => 'Irker',
        'repos'     => 'Repos',
    ];

    protected static $userinfoTools = [
        [
            'admin_edit_articles',       # permission
            'articles/manage',           # action
            'Articles'                   # title
        ],
    ];

    private static $articleCategories = [
        'Rules',
        'Help',
        'Hidden'
    ];

    private static $articleSubCategories = [
        'Intro',
        'Other',
        'Rules',
        'Torrenting',
        'IRC',
        'Uploading',
        'Site',
        'Bitcoin Guides',
        'Staff'
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ '*', 'articles/**', Auth::AUTH_LOGIN, 'plugin', 'Articles' ]);
    }

    private function getAllArticles() {
        return $this->repos->articles->find(
            null,                      # Find query
            null,                      # Query parameters
            'Category, SubCat, Title', # Result ordering
            null,                      # Result limit
            "all_articles"             # Auto-cache key
        );
    }

    public function article($topic = null) {
        $user = $this->request->user;

        $staffLevel = 0;
        $minStaffLevel = $this->repos->permissions->getMinStaffLevel();
        if ($user->class->Level >= $minStaffLevel) { // only interested in staff classes
            $staffLevel = $user->class->Level;
        } elseif ($user->legacy['SupportFor']) {
            $staffLevel = $minStaffLevel;
        }

        if (empty($topic)) {
            throw new ForbiddenError;
        }

        $article = $this->repos->articles->get('TopicID = ?', [$topic]);

        if (!($article instanceof Article)) {
            throw new NotFoundError();
        }

        if ($article->MinClass > 0) {
            # should there be a way for FLS to see these... perm setting maybe?
            if ($staffLevel < $article->MinClass) {
                throw new ForbiddenError;
            }
        }

        $articles = $this->repos->articles->find(
            'Category = ?',                   # Find query
            [$article->Category],             # Query parameters
            'SubCat, Title',                  # Result ordering
            null,                             # Result limit
            "articles_{$article->Category}"   # Auto-cache key
        );

        $topArticles = $this->repos->articles->find(
            'Category = ? AND SubCat = ?',                         # Find query
            [$article->Category, $article->SubCat],                # Query parameters
            'SubCat, Title',                                       # Result ordering
            null,                                                  # Result limit
            "articles_sub_{$article->Category}_{$article->SubCat}" # Auto-cache key
        );

        $params = [
            'bscripts'    => ['overlib', 'bbcode'],
            'article'     => $article,
            'articles'    => $articles,
            'topArticles' => $topArticles,
            'staffClass'  => $staffLevel,
            'permissions' => $this->repos->permissions,
            'articleCats' => self::$articleCategories,
            'subCats'     => self::$articleSubCategories,
        ];

        return new Rendered('@Articles/article.html.twig', $params);
    }

    public function search() {
        $searchTerms = trim($this->request->getGetString('searchterms'));

        $user = $this->request->user;
        $staffLevel = 0;
        $minStaffLevel = $this->repos->permissions->getMinStaffLevel();
        if ($user->class->Level >= $minStaffLevel) { // only interested in staff classes
            $staffLevel = $user->class->Level;
        } elseif ($user->legacy['SupportFor']) {
            $staffLevel = $minStaffLevel;
        }

        $articles = $this->db->rawQuery(
            "SELECT TopicID,
                    Title,
                    Description,
                    Category,
                    SubCat,
                    MinClass
               FROM articles
              WHERE Category != '2'
                AND MinClass <= ?
                AND MATCH (Title,Description,Body) AGAINST (? IN BOOLEAN MODE)",
            [$staffLevel, $searchTerms]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $params = [
            'bscripts'    => ['overlib', 'bbcode'],
            'articles'    => $articles,
            'articleCats' => self::$articleCategories,
            'subCats'     => self::$articleSubCategories,
            'searchterms' => $searchTerms,
        ];
        return new Rendered('@Articles/search_results.html.twig', $params);
    }

    public function manage($ID = null) {
        $this->auth->checkAllowed('admin_edit_articles');

        $user = $this->request->user;

        $staffLevel = 0;
        $minStaffLevel = $this->repos->permissions->getMinStaffLevel();
        if ($user->class->Level >= $minStaffLevel) { // only interested in staff classes
            $staffLevel = $user->class->Level;
        } elseif ($user->legacy['SupportFor']) {
            $staffLevel = $minStaffLevel;
        }

        $articles = $this->getAllArticles();

        $article = null;
        if (!empty($ID)) {
            $article = $this->repos->articles->load($ID);

            if (!($article instanceof Article)) {
                throw new NotFoundError();
            }
        }

        $params = [
            'bscripts'    => ['overlib', 'bbcode'],
            'article'     => $article,
            'articles'    => $articles,
            'staffClass'  => $staffLevel,
            'articleCats' => self::$articleCategories,
            'subCats'     => self::$articleSubCategories,
        ];
        return new Rendered('@Articles/articles_manager.html.twig', $params);
    }

    public function create() {
        $this->auth->checkAllowed('admin_edit_articles');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'article.create', 900);

        $topicID = strtolower($this->request->getPostString('topicid'));

        if (empty($topicID)) {
            throw new InputError('You must enter a topicid for this article');
        }

        if (!preg_match('/^[a-z0-9\-\_.()\@&]+$/', $topicID)) {
            throw new InputError("Invalid characters in topicID ({$topicID}); allowed: a-z 0-9 -_.()@&");
        }

        $count = $this->db->rawQuery(
            "SELECT Count(*) as c FROM articles WHERE TopicID=?",
            [$topicID]
        )->fetchColumn();
        if ($count > 0) {
            throw new InputError('The topic ID must be unique for the article');
        }

        # Validate input first
        $category = $this->request->getPostInt('category');
        $subCat = $this->request->getPostInt('subcat');
        $title = $this->request->getPostString('title');
        $description = $this->request->getPostString('description');
        $body = $this->request->getPostString('body');
        $level = $this->request->getPostInt('level');


        if (!in_array($category, array_keys(self::$articleCategories))) {
            throw new InputError('Bad category selecton');
        }

        if (!in_array($subCat, array_keys(self::$articleSubCategories))) {
            throw new InputError('Bad sub-category selecton');
        }

        if (!in_array($level, array_keys($this->repos->permissions->getLevels()))) {
            # 0 means anyone
            if (!($level === 0)) {
                throw new InputError('Bad permission level selecton');
            }
        }

        if (empty($title)) {
            throw new InputError('Title must not be empty');
        }

        if (empty($description)) {
            throw new InputError('Description must not be empty');
        }

        # check the article body
        # Work-around for references to master inside the legacy Text class
        $master = $this->master;
        $bbCode = new Text;
        $bbCode->validate_bbcode($body, true);

        $article = new Article();

        $article->Category = $category;
        $article->SubCat = $subCat;
        $article->TopicID = $topicID;
        $article->Title = $title;
        $article->Description = $description;
        $article->Body = $body;
        $article->MinClass = $level;
        $article->Time = sqltime();
        $this->repos->articles->save($article);

        $master = $this->master;
        $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $sslurl = $master->settings->main->ssl_site_url;
        $activeUser = $master->auth->getLegacyLoggedUser();
        $message = ("User ".$activeUser['Username']." https://".$sslurl."/user.php?id=".$activeUser['ID']." created an article.");
        $message .= ("\nArticle ID: " . $article->TopicID . " Article Title: " . $article->Title . " Description: " . $article->Description);
        $master->irker->announceArticle($message);

        return new Redirect("/articles/manage");
    }

    public function edit() {
        $this->auth->checkAllowed('admin_edit_articles');

        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'article.edit', 900);

        $articleID = $this->request->getPostString('id');
        $article = $this->repos->articles->load($articleID);

        if ($article instanceof Article) {
            $topicID = strtolower($this->request->getPostString('topicid'));

            if (empty($topicID)) {
                throw new InputError('You must enter a topicid for this article');
            }

            if (!preg_match('/^[a-z0-9\-\_.()\@&]+$/', $topicID)) {
                throw new InputError("Invalid characters in topicID ({$topicID}); allowed: a-z 0-9 -_.()@&");
            }

            $count = $this->db->rawQuery(
                "SELECT Count(*) as c FROM articles WHERE TopicID=? AND ID<>?",
                [$topicID, $article->ID]
            )->fetchColumn();
            if ($count > 0) {
                throw new InputError('The topic ID must be unique for the article');
            }

            # Validate input first
            $category = $this->request->getPostInt('category');
            $subCat = $this->request->getPostInt('subcat');
            $title = $this->request->getPostString('title');
            $description = $this->request->getPostString('description');
            $body = $this->request->getPostString('body');
            $level = $this->request->getPostInt('level');


            if (!in_array($category, array_keys(self::$articleCategories))) {
                throw new InputError('Bad category selecton');
            }

            if (!in_array($subCat, array_keys(self::$articleSubCategories))) {
                throw new InputError('Bad sub-category selecton');
            }

            if (!in_array($level, array_keys($this->repos->permissions->getLevels()))) {
                # 0 means anyone
                if (!($level === 0)) {
                    throw new InputError('Bad permission level selecton');
                }
            }

            if (empty($title)) {
                throw new InputError('Title must not be empty');
            }

            if (empty($description)) {
                throw new InputError('Description must not be empty');
            }

            # check the article body
            # Work-around for references to master inside the legacy Text class
            $master = $this->master;
            $bbCode = new Text;
            $bbCode->validate_bbcode($body, true);

            $edit = new CommentEdit;
            $edit->Page = 'articles';
            $edit->PostID = $article->ID;
            $edit->EditUser = $user->ID;
            $edit->EditTime = sqltime();
            $edit->Body = $article->Body;
            $this->repos->commentEdits->save($edit);

            $article->Category = $category;
            $article->SubCat = $subCat;
            $article->TopicID = $topicID;
            $article->Title = $title;
            $article->Description = $description;
            $article->Body = $body;
            $article->MinClass = $level;
            $article->Time = sqltime();
            $this->repos->articles->save($article);
        }
        $master = $this->master;
        $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $sslurl = $master->settings->main->ssl_site_url;
        $activeUser = $master->auth->getLegacyLoggedUser();
        $message = ($activeUser['Username']." https://".$sslurl."/user.php?id=".$activeUser['ID']." edited an article. ");
        $message .= ("Article Name: " . $article->TopicID . " Article ID: " . $article->ID);
        $master->irker->announceArticle($message);
        return new Redirect("/articles/manage");
    }

    public function delete() {
        $this->auth->checkAllowed('admin_delete_articles');

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'article.delete', 900);

        $articleID = $this->request->getPostString('id');
        $article = $this->repos->articles->load($articleID);
        if ($article instanceof Article) {
            $this->repos->articles->delete($article);
        }

        $master = $this->master;
        $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $sslurl = $master->settings->main->ssl_site_url;
        $activeUser = $master->auth->getLegacyLoggedUser();
        $message = ($activeUser['Username']." https://".$sslurl."/user.php?id=".$activeUser['ID']." deleted an article. ");
        $message .= ("Article Name: " . $article->TopicID . " Article ID: " . $article->ID);
        $message .= (" Description: " . $article->description);
        $master->irker->announceArticle($message);
        $subject = ("Article " . $article->ID ." was deleted!");
        $msgStaff = ("[b]Article ID:[/b] " . $article->ID);
        $msgStaff .= ("\n[b]Article Name:[/b] " . $article->TopicID . "\n[b]Article Title:[/b] " . $article->Title . "\n[b]Description:[/b] " . $article->Description . "\n[b]Body:[/b] \n");
        $msgStaff .= ($article->Body);
        $staffClass = $this->repos->permissions->getMinClassPermission('users_mod');
        send_staff_pm($subject, $msgStaff, $staffClass->Level);
        return new Redirect("/articles/manage");
    }

    public function replaceSpecialTags($body) {
        # client blacklist
        if (preg_match("/\[clientlist\]/i", $body)) {
            $blacklistedClients = $this->repos->clientBlacklists->find(null, null, 'vstring ASC', null, 'blacklisted_clients');
            $params = [
                'blacklistedClients' => $blacklistedClients,
            ];
            $list = $this->render->template('@Articles/snippets.html.twig', $params, 'clientlist');
            $body = preg_replace("/\[clientlist\]/i", $list, $body);
        }

        // imagehost whitelist
        if (preg_match("/\[whitelist\]/i", $body)) {
            $imageWhitelist = $this->repos->imagehosts->find("Hidden='0'", null, 'Time DESC', null, 'imagehost_whitelist');
            $params = [
                'imageWhitelist' => $imageWhitelist,
            ];
            $list = $this->render->template('@Articles/snippets.html.twig', $params, 'whitelist');
            $body = preg_replace("/\[whitelist\]/i", $list, $body);
        }

        // DNU list
        if (preg_match("/\[dnulist\]/i", $body)) {
            $forbiddenContentList = $this->repos->forbiddenContents->find(null, null, 'Time', null, 'do_not_upload_list');
            $params = [
                'forbiddenContentList' => $forbiddenContentList,
            ];
            $list = $this->render->template('@Articles/snippets.html.twig', $params, 'dnulist');
            $body = preg_replace("/\[dnulist\]/i", $list, $body);
        }

        return $body;
    }
}
