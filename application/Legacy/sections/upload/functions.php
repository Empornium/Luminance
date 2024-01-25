<?php
// This is the where the dupechecker functionality lives
// The actual lookup is done using sphinx and matching the bytesize for each file against the indexed filelist in sphinx
// Works becaues the torrent FileList is in the format '|||filepath/name.mp4{{{234567}}}|||filepath/name2.mp4{{{34543543}}}'
// then it loops through any returned torrentid's and looksup the torrent info to match, puts it all in an array and returns it

function check_size_dupes($TorrentFilelist, $ExcludeID=0) {
    global $search, $excludeBytesDupeCheck, $knownFileTypes;

    $search->limit(0, 10, 10);
    $search->setSortMode(SPH_SORT_ATTR_DESC, 'time');
    $search->setIndex(SPHINX_INDEX . ' delta');

    $AllResults=[];
    $UniqueResults = 0;
    $SizeUniqueMatches = 0;

    foreach ($TorrentFilelist as $File) {
        list($Size, $Name) = $File;

        // skip matching files < 2mb in size
        if ($Size < 1024*1024*2) continue;

        // skip image files
        preg_match('/\.([^\.]+)$/i', $Name, $ext);
        if (in_array($ext[1], $knownFileTypes['image'])) continue;

        if (isset($excludeBytesDupeCheck[$Size])) {
            $FakeEntry = [[
                'excluded'=> $excludeBytesDupeCheck[$Size],
                'dupedfile'=>$Name,
                'dupedfilesize'=>$Size
            ]];
            $AllResults = array_merge($AllResults, $FakeEntry);
            continue;
        }

        $Query = '@filelist "' . $search->escapeString($Size) .'"';  // . '"~20';

        // Do the sphinxsearch
        $Results = $search->search($Query, '', 0, [], '', '');
        $Num = $search->totalResults;

        if ($Num>0) {
            // These ones were not found in the cache, run SQL
            if (!empty($Results['notfound'])) {

                $SQLResults = get_groups($Results['notfound']);

                if (is_array($SQLResults['notfound'])) { // Something wasn't found in the db, remove it from results
                    reset($SQLResults['notfound']);
                    foreach ($SQLResults['notfound'] as $ID) {
                        unset($SQLResults['matches'][$ID]);
                        unset($Results['matches'][$ID]);
                    }
                }
                // Merge SQL results with sphinx/memcached results
                foreach ($SQLResults['matches'] as $ID => $SQLResult) {
                    $Results['matches'][$ID] = array_merge($Results['matches'][$ID], $SQLResult);
                    ksort($Results['matches'][$ID]);
                }
            }
            // loop through what matches we have , discard invalid results and add some more info to remaining
            foreach ($Results['matches'] as $ID => $tdata) {

                // If it's excluded then discard the match and continue
                if ($tdata['ID']==$ExcludeID) {
                    unset($Results['matches'][$ID]);
                    continue;
                }

                // If it's a permissible dupe then discard the match and continue
                if (array_key_exists($ID, $tdata['Torrents'])) {
                    if ((time_ago($tdata['Torrents'][$ID]['Time']) > 24*3600*EXCLUDE_DUPES_AFTER_DAYS) &&
                       ($tdata['Torrents'][$ID]['Seeders'] < EXCLUDE_DUPES_SEEDS)) {
                        unset($Results['matches'][$ID]);
                        continue;
                    }
                }

                // Ensure the original file actually exists
                $origfile = get_filename_fromsize($ID, $Size);
                if ($origfile === false) {
                    // if there is no original file with same bytesize then it was most likely a hash collision
                    // discard false positive
                    unset($Results['matches'][$ID]);
                    continue;
                }

                // Record the dupe into the results array
                $Results['matches'][$ID]['dupedfile'] = $Name;
                $Results['matches'][$ID]['dupedfilesize'] = $Size;
                $Results['matches'][$ID]['origfile'] = $origfile;
            }

            // add size and count info and merge the results from this match into the $AllResults array
            if (count($Results['matches'])>0) {
                $SizeUniqueMatches += $Size;
                $UniqueResults++;
                $AllResults = array_merge($AllResults, $Results['matches']);

                if (count($AllResults)>=500) break;
            }
        }
    }
    $NumFiles = count($TorrentFilelist);
    if (count($AllResults)<1) return ['UniqueMatches'=>0, 'NumChecked'=>$NumFiles, 'SizeUniqueMatches'=>0, 'DupeResults'=>false];

    return ['UniqueMatches'=>$UniqueResults, 'NumChecked'=>$NumFiles, 'SizeUniqueMatches'=>$SizeUniqueMatches, 'DupeResults'=>$AllResults];
}

function get_filename_fromsize($groupID, $ExactSize) {
    global $master;

    $ExactSize = "{{{{$ExactSize}}}}";

    $groupID = (int) $groupID;
    $FileList = "";

    $TorrentCache=$master->cache->getValue("torrents_details_{$groupID}");

    if (!is_array($TorrentCache) || !isset($TorrentCache[1][0]['FileList'])) {
        // not cached so just grab it, torrent_details is a massive cached beast so we wont grab & fill the cache just for this
        $FileList = $master->db->rawQuery(
            "SELECT FileList
               FROM torrents
              WHERE GroupID = ?",
            [$groupID])->fetchColumn();
    }
    else
    {
        // $TorrentCache[1] is torrentlist, ($TorrentCache[0] is torrentdetails, $TorrentCache[2] is tags)
        // [0] is the index of the torrent in the group, always 0 because we have 1:1 group:torrent
        $FileList = $TorrentCache[1][0]['FileList'];
    }

    $OrigFiles = explode('|||', $FileList);
    $OrigFileName = false;  // "File not found";
    foreach ($OrigFiles as $OrigFile) {
        if (strpos($OrigFile, "$ExactSize") !== false) {
            $OrigFileName = explode('{{{', $OrigFile)[0];
        }
    }
    return $OrigFileName;
}



function get_templates_private($userID) {
    global $master;

    $userTemplates = $master->cache->getValue('templates_ids_' . $userID);
    if (empty($userTemplates)) {
        $userTemplates = $master->db->rawQuery(
          "SELECT ID,
                  Name as Title
             FROM upload_templates
            WHERE UserID = ?
              AND Public = '0'
         ORDER BY Name",
            [$userID]
        )->fetchAll(\PDO::FETCH_OBJ);
        $master->cache->cacheValue('templates_ids_' . $userID, $userTemplates, 96400);
    }

    return (array)$userTemplates;
}

function get_templates_public() {
    global $master;

    $publicTemplates = $master->cache->getValue('templates_public');
    if (empty($publicTemplates)) {
        $publicTemplates = $master->db->rawQuery(
            "SELECT t.ID,
                    CONCAT(t.Name, ' (by ',  u.Username, ')') as Title
               FROM upload_templates as t
          LEFT JOIN users AS u ON u.ID = t.UserID
              WHERE Public = '1'
           ORDER BY Name"
        )->fetchAll(\PDO::FETCH_OBJ);
        $master->cache->cacheValue('templates_public', $publicTemplates, 96400);
    }

    return $publicTemplates;
}

/**
 * Returns the inner list elements of the tag table for a torrent
 * (this function calls/rebuilds the group_info cache for the torrent - in theory just a call to memcache as all calls come through the torrent details page)
 * @param int $GroupID The group id of the torrent
 * @return the html for the taglist
 */
function get_templatelist_html($userID, $SelectedTemplateID = 0) {
    global $master;

    ob_start();

    $TemplatesPrivate = get_templates_private($userID);
    $TemplatesPublic = get_templates_public();
?>

        <select id="template" name="template" onchange="SelectTemplate(<?=(check_perms('site_delete_any_templates')?'1':'0')?>);" title="Select a template (*=public)">
            <option class="indent" value="0" <?php  if ($SelectedTemplateID==0) echo ' selected="selected"' ?>>---</option>
<?php
        if (count($TemplatesPrivate) > 0) {
?>
            <optgroup label="private templates">
<?php
            foreach ($TemplatesPrivate as $template) { ?>
                <option class="indent" value="<?=$template->ID?>"<?php  if ($SelectedTemplateID==$template->ID) echo ' selected="selected"' ?>><?=$template->Title?></option>
<?php           }         ?>
            </optgroup>
<?php
        }
        if (count($TemplatesPublic)>0) {
?>
            <optgroup label="public templates">
<?php
            foreach ($TemplatesPublic as $template) { ?>
                <option class="indent" value="<?=$template->ID?>"<?php  if ($SelectedTemplateID==$template->ID) echo ' selected="selected"' ?>><?=$template->Title?></option>
<?php           }           ?>
            </optgroup>
<?php       }         ?>
        </select>
<?php
    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}
