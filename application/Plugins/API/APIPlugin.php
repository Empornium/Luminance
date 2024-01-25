<?php
namespace Luminance\Plugins\API;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Services\Auth;

use Luminance\Responses\JSON;
use Luminance\Responses\Rendered;

class APIPlugin extends Plugin {

    protected static $defaultOptions = [
        'torznabSupportedCategories' => ['value' => 0,    'section' => 'torznab',  'displayRow' => 1, 'displayCol' => 1, 'type' => 'string',  'description' => 'Supported Torznab categories (comma separated)'],
    ];

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  'torznab',    Auth::AUTH_API,  'torznab'    ],
    ];

    protected static $useServices = [
        'db'            => 'DB',
        'options'       => 'Options',
        'render'        => 'Render',
        'repos'         => 'Repos',
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ '*', 'api/**', Auth::AUTH_API, 'plugin', 'API' ]);
    }

    protected static $torznabCategories = [
        1000 => [
            "Name"          => "Console",
            "Subcategories" => [
                [ "ID" => 1010, "Name" => "Console/NDS"           ],
                [ "ID" => 1020, "Name" => "Console/PSP"           ],
                [ "ID" => 1030, "Name" => "Console/Wii"           ],
                [ "ID" => 1040, "Name" => "Console/XBox"          ],
                [ "ID" => 1050, "Name" => "Console/XBox 360"      ],
                [ "ID" => 1060, "Name" => "Console/Wiiware"       ],
                [ "ID" => 1070, "Name" => "Console/XBox 360 DLC"  ],
            ],
        ],
        2000 => [
            "Name"          => "Movies",
            "Subcategories" => [
                [ "ID" => 2010, "Name" => "Movies/Foreign"        ],
                [ "ID" => 2020, "Name" => "Movies/Other"          ],
                [ "ID" => 2030, "Name" => "Movies/SD"             ],
                [ "ID" => 2040, "Name" => "Movies/HD"             ],
                [ "ID" => 2045, "Name" => "Movies/UHD"            ],
                [ "ID" => 2050, "Name" => "Movies/BluRay"         ],
                [ "ID" => 2060, "Name" => "Movies/3D"             ],
            ],
        ],
        3000 => [
            "Name"          => "Audio",
            "Subcategories" => [
                [ "ID" => 3010, "Name" => "Audio/MP3"             ],
                [ "ID" => 3020, "Name" => "Audio/Video"           ],
                [ "ID" => 3030, "Name" => "Audio/Audiobook"       ],
                [ "ID" => 3040, "Name" => "Audio/Lossless"        ],
            ],
        ],
        4000 => [
            "Name"          => "PC",
            "Subcategories" => [
                [ "ID" => 4010, "Name" => "PC/0day"               ],
                [ "ID" => 4020, "Name" => "PC/ISO"                ],
                [ "ID" => 4030, "Name" => "PC/Mac"                ],
                [ "ID" => 4040, "Name" => "PC/Mobile-Other"       ],
                [ "ID" => 4050, "Name" => "PC/Games"              ],
                [ "ID" => 4060, "Name" => "PC/Mobile-iOS"         ],
                [ "ID" => 4070, "Name" => "PC/Mobile-Android"     ],
            ],
        ],
        5000 => [
            "Name"          => "TV",
            "Subcategories" => [
                [ "ID" => 5020, "Name" => "TV/Foreign"            ],
                [ "ID" => 5030, "Name" => "TV/SD"                 ],
                [ "ID" => 5040, "Name" => "TV/HD"                 ],
                [ "ID" => 5045, "Name" => "TV/UHD"                ],
                [ "ID" => 5050, "Name" => "TV/Other"              ],
                [ "ID" => 5060, "Name" => "TV/Sport"              ],
                [ "ID" => 5070, "Name" => "TV/Anime"              ],
                [ "ID" => 5080, "Name" => "TV/Documentary"        ],
            ],
        ],
        6000 => [
            "Name"          => "XXX",
            "Subcategories" => [
                [ "ID" => 6010, "Name" => "XXX/DVD"               ],
                [ "ID" => 6020, "Name" => "XXX/WMV"               ],
                [ "ID" => 6030, "Name" => "XXX/XviD"              ],
                [ "ID" => 6040, "Name" => "XXX/x264"              ],
                [ "ID" => 6050, "Name" => "XXX/Pack"              ],
                [ "ID" => 6060, "Name" => "XXX/ImgSet"            ],
                [ "ID" => 6070, "Name" => "XXX/Other"             ],
            ],
        ],
        7000 => [
            "Name"          => "Books",
            "Subcategories" => [
                [ "ID" => 7010, "Name" => "Books/Mags"            ],
                [ "ID" => 7020, "Name" => "Books/EBook"           ],
                [ "ID" => 7030, "Name" => "Books/Comics"          ],
            ],
        ],
        8000 => [
            "Name"          => "Other",
            "Subcategories" => [
                [ "ID" => 8010, "Name" => "Other/Misc"            ],
            ],
        ],
    ];

    protected function output($template, $params) {
        $output = $this->request->getGetString('o', 'xml'); // valid output formats are json, xml
        if ($output === 'xml') {
            header("Content-type: application/rss+{$output}; charset=utf-8");
            return new Rendered($template, $params);
        } else if ($output === 'json') {
            header("Content-type: application/rss+{$output}; charset=utf-8");
            $xml = simplexml_load_string($this->render->template($template, $params));
            return new JSON($xml);
        } else {
            header('Content-type: application/rss+xml; charset=utf-8');
            $code = 201;
            return new Rendered(
                '@API/torznab/error.xml.twig',
                ['Code' => $code, 'Description' => static::$torznabErrors[$code]]
            );
        }
    }

    protected static $torznabErrors = [
        100 => "Incorrect user credentials.",
        101 => "Account suspended.",
        102 => "Insufficient privileges/not authorized.",
        103 => "Registration denied.",
        104 => "Registrations are closed.",
        105 => "Invalid registration (Email Address Taken).",
        106 => "Invalid registration (Email Address Bad Format).",
        107 => "Registration Failed (Data error).",
        200 => "Missing parameter.",
        201 => "Incorrect parameter.",
        202 => "No such function.",
        203 => "Function not available.",
        300 => "No such item.",
        // spec defines 300 as both "No such item." and "Item already exists." WTF?
        //300 => "Item already exists.",
        500 => "Request limit reached.",
        501 => "Download limit reached.",
        900 => "Unknown error.",
        // spec defines API disabled as 910, but the spec author used 901 in a torznab implementation
        910 => "API Disabled.",
    ];

    protected function apiError(int $code) {
        return $this->output(
            '@API/torznab/error.xml.twig',
            ['Code' => $code, 'Description' => static::$torznabErrors[$code]]
        );
    }

    protected function caps() {
        $categoryCapability = explode(',', $this->options->torznabSupportedCategories);
        ksort($categoryCapability);
        $categories = [];
        $subcategories = [];
        // Copy categories from $torznabCategories
        foreach ($categoryCapability as $category) {
            $category = (int) $category;
            if (($category % 1000) === 0) {
                $categories[$category] = static::$torznabCategories[$category];
            } else {
                $subcategories[] = $category;
            }
        }
        // Unset unsupported subcategories
        foreach ($categories as $category => $categoryInfo) {
            $subcats = $categoryInfo['Subcategories'];
            foreach ($subcats as $index => $subcat) {
                if (!(in_array($subcat['ID'], $subcategories))) {
                    unset($categories[$category]['Subcategories'][$index]);
                }
            }
        }

        $genres = [];
        // instead of passing every single tag to caps output, send tags that have special meaning
        $tags = [
            ["Name" => "anonymous", "Description" => "Uploader is anonymous"        ],
            ["Name" => "checked",   "Description" => "Torrent release is checked"   ],
            ["Name" => "internal",  "Description" => "Torrent release is internal"  ],
        ];

        $params = [
            'categories'  => $categories,
            'genres'      => $genres,
            'tags'        => $tags,
        ];
        return $this->output('@API/torznab/caps.xml.twig', $params);
    }

    protected function details() {
        $torrentID = $this->request->getGetInt('id', 0); // spec says this is id, spec's examples say this is guid
        if ($torrentID <= 0) {
            return $this->apiError(201);
        }
        $torrent = $this->repos->torrents->load($torrentID);
        if (!(is_null($torrent))) {
            $torrentPass = $this->request->user->legacy['torrent_pass'];
            $torrentFile = $torrent->file->download($torrentPass);
            $torrentFileSize = strlen($torrentFile->enc());
            $torrent->fileSize = $torrentFileSize;

            list($torrentCategoryIDs, $torrentCategory) = $this->assignCategories($torrent);
            $torrent->CategoryIDs = $torrentCategoryIDs;
            $torrent->Category = new \Twig\Markup($torrentCategory, 'UTF-8');

            $reviewStatuses = $this->fetchReviewStatus([$torrent->ID]);
            if (array_key_exists($torrent->ID, $reviewStatuses)) {
                $torrent->Status = $reviewStatuses[$torrent->ID]['Status'];
            }
            $torrent->Tags = $this->assignTags($torrent);
            return $this->output('@API/torznab/details.xml.twig', ['items' => [$torrent]]);
        } else {
            return $this->apiError(300);
        }
    }

    protected function convertCategoriesToConditions(array $categoryIDs) {
        // categories are the only query parameter that are handled with LOGICAL OR
        $conditions = [];
        $params = [];
        $unsupportedCategories = 0;
        $tagMatchCondition = "MATCH (tt.TagList) AGAINST (? IN BOOLEAN MODE)";
        foreach ($categoryIDs as $categoryID) {
            $categoryID = (int) $categoryID;
            if ($categoryID === 2000) {
                $conditions[] = "({$tagMatchCondition})";
                $params[] = 'sd.movie';
                $conditions[] = "({$tagMatchCondition})";
                $params[] = 'hd.movie';
            } else if ($categoryID === 2030) {
                $conditions[] = "({$tagMatchCondition})";
                $params[] = 'sd.movie';
            } else if ($categoryID === 2040) {
                $conditions[] = "({$tagMatchCondition})";
                $params[] = 'hd.movie';
            } else if ($categoryID === 2045) {
                $conditions[] = "(({$tagMatchCondition}) AND ({$tagMatchCondition}))";
                $params[] = 'hd.movie';
                $params[] = '2160p';
            } else if ($categoryID === 2050) {
                $conditions[] = "(({$tagMatchCondition}) AND ({$tagMatchCondition}))";
                $params[] = "sd.movie";
                $params[] = "bluray";
                $conditions[] = "(({$tagMatchCondition}) AND ({$tagMatchCondition}))";
                $params[] = "hd.movie";
                $params[] = "bluray";
            } else if ($categoryID === 5000) {
                $conditions[] = "({$tagMatchCondition})";
                $params[] = "sd.episode";
                $conditions[] = "({$tagMatchCondition})";
                $params[] = "hd.episode";
                $conditions[] = "({$tagMatchCondition})";
                $params[] = "sd.season";
                $conditions[] = "({$tagMatchCondition})";
                $params[] = "hd.season";
            } else if ($categoryID === 5030) {
                $conditions[] = "({$tagMatchCondition})";
                $params[] = "sd.episode";
                $conditions[] = "({$tagMatchCondition})";
                $params[] = "sd.season";
            } else if ($categoryID === 5040) {
                $conditions[] = "(({$tagMatchCondition}) AND ({$tagMatchCondition}))";
                $params[] = "hd.episode";
                $params[] = "-2160p";
                $conditions[] = "(({$tagMatchCondition}) AND ({$tagMatchCondition}))";
                $params[] = "hd.season";
                $params[] = "-2160p";
            } else if ($categoryID === 5045) {
                $conditions[] = "(({$tagMatchCondition}) AND ({$tagMatchCondition}))";
                $params[] = "hd.episode";
                $params[] = "2160p";
                $conditions[] = "(({$tagMatchCondition}) AND ({$tagMatchCondition}))";
                $params[] = "hd.season";
                $params[] = "2160p";
            } else if ($categoryID === 5060) {
                $conditions[] = "({$tagMatchCondition})";
                $params[] = "sports";
            } else if (!($categoryID === 0)) {
                // api spec quirk, unsupported categories are silently dropped with the
                // exception of when the query consists ONLY of unsupported categories
                // which means an empty set is returned
                $unsupportedCategories++;
            }
        }
        if (($unsupportedCategories > 0) && ($unsupportedCategories === count($categoryIDs))) {
            return [[0], []];
        }
        if (!(empty($conditions))) {
            $where = implode(" OR ", $conditions);
            return [[$where], $params];
        } else {
            return [[], []];
        }
    }

    protected function convertExternalIDsToConditions($imdbid, $tmdbid, $tvdbid, $tvmazeid) {
        $conditions = [];
        $params = [];
        if (!(is_null($imdbid))) {
            $conditions[] = "(metadata_sources.IMDBID = ?)";
            $params[] = $imdbid;
        }
        if (!(is_null($tmdbid))) {
            $conditions[] = "(metadata_sources.TMDBID = ?)";
            $params[] = $tmdbid;
        }
        if (!(is_null($tvdbid))) {
            $conditions[] = "(metadata_sources.TVDBID = ?)";
            $params[] = $tvdbid;
        }
        if (!(is_null($tvmazeid))) {
            $conditions[] = "(metadata_sources.TVMazeID = ?)";
            $params[] = $tvmazeid;
        }
        return [$conditions, $params];
    }

    protected function convertSeasonEpisodeToConditions($season, $episode) {
        $conditions = [];
        $params = [];
        if (!(is_null($season))) {
            $conditions[] = "(tvs.Season = ?)";
            $params[] = (int) $season;
        }

        if (!(is_null($episode))) {
            if (is_integer_string($episode)) {
                $conditions[] = "(tve.Episode = ?)";
                $params[] = (int) $episode;
            } else if ((preg_match('#^([0-9]{1,2})/([0-9]{1,2})$#', $episode, $match)) && (strlen($season) === 4)) {
                $conditions[] = "(tve.Premiered = ?)";
                $month = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                $day = str_pad($match[2], 2, '0', STR_PAD_LEFT);
                $params[] = "{$season}-{$month}-{$day}";
            }
        }
        return [$conditions, $params];
    }

    protected function convertTagsToConditions(array $tags) {
        $conditions = [];
        $params = [];
        foreach ($tags as $tag) {
            if (str_starts_with($tag, '-')) {
                $negate = true;
                $tag = ltrim($tag, '-');
            } else {
                $negate = false;
            }
            if ($tag === "internal") {
                $conditions[] = "(MATCH (tt.TagList) AGAINST (? IN BOOLEAN MODE))";
                $params[] = ($negate === true) ? "-{$tag}" : $tag;
            } else if ($tag === "anonymous") {
                if ($negate === true) {
                    $conditions[] = "(t.Anonymous = '0')";
                } else {
                    $conditions[] = "(t.Anonymous = '1')";
                }
            } else if ($tag === "checked") {
                if ($negate === true) {
                    $conditions[] = "(tr.Status != 'Okay')";
                } else {
                    $conditions[] = "(tr.Status = 'Okay')";
                }
            }
        }
        return [$conditions, $params];
    }

    // look up a torrent object's taglist and assign appropriate torznab category
    protected function assignCategories($torrent) {
        if ((!(strpos($torrent->TagList, 'season') === false)) || (!(strpos($torrent->TagList, 'episode') === false))) {
            $baseCategoryID = 5000;
        } else {
            $baseCategoryID = 2000;
        }
        $baseCategory = static::$torznabCategories[$baseCategoryID]['Name'];

        if (!(strpos($torrent->TagList, '2160p') === false)) {
            $categoryIDs = [$baseCategoryID, $baseCategoryID + 45];
            $category = "{$baseCategory} > UHD";
        } else if (!(strpos($torrent->TagList, 'hd.') === false)) {
            $categoryIDs = [$baseCategoryID, $baseCategoryID + 40];
            $category = "{$baseCategory} > HD";
        } else {
            $categoryIDs = [$baseCategoryID, $baseCategoryID + 30];
            $category = "{$baseCategory} > SD";
        }
        if (!(strpos($torrent->TagList, 'sports') === false)) {
            $categoryIDs[] = 5060;
            $category = "TV > Sport"; // go with the more specific category
        }
        // TODO Anime?
        return [$categoryIDs, $category];
    }

    // look up a torrent object's properties and assign appropriate torznab tags
    protected function assignTags($torrent) {
        $tags = [];
        if ($torrent->Anonymous === '1') {
            $tags[] = 'anonymous';
        }
        if ($torrent->Status === 'Okay') {
            $tags[] = 'checked';
        }
        if (!(strpos($torrent->TagList, 'internal.release') === false)) {
            $tags[] = 'internal';
        }
        return $tags;
    }

    protected function fetchReviewStatus($torrentIDs) {
        //$inQuery = implode(',', array_fill(0, count($torrentIDs), '?'));
        //return $this->db->rawQuery(
        //    "SELECT tr.TorrentID, Status FROM torrents_reviews AS tr,
        //            (SELECT TorrentID, MAX(Time) AS Time FROM torrents_reviews GROUP BY TorrentID) AS NewestReviewPerTorrent
        //      WHERE tr.TorrentID = NewestReviewPerTorrent.TorrentID
        //        AND tr.Time = NewestReviewPerTorrent.Time
        //        AND tr.TorrentID IN ({$inQuery})",
        //    $torrentIDs
        //)->fetchAll(\PDO::FETCH_KEY_PAIR);
        $reviews = [];
        foreach ($torrentIDs as $torrentID) {
            $reviews[$torrentID] = get_last_review($torrentID);
        }
        return $reviews;
    }

    protected function search(string $initialCondition = null, string $initialParam = null) {
        $conditions = []; // these conditions are combined with LOGICAL AND and passed to torrent search
        $params = [];

        if (!(is_null($initialCondition))) {
            $conditions[] = $initialCondition;
            $params[] = $initialParam;
        }

        $query = trim($this->request->getGetString('q'));
        if (!($query === "")) {
            $conditions[] = "(MATCH (tt.Title) AGAINST (? IN BOOLEAN MODE))";
            $params[] = parse_search($query);
        }
        $attr = $this->request->getGetString('attrs');
        $cat = $this->request->getGetString('cat');
        $tag = $this->request->getGetString('tag');
        $minSize = $this->request->getGetInt('minsize');
        $minSize = max($minSize, 0);
        $maxSize = $this->request->getGetInt('maxsize');
        $maxSize = max($maxSize, 0);
        if (($minSize > 0) && ($maxSize > 0)) {
            $conditions[] = "(t.Size BETWEEN ? AND ?)";
            $params[] = $minSize;
            $params[] = $maxSize;
        } else if ($minSize > 0) {
            $conditions[] = "(t.Size > ?)";
            $params[] = $minSize;
        } else if ($maxSize > 0) {
            $conditions[] = "(t.Size < ?)";
            $params[] = $maxSize;
        }

        // planned (currently unsupported, with plans to implement)
        $imdbid = $this->request->getGetString('imdbid', null);
        //$tmdbid = $this->request->getGetInt('tmdbid', null); // this requires either movie or tv to be specified
        $tvdbid = $this->request->getGetInt('tvdbid', null);
        $tvmazeid = $this->request->getGetInt('tvmazeid', null);
        //list($externalIDConditions, $externalIDParams) = $this->convertExternalIDsToConditions($imdbid, $tmdbid, $tvdbid, $tvmazeid);
        //$conditions = array_merge($conditions, $externalIDConditions);
        //$params = array_merge($params, $externalIDParams);
        // $season is either a season number or YYYY
        $season = $this->request->getGetInt('season', null);
        // $episode is either a episode number or MM/DD
        $episode = $this->request->getGetString('ep', null);
        //list($tvConditions, $tvParams) = $this->convertSeasonEpisodeToConditions($season, $episode);
        //$conditions = array_merge($conditions, $tvConditions);
        //$params = array_merge($params, $tvParams);
        //$attrs = explode(',', $attr);
        //$extended = $this->request->getGetBool('extended', false);

        // unsupported (any unsupported parameters should result in API returning an empty set according to spec
        $rageID = $this->request->getGetInt('rid', false);
        if (!($rageID === false)) {
            $conditions[] = 0;
        }
        $offset = $this->request->getGetInt('offset', 0);
        $limit = $this->request->getGetInt('limit', 25);
        if (!(in_array($limit, [25, 50, 100]))) {
            return $this->apiError(201);
        }
        if ($limit <= 0) {
            return $this->apiError(201);
        }
        // offset should be a multiple of limit
        if (!($offset % $limit === 0)) {
            return $this->apiError(201);
        }
        $sort = $this->request->getGetString('sort', 'id_desc');
        if (strpos($sort, '_') === false) {
            return $this->apiError(201);
        }
        list($sortBy, $sortWay) = explode('_', $sort);
        if (!(in_array($sortBy, ['id', 'size', 'name', 'seeders', 'leechers', 'downloads']))) {
            return $this->apiError(201);
        }
        if (!(in_array($sortWay, ['asc', 'desc']))) {
            return $this->apiError(201);
        }
        $categoryIDs = explode(',', $cat);
        list($categoryConditions, $categoryParams) = $this->convertCategoriesToConditions($categoryIDs);
        $conditions = array_merge($conditions, $categoryConditions);
        $params = array_merge($params, $categoryParams);
        $tags = explode(',', $tag);
        list ($tagConditions, $tagParams) = $this->convertTagsToConditions($tags);
        $conditions = array_merge($conditions, $tagConditions);
        $params = array_merge($params, $tagParams);

        $where = (empty($conditions)) ? '1' : implode(" AND ", $conditions);

        if ($sortBy === 'id') {
            $orderBy = "t.ID";
        } else if ($sortBy === 'size') {
            $orderBy = "t.Size";
        } else if ($sortBy === 'name') {
            $orderBy = "tt.Title";
        } else if ($sortBy === 'seeders') {
            $orderBy = "t.Seeders";
        } else if ($sortBy === 'leechers') {
            $orderBy = "t.Leechers";
        } else {
            $orderBy = "t.Snatched";
        }
        $orderWay = strtoupper($sortWay);
        $limit = ($offset === 0) ? $limit : "{$offset}, {$limit}";

        //$torrents = $this->repos->torrents->find($where, $queryParams, "{$orderBy} {$orderWay}", "LIMIT {$limit}");
        $torrents = $this->db->rawQuery(
            "SELECT tg.Name, t.GroupID, t.ID, t.Time, t.Size, t.FileCount, t.Anonymous, t.UserID, t.Snatched, t.Seeders, t.Leechers, u.Username, tt.TagList, t.info_hash, tr.Status
               FROM torrents AS t
               JOIN torrents_group AS tg ON tg.ID = t.GroupID
               JOIN users AS u ON u.ID = t.UserID
          LEFT JOIN (SELECT tr.*
                       FROM torrents_reviews AS tr,
                            (SELECT TorrentID, MAX(Time) AS Time
                               FROM torrents_reviews
                           GROUP BY TorrentID) AS NewestReview
                      WHERE tr.TorrentID = NewestReview.TorrentID
                        AND tr.Time = NewestReview.Time) AS tr ON tr.TorrentID = t.ID
              WHERE {$where}
           ORDER BY {$orderBy} {$orderWay}
              LIMIT {$limit}",
            $params
        )->fetchAll(\PDO::FETCH_OBJ);

        if (!(empty($torrents))) {
            $torrentPass = $this->request->user->legacy['torrent_pass'];
        }

        foreach ($torrents as $torrent) {
            $torrentFile = getTorrentFile($torrent->ID, $torrentPass);
            $torrentFileSize = strlen($torrentFile->enc());
            $torrent->fileSize = $torrentFileSize;

            $torrent->InfoHash = unpack("H*", $torrent->info_hash)[1];
            list($torrentCategoryIDs, $torrentCategory) = $this->assignCategories($torrent);
            $torrent->CategoryIDs = $torrentCategoryIDs;
            $torrent->Category = new \Twig\Markup($torrentCategory, 'UTF-8');

            $torrent->Tags = $this->assignTags($torrent);
        }
        return $this->output('@API/torznab/details.xml.twig', ['items' => $torrents]);
    }

    public function torznab() {
        if ($this->options->APIEnabled) {
            $output = $this->request->getGetString('o', 'xml'); // valid output formats are json, xml
            if (!(in_array($output, ['json', 'xml']))) {
                return $this->apiError(201);
            }

            $function = $this->request->getGetString('t');

            switch ($function) {
                case 'caps':
                    return $this->caps();
                case 'search':
                    return $this->search();
                case 'tvsearch':
                    $condition = "(MATCH (tt.TagList) AGAINST (?))";
                    $param = "sd.episode OR sd.season OR hd.episode OR hd.season";
                    return $this->search($condition, $param);
                case 'moviesearch':
                    $condition = "(MATCH (tt.TagList) AGAINST (?))";
                    $param = "sd.movie OR hd.movie";
                    return $this->search($condition, $param);
                // the rest are technically newznab endpoints
                case 'details':
                    return $this->details();
                case 'getnfo':
                    return $this->apiError(203);
                case 'get':
                    return $this->apiError(203);
                case 'cartadd':
                    return $this->apiError(203);
                case 'cartdel':
                    return $this->apiError(203);
                case 'comments':
                    return $this->apiError(203);
                case 'commentadd':
                    return $this->apiError(203);
                case 'user':
                    return $this->apiError(203);
                default:
                    return $this->apiError(202);
            }
        } else {
            return $this->apiError(910);
        }
    }
}
