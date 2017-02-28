<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\Stylesheet;
use Luminance\Entities\User;

class StylesheetRepository extends Repository {

    protected $entityName = 'Stylesheet';
    protected $allStylesheets = null;

    public function get_by_user(User $User) {
        $Stylesheet = $this->load($User->legacy['StyleID']);
        return $Stylesheet;
    }

    public function get_all() {
        if (is_null($this->allStylesheets)) {
            $this->allStylesheets = $this->cache->get_value('stylesheets');
            if (!is_array($this->allStylesheets)) {
                $this->master->olddb->query('SELECT ID, LOWER(REPLACE(Name," ","_")) AS Name, Name AS ProperName FROM stylesheets');
                $this->allStylesheets = $this->master->olddb->to_array('ID', MYSQLI_BOTH);
                $this->cache->cache_value('stylesheets', $this->allStylesheets, 600);
            }
        }
        return $this->allStylesheets;
    }

    public function getDefault() {
        $stylesheet = $this->get('`Default` = ?', ['1']);
        return $stylesheet;
    }
}
