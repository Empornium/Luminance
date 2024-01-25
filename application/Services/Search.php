<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Services\Debug;

use Luminance\Services\Search\SphinxLegacy;

class Search extends Service {
    private $client = null;
    public $totalResults = 0;
    public $queries = [];
    public $time = 0.0;
    public $filters = [];

    protected static $useServices = [
        'auth'     => 'Auth',
        'cache'    => 'Cache',
        'db'       => 'DB',
        'settings' => 'Settings',
        'irker'    => 'Irker',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->client = new SphinxLegacy($master);
    }

    public function search($query = '', $cachePrefix = '', $cacheLength = 0, $returnData = [], $SQL = '', $IDColumn = 'ID') {
        $queryStartTime=microtime(true);
        $result = $this->client->search($query);
        $queryEndTime=microtime(true);

        $filters = [];
        foreach ($this->filters as $name => $values) {
            foreach ($values as $value) {
                $filters[] = $name." - ".$value;
            }
        }

        if (Debug::getEnabled()) {
            $this->queries[] = [
                'query'     => 'Params: '.$query.' Filters: '.implode(", ", $filters).' Indicies: '.$this->index,
                'microtime' => ($queryEndTime-$queryStartTime)*1000
            ];
            $this->time+=($queryEndTime-$queryStartTime)*1000;
        }

        if ($result === false) {
            return false;
        }

        $this->totalResults = $result['total'];

        if (empty($result['matches'])) {
            return false;
        }
        $matches = $result['matches'];

        $matchIDs = array_keys($matches);

        $notFound = [];
        $skip = [];
        if (!empty($returnData)) {
            $allFields = false;
        } else {
            $allFields = true;
        }

        foreach ($matchIDs as $match) {
            $matches[$match] = $matches[$match]['attrs'];
            if (!empty($cachePrefix)) {
                $data = $this->cache->getValue($cachePrefix.'_'.$match);
                if ($data === false) {
                    $notFound[]=$match;
                    continue;
                }
            } else {
                $notFound[]=$match;
            }
            if ($allFields === false) {
                # Populate list of fields to unset (faster than picking out the ones we need). Should only be run once, on the first cache key
                if (empty($skip)) {
                    foreach (array_keys($data) as $key) {
                        if (!in_array($key, $returnData)) {
                            $skip[]=$key;
                        }
                    }
                    if (empty($skip)) {
                        $allFields = true;
                    }
                }
                foreach ($skip as $key) {
                    unset($data[$key]);
                }
                reset($skip);
            }
            if (!empty($data)) {
                $matches[$match] = array_merge($matches[$match], $data);
            }
        }

        if (!($SQL === '')) {
            if (!empty($notFound)) {
                $results = $this->db->rawQuery(str_replace('%ids', implode(',', $notFound), $SQL))->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($results as $result) {
                    $matches[$result[$IDColumn]] = array_merge($matches[$result[$IDColumn]], $result);
                    $this->cache->cacheValue($cachePrefix.'_'.$result[$IDColumn], $result, $cacheLength);
                }
            }
        } else {
            $matches = ['matches'=>$matches,'notfound'=>$notFound];
        }

        return $matches;
    }

    public function limit($start, $length, $maxMatches = null) {
        if (is_null($maxMatches)) {
            $maxMatches = $this->settings->sphinx->matches_start;
        }
        if ($this->auth->isAllowed('site_search_many') && empty($_GET['limit_matches'])) {
            $maxMatches = 1000000;
        }
        $this->client->limit((int) $start, (int) $length, $maxMatches, 0);
        return $maxMatches;
    }

    public function setIndex($index) {
        $this->index = $index;
        $this->client->setIndex($index);
    }

    public function setFilter($name, $vals, $exclude = false) {
        foreach ($vals as $val) {
            $this->filters[$name][] = $val;
        }
        $this->client->setFilter($name, $vals, $exclude);
    }

    public function setFilterRange($name, $min, $max, $exclude = false) {
        $this->filters[$name] = [$min.'-'.$max];
        $this->client->setFilterRange($name, $min, $max, $exclude);
    }

    public function setSortMode($mode, $sortby = "") {
        $this->client->setSortMode($mode, $sortby);
    }

    public function updateAttributes($index, $attrs, $values, $mva = false, $ignorenonexistent = false) {
        $this->client->updateAttributes($index, $attrs, $values, $mva, $ignorenonexistent);
    }

    public function escapeString($string) {
        return strtr($string, ['('=>'\(', ')'=>'\)',  '|'=>'\|',  '@'=>'\@',  '~'=>'\~',  '&'=>'\&',  '/'=>'\/']);
    }
}
