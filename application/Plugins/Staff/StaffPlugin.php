<?php
namespace Luminance\Plugins\Staff;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Errors\UserError;

use Luminance\Entities\Restriction;

use Luminance\Services\Auth;

use Luminance\Responses\Rendered;
use Luminance\Responses\Redirect;

class StaffPlugin extends Plugin {

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  '*',               Auth::AUTH_LOGIN,  'staff'         ],
        [ 'GET',  'blog',            Auth::AUTH_2FA,    'blog'          ],
        [ 'POST', 'blog/new',        Auth::AUTH_2FA,    'blogNew'       ],
        [ 'POST', 'blog/*/edit',     Auth::AUTH_2FA,    'blogEdit'      ],
        [ 'GET',  'blog/*/edit',     Auth::AUTH_2FA,    'blogEditForm'  ],
        [ 'POST', 'blog/*/delete',   Auth::AUTH_2FA,    'blogDelete'    ],
        [ 'GET',  'restrictions',    Auth::AUTH_2FA,    'restrictions'  ],
    ];

    protected static $useServices = [
        'auth'      => 'Auth',
        'db'        => 'DB',
        'cache'     => 'Cache',
        'options'   => 'Options',
        'settings'  => 'Settings',
        'secretary' => 'Secretary',
        'repos'     => 'Repos',
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ '*', 'staff/**', Auth::AUTH_LOGIN, 'plugin', 'Staff' ]);
    }

    public function getFLS() {
        if (($fls = $this->cache->getValue('fls')) === false) {
            $fls = $this->db->rawQuery(
                "SELECT u.ID
                   FROM users AS u
                   JOIN users_main AS um ON u.ID = um.ID
                   JOIN users_info AS ui ON u.ID = ui.UserID
                   JOIN permissions AS p ON p.ID = um.PermissionID
                  WHERE p.DisplayStaff != '1'
                    AND ui.SupportFor != ''
                    AND um.GroupPermissionID != 0
               ORDER BY um.LastAccess ASC"
            )->fetchAll(\PDO::FETCH_COLUMN);
            $this->cache->cacheValue('fls', $fls, 180);
        }
        foreach ($fls as &$user) {
            $user = $this->repos->users->load($user);
        }

        return $fls;
    }

    public function getStaff() {
        if (($staff = $this->cache->getValue('staff')) === false) {
            $staff = $this->db->rawQuery(
                "SELECT u.ID
                   FROM users AS u
                   JOIN users_main AS um ON u.ID = um.ID
                   JOIN permissions AS p ON p.ID = um.PermissionID
                  WHERE p.DisplayStaff = '1'
                    AND p.Level >= ?
                    AND p.Level < ?
               ORDER BY p.Level, um.LastAccess ASC",
                [$this->repos->permissions->getMinStaffLevel(), $this->settings->users->level_admin]
            )->fetchAll(\PDO::FETCH_COLUMN);
            $this->cache->cacheValue('staff', $staff, 180);
        }
        foreach ($staff as &$user) {
            $user = $this->repos->users->load($user);
        }

        return $staff;
    }

    public function getAdmins() {
        if (($admins = $this->cache->getValue('admins')) === false) {
            $admins = $this->db->rawQuery(
                "SELECT u.ID
                   FROM users AS u
                   JOIN users_main AS um ON u.ID = um.ID
                   JOIN permissions AS p ON p.ID = um.PermissionID
                  WHERE p.DisplayStaff='1'
                    AND p.Level >= ?
               ORDER BY p.Level, um.LastAccess ASC",
                [$this->settings->users->level_admin]
            )->fetchAll(\PDO::FETCH_COLUMN);
            $this->cache->cacheValue('admins', $admins, 180);
        }
        foreach ($admins as &$user) {
            $user = $this->repos->users->load($user);
        }

        return $admins;
    }

    public function getSupport() {
        return array(
            $this->getFLS(),
            $this->getStaff(),
            $this->getAdmins()
        );
    }

    public function staff() {
        $user = $this->request->user;

        $this->auth->checkAllowed('site_staff_page');

        # Will throw an exception if user is restricted
        $this->repos->restrictions->checkRestricted($user, Restriction::STAFFPM);

        $bscripts = ['bbcode', 'inbox', 'jquery'];
        $fls = $this->getFLS();
        $staff = $this->getStaff();
        $admins = $this->getAdmins();

        $show = !$this->request->getBool('show');
        $assign = $this->request->getString('assign');
        if (!($assign === '') && !in_array($assign, ['mod','smod','admin'])) {
            $assign = '';
        }
        $subject = isset($_REQUEST['sub'])?$_REQUEST['sub']:'';
        $message = isset($_REQUEST['msg'])?$_REQUEST['msg']:'';

        $params = compact(
            'bscripts',
            'fls',
            'staff',
            'admins',
            'show',
            'assign',
            'subject',
            'message',
        );
        return new Rendered('@Staff/staff.html.twig', $params);
    }

    public function blog() {
        $user = $this->request->user;

        $this->auth->checkAllowed('users_mod');

        $blogPosts = $this->db->rawQuery(
            'SELECT b.ID,
                    b.UserID,
                    b.Title,
                    b.Body,
                    b.Time
               FROM staff_blog AS b
           ORDER BY Time DESC
              LIMIT 20'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $this->db->rawQuery(
            'INSERT INTO staff_blog_visits (UserID, Time)
                  VALUES (?, NOW())
                      ON DUPLICATE KEY
                  UPDATE Time=NOW()',
            [$user->ID]
        );

        $this->cache->deleteValue('staff_blog_read_'.$user->ID);

        $bscripts = ['bbcode', 'inbox', 'jquery'];

        $params = compact(
            'bscripts',
            'blogPosts',
        );

        return new Rendered('@Staff/blog.html.twig', $params);
    }

    public function blogNew() {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'staff.blog.new');

        $body = $this->request->getPostString('body');
        $title = $this->request->getPostString('title');
        $title = cut_string(trim($title), 150, 1, 0);

        # better to error out as at least they can go back and retreive the other post content
        if (empty($title)) {
            throw new UserError('You cannot create a blog post with no title');
        }

        $this->db->rawQuery(
            "INSERT INTO staff_blog (UserID, Title, Body, Time)
             VALUES (?, ?, ?, ?)",
            [$user->ID, $title, $body, sqltime()]
        );
        $this->cache->deleteValue('staff_blog');

        return new Redirect("/staff/blog");
    }

    public function blogEdit(int $blogID) {
        $user = $this->request->user;

        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'staff.blog.edit');

        $body = $this->request->getPostString('body');
        $title = $this->request->getPostString('title');
        $title = cut_string(trim($title), 150, 1, 0);

        # better to error out as at least they can go back and retreive the other post content
        if (empty($title)) {
            throw new UserError('You cannot leave a blog post with no title');
        }

        $this->db->rawQuery(
            'UPDATE staff_blog SET Title = ?, Body = ? WHERE ID = ?',
            [$title, $body, $blogID]
        );
        $this->cache->deleteValue('staff_blog');
        $this->cache->deleteValue('staff_feed_blog');

        return new Redirect("/staff/blog");
    }

    public function blogEditForm(int $blogID) {
        $blogPost = $this->db->rawQuery(
            "SELECT ID,
                    Title,
                    Body
               FROM staff_blog
              WHERE ID = ?",
            [$blogID]
        )->fetch(\PDO::FETCH_ASSOC);

        $bscripts = ['bbcode', 'inbox', 'jquery'];

        $params = compact(
            'bscripts',
            'blogPost',
        );

        return new Rendered('@Staff/blog.html.twig', $params);
    }

    public function blogDelete(int $blogID) {
        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'staff.blog.delete');

        $this->db->rawQuery(
            "DELETE FROM staff_blog WHERE ID = ?",
            [$blogID]
        );
        $this->cache->deleteValue('staff_blog');
        $this->cache->deleteValue('staff_feed_blog');

        return new Redirect("/staff/blog");
    }

    public function restrictions() {
        $this->auth->checkAllowed('users_disable_any');
        $type = $this->request->getGetString('type');
        $sort = $this->request->getGetString('sort');
        $userID = $this->request->getGetInt('userid');
        $authorID = $this->request->getGetInt('authorid');

        $restrictions = $this->repos->restrictions->searchRestrictions($type, $userID, $authorID, $sort);

        $params = compact(
            'restrictions',
        );
        return new Rendered('@Staff/restrictions.html.twig', $params);
    }
}
