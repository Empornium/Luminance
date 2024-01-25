<?php
namespace Luminance\Services\Search;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Errors\InternalError;

class SphinxLegacy extends Service {
    private $index = '*';
    private $sphinxClient = null;

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->sphinxClient = new \SphinxClient();
        $this->sphinxClient->setServer($master->settings->sphinx->host, $master->settings->sphinx->port);
        $this->sphinxClient->setMatchMode(SPH_MATCH_EXTENDED2);
    }

    /****************************************************************
    /--- Search function --------------------------------------------

    This function queries sphinx for whatever is in $query, in
    extended2 mode.

    $query          - sphinx query

    ****************************************************************/
    public function search($query = '') {
        $result = $this->sphinxClient->Query($query, $this->index);

        if ($result === false) {
            if ($this->sphinxClient->_connerror) {
                throw new InternalError('Connection to searchd failed');
            }
            $this->master->irker->announceLab('Search for "'.$query.'" ('.str_replace("\n", '', print_r($this->filters, true)).') failed: '.$this->sphinxClient->GetLastError());
        }

        return $result;
    }

    public function limit($start, $length, $maxMatches) {
        $this->sphinxClient->SetLimits((int) $start, (int) $length, $maxMatches, 0);
        return $maxMatches;
    }

    public function setIndex($index) {
        $this->index = $index;
    }

    public function setFilter($name, $vals, $exclude) {
        $this->sphinxClient->SetFilter($name, $vals, $exclude);
    }

    public function setFilterRange($name, $min, $max, $exclude) {
        $this->sphinxClient->SetFilterRange($name, $min, $max, $exclude);
    }

    public function setSortMode($mode, $sortby) {
        $this->sphinxClient->SetSortMode($mode, $sortby);
    }

    public function updateAttributes($index, $attrs, $values, $mva, $ignorenonexistent) {
        $this->sphinxClient->UpdateAttributes($index, $attrs, $values, $mva, $ignorenonexistent);
    }
}
