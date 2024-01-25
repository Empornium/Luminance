<?php
namespace Luminance\Legacy;

class Wiki {
    /**
     * Render the block about access control in wiki Create/Edit forms.
     *
     * @param  array  $formData [description]
     *
     * @return string
     */
    public static function renderFormAccess(array $formData = []): string {
        global $master;
        global $classes;
        global $activeUser;

        $availableClasses = static::initAvailableClasses($classes, $activeUser);

        $readClasses = static::hydrateSelected($availableClasses, $formData['Read'] ?? 0);
        $editClasses = static::hydrateSelected($availableClasses, $formData['Edit'] ?? 0);

        $groups = static::hydrateGroupAccess(
            $master->repos->permissions->getGroups(),
            $formData['GroupsRead'] ?? [],
            $formData['GroupsEdit'] ?? []
        );

        return $master->tpl->render('core/wiki/form_access.html.twig', [
            'groups' => $groups,
            'readClasses' => $readClasses,
            'editClasses' => $editClasses,
        ]);
    }

    /**
     * Retrieve classes that are available to a given user.
     *
     * @param  array $classes  An initial list of classes.
     * @param  array $user
     *
     * @return array
     */
    public static function initAvailableClasses(array $classes, array $user): array {
        return array_filter($classes, function ($class) use ($user) {
            return $class['Level'] <= $user['Class'] && $class['IsUserClass'] != 0;
        });
    }

    /**
     * Add a "Selected" flag to a list of classes.
     *
     * @param  array $classes  The initial list of classes.
     * @param  int   $level    The level of the class to be marked as selected.
     *
     * @return array The modified list of classes
     */
    public static function hydrateSelected(array $classes, int $level): array {
        return array_map(function ($class) use ($level) {
            $class['Selected'] = $class['Level'] == $level ? true : false;
            return $class;
        }, $classes);
    }

    /**
     * Add an "Access" flag to a list of groups.
     *
     * @param  array  $groups  The initial list of groups.
     * @param  array  $readIDs A list of IDs of the groups that have Read access.
     * @param  array  $editIDs A list of IDs of the groups that have Edit access.
     *
     * @return array The hydrated list of groups
     */
    public static function hydrateGroupAccess(array $groups, array $readIDs = [], array $editIDs = []): array {
        foreach ($groups as &$group) {
            $group['Access'] = 'none';
        }

        foreach (array_filter($readIDs, 'is_numeric') as $id) {
            $groups[$id]['Access'] = 'read';
        }

        foreach (array_filter($editIDs, 'is_numeric') as $id) {
            $groups[$id]['Access'] = 'edit';
        }

        return $groups;
    }

    /**
     * Update groups assignment to a wiki article.
     *
     * @param  int   $articleID ID of the article to update.
     * @param  array $postData  Data from the form.
     */
    public static function applyGroupsFromPost(int $articleID, array $postData): void {
        global $master;

        $groupData = static::getGroupData($postData);
        $repo = $master->repos->wikiArticles;
        $dbData = $repo->findByArticle($articleID);
        $currentData = static::normalizeArticleGroupData($dbData);

        $operations = static::getDataDiffAsOperations($currentData, $groupData);

        foreach ($operations as $op => $data) {
            switch ($op) {
                case 'delete':
                    $repo->deleteGroupsByArticle($articleID, array_keys($data));
                    break;
                case 'update':
                    foreach ($data as $groupID => $access) {
                        $repo->updateGroupAccess($articleID, $groupID, $access);
                    }
                    break;
                case 'insert':
                    foreach ($data as $groupID => $access) {
                        $repo->createGroupAccess($articleID, $groupID, $access);
                    }
                    break;
            }
        }
    }

    /**
     * Transform post data to normalized data about groups.
     *
     * @param  array  $postData Data from the form.
     * @return array            Normalized group data.
     */
    public static function getGroupData(array $postData): array {
        $data = array_filter($postData, function($key) {
            return preg_match('/^groups_[0-9]+$/', $key) > 0;
        }, ARRAY_FILTER_USE_KEY);

        $groupData = [];
        foreach ($data as $key => $value) {
            $matches = [];
            preg_match('/^groups_([0-9]+)$/', $key, $matches);
            $groupID = $matches[1];
            $groupData[$groupID] = $value;
        }

        return $groupData;
    }

    /**
     * Transform data taken from DB into something easier to manipulate.
     *
     * @param  array $data  Raw data from DB.
     * @return array        Normalized data.
     */
    public static function normalizeArticleGroupData(array $data): array {
        $normalized = [];
        foreach ($data as $row) {
            $normalized[$row['GroupID']] = $row['CanEdit'] == '1' ? 'edit' : 'read';
        }

        return $normalized;
    }

    /**
     * Figure out diff between form data and current db data.
     *
     * @param  array  $currentData  DB data.
     * @param  array  $formData     Group data derived from the form.
     * @return array                List of operations to run.
     */
    public static function getDataDiffAsOperations(array $currentData, array $formData): array {
        $allGroupIDs = array_unique(array_merge(
            array_keys($currentData),
            array_keys($formData))
        );

        $ops = [];

        foreach ($allGroupIDs as $groupID) {
            if (!isset($currentData[$groupID])) {
                if (isset($formData[$groupID]) && $formData[$groupID] != 'none') {
                    $ops['insert'][$groupID] = $formData[$groupID];
                }
                continue;
            }
            if (!isset($formData[$groupID]) || $formData[$groupID] == 'none') {
                $ops['delete'][$groupID] = true;
                continue;
            }
            if ($currentData[$groupID] == $formData[$groupID]) {
                continue;
            }
            $ops['update'][$groupID] = $formData[$groupID];
        }

        return $ops;
    }

    /**
     * Retrieve a list of names using group IDs.
     *
     * @param  array $groupIDs  A list of group IDs.
     * @return array            A list of corresponding group names.
     */
    public static function groupIdsToReadable(array $groupIDs): array {
        global $master;

        $groups = $master->repos->permissions->getClassesByIDs($groupIDs);

        return array_map(function($group) {
            return $group['Name'];
        }, $groups);
    }

    /**
     * Check whether the given user can edit this article.
     *
     * @param  array $article  The article to be checked; result of Alias::article
     * @param  array $user     A user's data
     * @return bool
     */
    public static function canEditArticle(array $article, array $user): bool {
        if ($user['Class'] >= $article['MinClassEdit']) {
            return true;
        }

        $groups = explode(',', $article['GroupsEdit']);

        return count(array_intersect($groups, $user['Groups'])) > 0;
    }

    /**
     * Check whether the given user can read this article.
     *
     * @param  array $article  The article to be checked; result of Alias::article
     * @param  array $user     A user's data
     * @return bool
     */
    public static function canReadArticle(array $article, array $user): bool {
        if ($user['Class'] >= $article['MinClassRead']) {
            return true;
        }

        $groupsRead = explode(',', $article['GroupsRead']);
        $groupsEdit = explode(',', $article['GroupsEdit']);
        $groups = array_merge($groupsRead, $groupsEdit);

        return count(array_intersect($groups, $user['Groups'])) > 0;
    }
}
