<?php
namespace Luminance\Plugins\Developer;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Errors\UserError;

use Luminance\Services\Auth;

class DeveloperPlugin extends Plugin {

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'CLI',  'encrypt/*',             Auth::AUTH_NONE, 'encrypt'              ],
        [ 'CLI',  'decrypt/*',             Auth::AUTH_NONE, 'decrypt'              ],
        [ 'CLI',  'entity/**',             Auth::AUTH_NONE, 'entity'               ],
        [ 'CLI',  'fixNotes',              Auth::AUTH_NONE, 'fixNotes'             ],
        [ 'CLI',  'check/permissions',     Auth::AUTH_NONE, 'checkPermissions'     ],
        [ 'CLI',  'regenerate/taglists',   Auth::AUTH_NONE, 'regenerateTaglists'   ],
        [ 'CLI',  'regenerate/tagcounts',  Auth::AUTH_NONE, 'regenerateTagcounts'  ],
        [ 'CLI',  'regenerate/icons',      Auth::AUTH_NONE, 'regenerateIcons'      ],
        [ 'CLI',  'regenerate/invitetree', Auth::AUTH_NONE, 'regenerateInviteTree' ],
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ 'CLI', 'encrypt/*',        Auth::AUTH_NONE, 'plugin', 'Developer', 'encrypt'         ]);
        $master->prependRoute([ 'CLI', 'decrypt/*',        Auth::AUTH_NONE, 'plugin', 'Developer', 'decrypt'         ]);
        $master->prependRoute([ 'CLI', 'entity/**',        Auth::AUTH_NONE, 'plugin', 'Developer', 'entity'          ]);
        $master->prependRoute([ 'CLI', 'fixNotes',         Auth::AUTH_NONE, 'plugin', 'Developer', 'fixNotes'        ]);
        $master->prependRoute([ 'CLI', 'check/*',          Auth::AUTH_NONE, 'plugin', 'Developer', 'check'           ]);
        $master->prependRoute([ 'CLI', 'regenerate/*',     Auth::AUTH_NONE, 'plugin', 'Developer', 'regenerate'      ]);
    }

    public function decrypt($data) {
        var_dump($this->master->crypto->decrypt($data, 'default', true));
    }

    public function encrypt($data) {
        var_dump($this->master->crypto->encrypt($data, 'default', true));
    }

    public function entity($table, $entity = null) {
        $table = $this->master->orm->getTableSpecification($table);
        if (is_array($table['properties'])) {
            $properties = [];
            foreach ($table['properties'] as $name => $property) {
                $property = stripslashes(var_export($property, true));
                $property = str_replace('array (', '[', $property);
                $property = str_replace(PHP_EOL, '', $property);
                $property = str_replace(',)', ' ],', $property);
                $property = str_replace('\'\'', '\'', $property);
                $property = preg_replace('!\s+!', ' ', $property);
                $property = preg_replace('/\'ENUM\((.*?)\)\'/', '"ENUM(${1})"', $property);
                $properties[$name] = $property;
            }
        }

        if (is_array($table['indexes'])) {
            $indexes = [];
            foreach ($table['indexes'] as $name => $index) {
                if (empty($name)) continue;
                $index = var_export($index, true);
                $index = str_replace('array (', '[', $index);
                $index = str_replace(PHP_EOL, '', $index);
                $index = str_replace(',)', ' ],', $index);
                $index = preg_replace('!\s+!', ' ',  $index);
                $index = str_replace('0 => ', '', $index);
                $index = str_replace('\', )', '\' ]', $index);
                $indexes[$name] = $index;
            }
        }

        if (is_null($entity) === false) {
            $entity = preg_replace('|\.php$|', '', $entity);
        }

        $params = [
            'entityName'  => $entity,
            'dbTableName' => $table['table'],
            'properties'  => $properties,
            'indexes'     => $indexes,
        ];

        if (is_null($entity) === true) {
            print($this->master->tpl->render('@Developer/entity.php.twig', $params, 'definition'));
            return;
        }

        # Capture the Entity into a PHP file
        $entityContents = $this->master->tpl->render('@Developer/entity.php.twig', $params);
        $filename = $this->master->applicationPath.'/Entities/'.$entity.'.php';

        if (!is_writable(dirname($filename))) {
            throw new UserError("Unable to write entity file: {$filename}");
        }
        if (is_file($filename)) {
            throw new UserError("Entity file: {$filename} already exists!");
        }

        $entityFile = fopen($filename, "w");
        fwrite($entityFile, $entityContents);
        fclose($entityFile);
    }

    public function fixNotes() {
        $firstUserIDs = $this->master->db->rawQuery(
            "SELECT UserID
               FROM users_info
              WHERE AdminComment RLIKE CONCAT('([[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2} [[:digit:]]{2}:[[:digit:]]{2}:[[:digit:]]{2})(', CHAR(13 using utf8), ')?', CHAR(10 using utf8))"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $secondUserIDs = $this->master->db->rawQuery(
            "SELECT UserID
               FROM users_info
              WHERE AdminComment RLIKE CONCAT('(', CHAR(13 using utf8), ')?', CHAR(10 using utf8), '[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2} [[:digit:]]{2}:[[:digit:]]{2}:[[:digit:]]{2}0.[[:digit:]]{8}')"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $userIDs = array_merge($firstUserIDs, $secondUserIDs);

        print("Processing notes now".PHP_EOL);

        foreach ($userIDs as $userID) {
            $notes = $this->master->db->rawQuery(
                "SELECT AdminComment
                   FROM users_info
                  WHERE UserID = ?",
                [$userID]
            )->fetchColumn();

            $datePattern = '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}';
            $notes = preg_replace("|{$datePattern}({$datePattern})|", '${1}', $notes);
            $notes = preg_replace("|{$datePattern}\n({$datePattern})(0\.\d{8})|", '${1} ${2}'.PHP_EOL, $notes);
            $notes = preg_replace("|( - Disabled for inactivity \(never logged in\))({$datePattern})|", '${2}${1}', $notes);
            $notes = preg_replace("|( - Class changed to \[b\]\[color=apprentice\]Apprentice\[/color\]\[/b\] by System)({$datePattern})|", '${2}${1}', $notes);
            $notes = preg_replace("|( - Class changed to \[b\]\[color=perv\]Perv\[/color\]\[/b\] by System)({$datePattern})|", '${2}${1}', $notes);
            $notes = preg_replace("|( - Leeching re-enabled by adequate ratio.)({$datePattern})|",  '${2}${1}', $notes);
            $notes = preg_replace("|( - Leeching ability disabled by ratio watch system - required ratio: )({$datePattern})|", '${2}${1}', $notes);
            $notes = preg_replace("|( - Leeching ability disabled by ratio watch system for downloading more than 10 gigs on ratio watch - required ratio: )({$datePattern})|", '${2}${1}', $notes);

            # A little legacy cleanup
            $notes = preg_replace("|({$datePattern} - Leeching ability disabled by ratio watch system - required ratio: 0\.\d{8})\n\n|", '${1}'.PHP_EOL, $notes);

            # Final Cleanup
            $notes = preg_replace("|{$datePattern}({$datePattern})|", '${1}', $notes);
            $notes = preg_replace("|(0\.\d{8}){$datePattern}\n{$datePattern}\n|", '${1}'.PHP_EOL, $notes);
            $notes = preg_replace("|\n{$datePattern}(0\.\d{8})|", '${1}'.PHP_EOL, $notes);

            # Save the changes
            $this->master->db->rawQuery(
                "UPDATE users_info
                    SET AdminComment = ?
                  WHERE UserID = ?",
                [$notes, $userID]
            );
        }
    }

/* We'd need to parse all the PHP to find unused or undefined permissions. :hmmm:
    use \Luminance\Legacy\Permissions;
    public function checkPermissions() {
        # check_perms()
        # check_perms_here()
        # auth->isAllowed()
        # auth->isAllowedByMinUser()
        # auth->checkAllowed()

        print("Checking permissions usage".PHP_EOL);
        foreach (static::$test as $check) {
            if (!array_key_exists($check, static::$permissionsArray)) {
                print("Unkown permission: {$check}".PHP_EOL);
            }
        }

        print("----------".PHP_EOL);
        foreach (array_keys(static::$permissionsArray) as $perm) {
            if (!in_array($perm, static::$test)) {
                print("Unused permission: {$perm}".PHP_EOL);
            }
        }
    }
*/

    public function regenerateTaglists() {
        $count = $this->master->db->rawQuery(
            "SELECT MAX(ID) FROM torrents_group"
        )->fetchColumn();
        $step = 10000;
        $affected = 0;
        for ($batch = 0; $batch <= $count; $batch+=$step) {
            $affected += $this->master->db->rawQuery(
                "UPDATE torrents_group AS tg
                   JOIN (SELECT REPLACE(GROUP_CONCAT(tags.Name ORDER BY  (t.PositiveVotes-t.NegativeVotes) DESC SEPARATOR ' '),'.','_') AS TagList,
                                t.GroupID
                           FROM torrents_tags AS t
                     INNER JOIN tags ON tags.ID=t.TagID
                          WHERE t.GroupID BETWEEN ? AND ?
                       GROUP BY t.GroupID) AS taglists ON tg.ID=taglists.GroupID
                    SET tg.TagList=taglists.TagList",
                [$batch, $batch+$step]
            )->rowCount();
        }
        print("Updated {$affected} taglists".PHP_EOL);
    }

    public function regenerateTagcounts() {
        $count = $this->master->db->rawQuery(
            "SELECT MAX(ID) FROM tags"
        )->fetchColumn();
        $step = 10000;
        $affected = 0;
        for ($batch = 0; $batch <= $count; $batch+=$step) {
            $affected += $this->master->db->rawQuery(
                'UPDATE tags
                   JOIN (SELECT COUNT(*) AS Uses,
                                TagID
                           FROM torrents_tags
                          WHERE TagID BETWEEN ? AND ?
                       GROUP BY TagID) AS tt ON tt.TagID=tags.ID
                    SET tags.Uses=tt.Uses',
                [$batch, $batch+$step]
            )->rowCount();
        }
        print("Updated {$affected} tag usage counts".PHP_EOL);
    }

    # Deprecated?
    private function regenerateIcons() {
        # Build new spritesheet SVG
        $icons = new \DOMDocument('1.0', 'utf-8');
        $iconsSVG = new \SimpleXMLElement('<svg></svg>');
        $iconsSVG->addAttribute('style', 'display: block; width: 0px; height: 0px;');
        $iconsSVG = dom_import_simplexml($iconsSVG);
        $iconsSVG = $icons->importNode($iconsSVG, true);
        $iconsSVG = $icons->appendChild($iconsSVG);
        $icons->createAttributeNS('http://www.w3.org/2000/svg', 'xmlns');
        #$icons->documentElement->setAttributeNS('http://www.w3.org/2000/svg', 'xmlns:xlink', 'http://www.w3.org/1999/xlink');

        # Create new defs section
        $defs = $icons->createElementNS('http://www.w3.org/2000/svg', 'defs');
        $defs = $icons->importNode($defs, true);
        $defs = $iconsSVG->appendChild($defs);

        # Load defs
        foreach (glob($this->master->resourcePath."/icons/defs/*.svg") as $defFile) {
            $def = new \DOMDocument();
            $def->load($defFile);
            $defNodes = $def->getElementsByTagName('defs');

            # Should really only be one
            foreach ($defNodes as $defNode) {
                # Copy def contents into iconset
                foreach ($defNode->childNodes as $childNode) {
                    $childNode = $icons->importNode($childNode, true);
                    $childNode = $defs->appendChild($childNode);
                }
            }
        }

        foreach (glob($this->master->resourcePath."/icons/*.svg") as $iconFile) {
            # Load icon file and extract SVG element
            $icon = new \DOMDocument();
            $icon->load($iconFile);
            $icon = $icon->documentElement;

            # Create new glyph symbol from SVG
            $iconGlyph = $icons->createElementNS('http://www.w3.org/2000/svg', 'symbol');
            $iconGlyph = $icons->importNode($iconGlyph, true);
            $iconGlyph = $iconsSVG->appendChild($iconGlyph);

            # Copy attributes
            foreach ($icon->attributes as $attr) {
                switch ($attr->nodeName) {
                    case 'id':
                    case 'viewBox':
                        $iconGlyph->setAttribute($attr->nodeName, $attr->nodeValue);
                }
            }

            # Copy SVG contents into $iconGlyph
            foreach ($icon->childNodes as $childNode) {
                $childNode = $icons->importNode($childNode, true);
                $childNode = $iconGlyph->appendChild($childNode);
            }
        }
        $icons->save($this->master->publicPath . '/static/common/icons.svg');
    }

    public function regenerateInviteTree() {
        //$this->master->db->rawQuery("TRUNCATE invite_tree");
        $count = $this->master->db->rawQuery(
            "SELECT MAX(ID) FROM users"
        )->fetchColumn();
        $step = 10000;
        for ($batch = 0; $batch <= $count; $batch+=$step) {
            $userIDs = $this->master->db->rawQuery(
                "SELECT u.ID
                   FROM users AS u
              LEFT JOIN invite_tree AS it ON u.ID=it.UserID
                  WHERE it.UserID IS NULL
                    AND u.ID BETWEEN ? AND ?",
                [$batch, $batch+$step]
            )->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($userIDs as $userID) {
                print("Generating invite tree for user ID {$userID}\r");
                $this->master->repos->invitetrees->new($userID);
            }
        }
        print(PHP_EOL."Done regenerating invite trees.".PHP_EOL);
    }
}
