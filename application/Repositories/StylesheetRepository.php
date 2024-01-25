<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\User;
use Luminance\Entities\Stylesheet;

class StylesheetRepository extends Repository {

    protected $entityName = 'Stylesheet';
    protected $allStylesheets = null;

    public function getByUser(User $user) {
        $style = $this->load($user->legacy['StyleID']);
        if (!($style instanceof Stylesheet)) {
            $style = $this->getDefault();
            $this->db->rawQuery(
                "UPDATE users_info SET StyleID = ? WHERE UserID = ?",
                [intval($style->ID), $user->ID]
            );
        } else {
            $styleInstalled = $this->master->render->publicFileExists("static/styles/{$style->Path}/style.css");
            if ($styleInstalled === false) {
                $style = $this->getDefault();
            }
        }
        return $style;
    }

    public function getAll() {
        if (is_null($this->allStylesheets)) {
            $this->allStylesheets = $this->cache->getValue('stylesheets');
            if (!is_array($this->allStylesheets)) {
                $this->allStylesheets = $this->master->db->rawQuery('SELECT ID, ID, LOWER(REPLACE(Name," ","_")) AS Name, Name AS ProperName FROM stylesheets ORDER BY Name')->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
                $this->cache->cacheValue('stylesheets', $this->allStylesheets, 600);
            }
        }
        return $this->allStylesheets;
    }

    public function getDefault() {
        $style = $this->get('`Default` = ?', ['1']);
        return $style;
    }
}
