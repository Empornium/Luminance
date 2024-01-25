<?php
namespace Luminance\Plugins\Stats;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Errors\ForbiddenError;

use Luminance\Services\Auth;

use Luminance\Responses\JSON;
use Luminance\Responses\Rendered;

class StatsPlugin extends Plugin {

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  'users',                     Auth::AUTH_LOGIN,  'users'                  ],
        [ 'GET',  'user_flow_chart',           Auth::AUTH_LOGIN,  'userFlowChart'          ],
        [ 'GET',  'class_dist_active_chart',   Auth::AUTH_LOGIN,  'classDistActiveChart'   ],
        [ 'GET',  'class_dist_month_chart',    Auth::AUTH_LOGIN,  'classDistMonthChart'    ],
        [ 'GET',  'class_dist_week_chart',     Auth::AUTH_LOGIN,  'classDistWeekChart'     ],
        [ 'GET',  'user_platforms_chart',      Auth::AUTH_LOGIN,  'userPlatformsChart'     ],
        [ 'GET',  'user_browsers_chart',       Auth::AUTH_LOGIN,  'userBrowsersChart'      ],
        [ 'GET',  'client_chart',              Auth::AUTH_LOGIN,  'clientChart'            ],
        [ 'GET',  'client_major_chart',        Auth::AUTH_LOGIN,  'clientMajorChart'       ],
        [ 'GET',  'client_minor_chart',        Auth::AUTH_LOGIN,  'clientMinorChart'       ],
        [ 'GET',  'choro_world_chart',         Auth::AUTH_LOGIN,  'choroWorldChart'        ],
        [ 'GET',  'choro_north_america_chart', Auth::AUTH_LOGIN,  'choroNorthAmericaChart' ],
        [ 'GET',  'choro_europe_chart',        Auth::AUTH_LOGIN,  'choroEuropeChart'       ],
        [ 'GET',  'choro_south_america_chart', Auth::AUTH_LOGIN,  'choroSouthAmericaChart' ],
        [ 'GET',  'choro_africa_chart',        Auth::AUTH_LOGIN,  'choroAfricaChart'       ],
        [ 'GET',  'choro_asia_chart',          Auth::AUTH_LOGIN,  'choroAsiaChart'         ],

        [ 'GET',  'torrents',                  Auth::AUTH_LOGIN,  'torrents'               ],
        [ 'GET',  'torrent_flow_chart',        Auth::AUTH_LOGIN,  'torrentFlowChart'       ],
        [ 'GET',  'torrent_category_chart',    Auth::AUTH_LOGIN,  'torrentCategoryChart'   ],

        [ 'GET',  'site',                      Auth::AUTH_LOGIN,  'site'                   ],
        [ 'GET',  'site_flow_chart',           Auth::AUTH_LOGIN,  'siteFlowChart'          ],

        [ 'GET',  'forum',                     Auth::AUTH_LOGIN,  'forum'                  ],
        [ 'GET',  'forum_flow_chart',          Auth::AUTH_LOGIN,  'forumFlowChart'         ],
    ];

    protected static $useServices = [
        'auth'      => 'Auth',
        'db'        => 'DB',
        'cache'     => 'Cache',
        'plotly'    => 'Plotly',
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ '*', 'stats/**', Auth::AUTH_LOGIN, 'plugin', 'Stats' ]);
    }

    public function users() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $bscripts = ['plotly', 'charts', 'jquery'];
        $params = [
            'bscripts'  => $bscripts,
            'users'     => $this->getChoroData(),
        ];

        return new Rendered('@Stats/users.html.twig', $params);
    }

    public function userFlowChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('user_flow_chart');

        if ($data === false) {
            $data['joined'] = $this->db->rawQuery(
                "SELECT DATE_FORMAT(JoinDate, '%Y-%m-%d') AS Label,
                        Count(UserID) AS Joined
                   FROM users_info
                  WHERE JoinDate != '0000-00-00 00:00:00'
                    AND JoinDate IS NOT NULL
               GROUP BY Label
               ORDER BY JoinDate ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $data['banned'] = $this->db->rawQuery(
                "SELECT DATE_FORMAT(BanDate, '%Y-%m-%d') AS Label,
                        Count(UserID) AS Banned
                   FROM users_info
                  WHERE BanDate != '0000-00-00 00:00:00'
                    AND BanDate IS NOT NULL
               GROUP BY Label
               ORDER BY BanDate ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $this->cache->cacheValue('user_flow_chart', $data, 3600*24);
        }

        $chart = $this->plotly->newLineChart();

        $joinDates  = array_column($data['joined'], 'Label');
        $joined = array_column($data['joined'], 'Joined');
        $chart->add('New Registrations', $joinDates, $joined);

        $banDates  = array_column($data['banned'], 'Label');
        $banned = array_column($data['banned'], 'Banned');
        $chart->add('Disabled Users', $banDates, $banned);

        # Ensure we have the full combined, sorted date range
        $dates = array_unique(array_merge($joinDates, $banDates));
        sort($dates);

        $latest = new \DateTime(end($dates));
        $earliest = new \DateTime(reset($dates));
        $earliest = $earliest->format('Y-m-d');
        $end = $latest->format('Y-m-d');
        $start = $latest->sub(new \DateInterval('P1Y'))->format('Y-m-d');

        $chart->timeSeries([$start, $end], [$earliest, $end]);
        $chartData = $chart->generate();

        return new JSON($chartData);
    }

    public function classDistActiveChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('class_dist_active_chart');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT p.Name,
                        COUNT(m.ID) AS Users
                   FROM users_main AS m
                   JOIN permissions AS p ON m.PermissionID=p.ID
                  WHERE m.Enabled='1'
               GROUP BY p.Name
               ORDER BY Users DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('class_dist_active_chart', $data, 3600*24);
        }

        $chart = $this->plotly->newPieChart();
        foreach ($data as $value) {
            $chart->add($value['Name'], $value['Users']);
        }
        $chart->color('FF11aa');
        $chartData = $chart->generate(25);

        return new JSON($chartData);
    }

    public function classDistMonthChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('class_dist_month_chart');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT p.Name,
                        COUNT(m.ID) AS Users
                   FROM users_main AS m
                   JOIN permissions AS p ON m.PermissionID=p.ID
                  WHERE m.Enabled = '1'
                    AND m.LastAccess > DATE_SUB(NOW(), INTERVAL 1 MONTH)
               GROUP BY p.Name
               ORDER BY Users DESC",
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('class_dist_month_chart', $data, 3600*24);
        }

        $chart = $this->plotly->newPieChart();
        foreach ($data as $value) {
            $chart->add($value['Name'], $value['Users']);
        }
        $chart->color('FF11aa');
        $chartData = $chart->generate(25);

        return new JSON($chartData);
    }

    public function classDistWeekChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('class_dist_week_chart');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT p.Name,
                        COUNT(m.ID) AS Users
                   FROM users_main AS m
                   JOIN permissions AS p ON m.PermissionID=p.ID
                  WHERE m.Enabled = '1'
                    AND m.LastAccess > DATE_SUB(NOW(), INTERVAL 7 DAY)
               GROUP BY p.Name
               ORDER BY Users DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('class_dist_week_chart', $data, 3600*24);
        }

        $chart = $this->plotly->newPieChart();
        foreach ($data as $value) {
            $chart->add($value['Name'], $value['Users']);
        }
        $chart->color('FF11aa');
        $chartData = $chart->generate(25);

        return new JSON($chartData);
    }

    public function userPlatformsChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('user_platforms_chart');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT cua.Platform,
                        COUNT(s.UserID) AS Users
                   FROM sessions AS s
                   JOIN clients AS c ON s.ClientID=c.ID
                   JOIN client_user_agents AS cua ON c.ClientUserAgentID=cua.ID
               GROUP BY Platform
               ORDER BY Users DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('user_platforms_chart', $data, 3600*24);
        }

        $chart = $this->plotly->newPieChart();
        foreach ($data as $value) {
            $chart->add($value['Platform'], $value['Users']);
        }
        $chart->color('8A00B8');
        $chartData = $chart->generate(25);

        return new JSON($chartData);
    }

    public function userBrowsersChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('user_browsers_chart');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT cua.Browser,
                        COUNT(s.UserID) AS Users
                   FROM sessions AS s
                   JOIN clients AS c ON s.ClientID=c.ID
                   JOIN client_user_agents AS cua ON c.ClientUserAgentID=cua.ID
               GROUP BY Browser
               ORDER BY Users DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('user_browsers_chart', $data, 3600*24);
        }

        $chart = $this->plotly->newPieChart();
        foreach ($data as $value) {
            $chart->add($value['Browser'], $value['Users']);
        }
        $chart->color('008AB8');
        $chartData = $chart->generate(25);

        return new JSON($chartData);
    }

    private function getClientData() {
        $data = $this->cache->getValue('client_chart_data');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT useragent,
                        Count(uid) AS Users
                   FROM xbt_files_users
               GROUP BY useragent
               ORDER BY Users DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('client_chart_data', $data, 3600*24);
        }

        return $data;
    }

    public function clientChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->getClientData();

        $chart = $this->plotly->newPieChart();
        $useragents = [];
        foreach ($data as $value) {
            // break down versions - matches formats "name/mv22/0101" or "name/v1234(mv4444)" or "name/v2345" or "name v.1.0"
            if (preg_match('#^(?|([^/]*)\/([^/]*)\/([^/]*)|([^/]*)\/([^/\(]*)\((.*)\)|([^/]*)\/([^/]*)|([^\s]*)\s(.*))$#', $value['useragent'], $matches)) {
                $useragent = $matches[1];
            } else {
                $useragent = $value['useragent'];
            }
            $users = 0;
            if (array_key_exists($useragent, $useragents)) {
                $users = $useragents[$useragent]['users'];
            }
            $useragents[$useragent] = [
                'useragent' => $useragent,
                'users'     => $users + $value['Users'],
            ];
        }
        foreach ($useragents as $useragent) {
            $chart->add($useragent['useragent'], $useragent['users']);
        }
        $chart->color('00D025');
        $chartData = $chart->generate(25);

        return new JSON($chartData);
    }

    public function clientMajorChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->getClientData();

        $chart = $this->plotly->newPieChart();
        $useragents = [];
        foreach ($data as $value) {
            // break down versions - matches formats "name/mv22/0101" or "name/v1234(mv4444)" or "name/v2345" or "name v.1.0"
            if (preg_match('#^(?|([^/]*)\/([^/]*)\/([^/]*)|([^/]*)\/([^/\(]*)\((.*)\)|([^/]*)\/([^/]*)|([^\s]*)\s(.*))$#', $value['useragent'], $matches)) {
                $useragent = $matches[1] .'/'.$matches[2];
            } else {
                $useragent = $value['useragent'];
            }
            $users = 0;
            if (array_key_exists($useragent, $useragents)) {
                $users = $useragents[$useragent]['users'];
            }
            $useragents[$useragent] = [
                'useragent' => $useragent,
                'users'     => $users + $value['Users'],
            ];
        }
        foreach ($useragents as $useragent) {
            $chart->add($useragent['useragent'], $useragent['users']);
        }
        $chart->color('00D025');
        $chartData = $chart->generate(25);

        return new JSON($chartData);
    }

    public function clientMinorChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->getClientData();

        $chart = $this->plotly->newPieChart();
        foreach ($data as $value) {
            $chart->add($value['useragent'], $value['Users']);
        }
        $chart->color('00D025');
        $chartData = $chart->generate(25);

        return new JSON($chartData);
    }

    private function getChoroData() {
        $data = $this->cache->getValue('choro_chart_data');

        if ($data === false) {
            $data = $this->db->rawQuery(
                'SELECT Code,
                        CountryName,
                        Users
                   FROM users_geodistribution AS ug
                   JOIN geolite2_locations AS gl2l ON gl2l.ISOCode=ug.Code
               GROUP BY ug.Code
               ORDER BY Users DESC'
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('choro_chart_data', $data, 3600*24);
        }

        return $data;
    }

    public function choroWorldChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->getChoroData();

        $chart = $this->plotly->newChoroplethChart();
        foreach ($data as $value) {
            $chart->add($value['CountryName'], $value['Users']);
        }
        $chart->color('210267');
        $chart->scope('world');
        $chartData = $chart->generate();

        return new JSON($chartData);
    }

    public function choroNorthAmericaChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->getChoroData();

        $chart = $this->plotly->newChoroplethChart();
        foreach ($data as $value) {
            $chart->add($value['CountryName'], $value['Users']);
        }
        $chart->color('210267');
        $chart->scope('north america');
        $chartData = $chart->generate();

        return new JSON($chartData);
    }

    public function choroEuropeChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->getChoroData();

        $chart = $this->plotly->newChoroplethChart();
        foreach ($data as $value) {
            $chart->add($value['CountryName'], $value['Users']);
        }
        $chart->color('210267');
        $chart->scope('europe');
        $chartData = $chart->generate();

        return new JSON($chartData);
    }

    public function choroSouthAmericaChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->getChoroData();

        $chart = $this->plotly->newChoroplethChart();
        foreach ($data as $value) {
            $chart->add($value['CountryName'], $value['Users']);
        }
        $chart->color('210267');
        $chart->scope('south america');
        $chartData = $chart->generate();

        return new JSON($chartData);
    }

    public function choroAfricaChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->getChoroData();

        $chart = $this->plotly->newChoroplethChart();
        foreach ($data as $value) {
            $chart->add($value['CountryName'], $value['Users']);
        }
        $chart->color('210267');
        $chart->scope('africa');
        $chartData = $chart->generate();

        return new JSON($chartData);
    }

    public function choroAsiaChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->getChoroData();

        $chart = $this->plotly->newChoroplethChart();
        foreach ($data as $value) {
            $chart->add($value['CountryName'], $value['Users']);
        }
        $chart->color('210267');
        $chart->scope('asia');
        $chartData = $chart->generate();

        return new JSON($chartData);
    }

    public function torrents() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $bscripts = ['plotly', 'charts', 'jquery'];
        $params = [
            'bscripts'  => $bscripts,
        ];

        return new Rendered('@Stats/torrents.html.twig', $params);
    }

    public function torrentFlowChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('torrent_flow_chart');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT DATE_FORMAT(Time, '%Y-%m-%d') AS Label,
                        Count(ID) As Uploaded
                   FROM torrents
                  WHERE Time < (NOW() - INTERVAL 1 DAY)
               GROUP BY Label
               ORDER BY Time ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('torrent_flow_chart', $data, 3600*24);
        }

        $dates    = array_column($data, 'Label');
        $uploads  = array_column($data, 'Uploaded');

        $chart = $this->plotly->newLineChart();
        $chart->add('Uploaded', $dates, $uploads);

        $latest = new \DateTime(end($dates));
        $earliest = new \DateTime(reset($dates));
        $earliest = $earliest->format('Y-m-d');
        $end = $latest->format('Y-m-d');
        $start = $latest->sub(new \DateInterval('P1Y'))->format('Y-m-d');

        $chart->timeSeries([$start, $end], [$earliest, $end]);

        $chartData = $chart->generate();

        return new JSON($chartData);
    }

    public function torrentCategoryChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('torrent_category_chart');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT c.name AS Category,
                        COUNT(t.ID) AS Torrents
                   FROM torrents AS t
                   JOIN torrents_group AS tg ON tg.ID=t.GroupID
                   JOIN categories AS c ON tg.NewCategoryID=c.id
               GROUP BY tg.NewCategoryID
               ORDER BY Torrents DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('torrent_category_chart', $data, 3600*24);
        }

        $chart = $this->plotly->newPieChart();
        foreach ($data as $value) {
            $chart->add($value['Category'], $value['Torrents']);
        }
        $chart->color('FF33CC');
        $chartData = $chart->generate(25);

        return new JSON($chartData);
    }

    public function site() {
        if (!$this->auth->isAllowed('site_view_stats')) {
            throw new ForbiddenError();
        }

        $bscripts = ['plotly', 'charts', 'jquery'];
        $params = [
            'bscripts'  => $bscripts,
        ];

        return new Rendered('@Stats/site.html.twig', $params);
    }

    public function siteFlowChart() {
        if (!$this->auth->isAllowed('site_view_stats')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('site_flow_chart');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT DATE_FORMAT(TimeAdded, '%Y-%m-%d') AS Label,
                        CAST(AVG(Users) AS SIGNED) AS Users,
                        CAST(AVG(Torrents) AS SIGNED) AS Torrents,
                        CAST(AVG(Seeders) AS SIGNED) AS Seeders,
                        CAST(AVG(Leechers) AS SIGNED) AS Leechers
                   FROM site_stats_history
               GROUP BY Label
                 HAVING Count(ID)=4
               ORDER BY TimeAdded ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('site_flow_chart', $data, 3600*24);
        }

        if (!$this->auth->isAllowed('site_stats_advanced')) {
            $data = array_slice($data, -365);
        }

        $dates    = array_column($data, 'Label');
        $users    = array_column($data, 'Users');
        $torrents = array_column($data, 'Torrents');
        $seeders  = array_column($data, 'Seeders');
        $leechers = array_column($data, 'Leechers');


        $chart = $this->plotly->newLineChart();
        $chart->add('Users',    $dates, $users);
        $chart->add('Torrents', $dates, $torrents);
        $chart->add('Seeders',  $dates, $seeders);
        $chart->add('Leechers', $dates, $leechers);

        # Pan to full history is a protected function
        if ($this->auth->isAllowed('site_stats_advanced')) {
            $latest = new \DateTime(end($dates));
            $earliest = new \DateTime(reset($dates));
            $earliest = $earliest->format('Y-m-d');
            $end = $latest->format('Y-m-d');
            $start = $latest->sub(new \DateInterval('P1Y'))->format('Y-m-d');

            $chart->timeSeries([$start, $end], [$earliest, $end]);
        }

        $chartData = $chart->generate();

        return new JSON($chartData);
    }

    public function forum() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $bscripts = ['plotly', 'charts', 'jquery'];
        $params = [
            'bscripts'  => $bscripts,
        ];

        return new Rendered('@Stats/forum.html.twig', $params);
    }

    public function forumFlowChart() {
        if (!$this->auth->isAllowed('site_stats_advanced')) {
            throw new ForbiddenError();
        }

        $data = $this->cache->getValue('forum_flow_chart');

        if ($data === false) {
            $data = $this->db->rawQuery(
                "SELECT DATE_FORMAT(AddedTime, '%Y-%m-%d') AS Label,
                        COUNT(DISTINCT fp.AuthorID) AS Authors,
                        COUNT(DISTINCT fp.ThreadID) AS Threads,
                        COUNT(fp.ID) AS Posts
                   FROM forums_posts AS fp
               GROUP BY Label
               ORDER BY AddedTime ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('forum_flow_chart', $data, 3600*24);
        }

        if (!$this->auth->isAllowed('site_stats_advanced')) {
            $data = array_slice($data, -365);
        }

        $dates    = array_column($data, 'Label');
        $authors  = array_column($data, 'Authors');
        $threads  = array_column($data, 'Threads');
        $posts    = array_column($data, 'Posts');


        $chart = $this->plotly->newLineChart();
        $chart->add('Active Forum Users', $dates, $authors);
        $chart->add('Active Threads',     $dates, $threads);
        $chart->add('New Posts',          $dates, $posts);

        # Pan to full history is a protected function
        if ($this->auth->isAllowed('site_stats_advanced')) {
            $latest = new \DateTime(end($dates));
            $earliest = new \DateTime(reset($dates));
            $earliest = $earliest->format('Y-m-d');
            $end = $latest->format('Y-m-d');
            $start = $latest->sub(new \DateInterval('P1Y'))->format('Y-m-d');

            $chart->timeSeries([$start, $end], [$earliest, $end]);
        }

        $chartData = $chart->generate();

        return new JSON($chartData);
    }
}
