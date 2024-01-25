<?php
namespace Luminance\Legacy;
class InviteTree {
    public function makeTree($userID) {
        global $master;

        $tree = $master->db->rawQuery(
            "SELECT TreeID,
                    TreeLevel,
                    TreePosition
               FROM invite_tree
               WHERE UserID = ?
           ORDER BY TreePosition ASC
              LIMIT 1",
              [$userID]
        )->fetch(\PDO::FETCH_NUM);

        if (empty($tree)) return;
        list($page, $limit) = page_limit(100);

         // used so adjacent trees do not show up in immediate tree
         $maxPosition = $master->db->rawQuery(
            "SELECT TreePosition
               FROM invite_tree
              WHERE TreeID = ?
                AND TreeLevel = ?
                AND TreePosition > ?
           ORDER BY TreePosition ASC
              LIMIT 1",
            $tree
        )->fetchColumn();

        if ($maxPosition === false) {
            $wherePosition = "";
            $params = $tree;
        } else {
            $wherePosition = "AND TreePosition < ?";
            $params = array_merge($tree, [$maxPosition]);
        }

        $invitees = $master->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS
                    UserID,
                    TreePosition,
                    TreeLevel
               FROM invite_tree
              WHERE TreeID = ?
              AND TreeLevel > ?
              AND TreePosition > ?
            {$wherePosition}
           ORDER BY TreePosition
              LIMIT {$limit}",
           $params
        )->fetchAll(\PDO::FETCH_UNIQUE|\PDO::FETCH_OBJ);

        $treeLevel = $tree[1];

        // Stats for the summary
        $tree = [
            'MaxTreeLevel'      => $treeLevel,      // The deepest level (this changes)
            'OriginalTreeLevel' => $treeLevel,      // The level of the user we're viewing
            'BaseTreeLevel'     => $treeLevel + 1,  // The level of users invited by our user
            'Count'             => 0,
            'Branches'          => 0,
            'DisabledCount'     => 0,
            'DonorCount'        => 0,
            'ParanoidCount'     => 0,
            'TotalUpload'       => 0,
            'TotalDownload'     => 0,
            'TopLevelUpload'    => 0,
            'TopLevelDownload'  => 0,
        ];

        $classSummary = [];
        $classes = $master->repos->permissions->getClasses();
        foreach ($classes as $classID => $val) {
            $classSummary[$classID] = 0;
        }

        foreach ($invitees as $userID => &$invitee) {
            $invitee->user = $master->repos->users->load($userID);
            if ($invitee->user === false) {
                unset($invitees[$userID]);
                continue;
            }


            // Do stats
            $tree['Count']++;

            if ($invitee->TreeLevel > $tree['MaxTreeLevel']) {
                $tree['MaxTreeLevel'] = $invitee->TreeLevel;
            }

            if ($invitee->TreeLevel == $tree['BaseTreeLevel']) {
                $tree['Branches']++;
                $tree['TopLevelUpload'] += $invitee->user->legacy['Uploaded'];
                $tree['TopLevelDownload'] += $invitee->user->legacy['Downloaded'];
            }

            $classSummary[$invitee->user->class->ID]++;
            if ($invitee->user->legacy['Enabled'] == 2) {
                $tree['DisabledCount']++;
            }
            if ($invitee->user->legacy['Donor']) {
                $tree['DonorCount']++;
            }
            if (check_paranoia(['uploaded', 'downloaded'], $invitee->user->legacy['Paranoia'], $invitee->user->class->Level)) {
                $tree['TotalUpload'] += $invitee->user->legacy['Uploaded'];
                $tree['TotalDownload'] += $invitee->user->legacy['Downloaded'];
            } else {
                $tree['ParanoidCount']++;
            }
        }

        echo $master->render->template(
            'snippets/invite_tree.html.twig',
            [
                'tree'         => $tree,
                'invitees'     => $invitees,
                'page'         => $page,
                'classSummary' => $classSummary,
                'treeLevel'    => $treeLevel,
            ]
        );
    }
}
