<?php

use Luminance\Legacy\Wiki;
use Luminance\Plugins\Legacy\LegacyPlugin;
use PHPUnit\Framework\TestCase;

class WikiTest extends TestCase
{
    protected $classes = [
        [ 'Level' => 1, 'IsUserClass' => 1 ],
        [ 'Level' => 4, 'IsUserClass' => 1 ],
        [ 'Level' => 7, 'IsUserClass' => 1 ],
    ];

    protected $groups = [
        123 => [ 'ID' => 123, 'Name' => 'Dummy' ],
        234 => [ 'ID' => 234, 'Name' => 'AnotherGroup' ],
        345 => [ 'ID' => 345, 'Name' => 'The Best Group' ],
    ];

    protected $articleWithGroups = [
        'MinClassRead' => 1,
        'MinClassEdit' => 1,
        'GroupsRead' => '2,3',
        'GroupsEdit' => '4,5',
    ];

    public function testUserCannotAccessHigherClasses()
    {
        $user = [ 'Class' => 0 ];
        $this->assertEmpty(Wiki::initAvailableClasses($this->classes, $user));

        $user = [ 'Class' => 4 ];
        $classes = Wiki::initAvailableClasses($this->classes, $user);
        $this->assertSame(2, count($classes));

        $user = [ 'Class' => 10 ];
        $classes = Wiki::initAvailableClasses($this->classes, $user);
        $this->assertSame(3, count($classes));
    }

    public function testGroupClassesShouldNotBeShown()
    {
        $classes = [
            [ 'Level' => 10, 'IsUserClass' => 1 ],
            [ 'Level' => 10, 'IsUserClass' => 0 ],
        ];

        $user = [ 'Class' => 1000 ];
        $available = Wiki::initAvailableClasses($classes, $user);
        $this->assertSame(1, count($available));
    }

    public function testHydrateSelectedUndefinedClass()
    {
        $classes = Wiki::hydrateSelected($this->classes, 2);
        foreach ($classes as $class) {
            $this->assertSame(false, $class['Selected']);
        }
    }

    public function testHydrateSelectedSpecificClass()
    {
        $classes = Wiki::hydrateSelected($this->classes, 4);
        $this->assertSame(false, $classes[0]['Selected']);
        $this->assertSame(true, $classes[1]['Selected']);
        $this->assertSame(false, $classes[2]['Selected']);
    }

    public function testHydrateGroupAccessBadDataIgnored()
    {
        $groups = Wiki::hydrateGroupAccess($this->groups, ["", "abc"]);
        $this->assertSame(count($groups), count($this->groups));

        $groups = Wiki::hydrateGroupAccess($this->groups, [], ["", "abc"]);
        $this->assertSame(count($groups), count($this->groups));
    }

    public function testHydrateGroupAccessDefault()
    {
        $groups = Wiki::hydrateGroupAccess($this->groups);
        foreach ($groups as $group) {
            $this->assertSame('none', $group['Access']);
        }
    }

    public function testHydrateGroupAccessRead()
    {
        $groups = Wiki::hydrateGroupAccess($this->groups, [234]);
        $this->assertSame('none', $groups[123]['Access']);
        $this->assertSame('read', $groups[234]['Access']);
    }

    public function testHydrateGroupAccessEdit()
    {
        $groups = Wiki::hydrateGroupAccess($this->groups, [], [234]);
        $this->assertSame('none', $groups[123]['Access']);
        $this->assertSame('edit', $groups[234]['Access']);
    }

    public function testHydrateGroupAccessReadEdit()
    {
        $groups = Wiki::hydrateGroupAccess($this->groups, [234], [234]);
        $this->assertSame('none', $groups[123]['Access']);
        $this->assertSame('edit', $groups[234]['Access']);

        $groups = Wiki::hydrateGroupAccess($this->groups, [234], [345]);
        $this->assertSame('none', $groups[123]['Access']);
        $this->assertSame('read', $groups[234]['Access']);
        $this->assertSame('edit', $groups[345]['Access']);
    }

    public function testExtractPostGroupData()
    {
        $post = [
            'title' => 'random',
            'groups' => 'none',
            'groups123' => 'none',
            'groups_123_bad' => 'none',
            'groups_123' => 'read',
            'groups_234' => 'edit',
            'groups_345' => 'none',
        ];

        $data = Wiki::getGroupData($post);
        $this->assertSame(3, count($data));
        $this->assertSame('read', $data[123]);
        $this->assertSame('edit', $data[234]);
        $this->assertSame('none', $data[345]);
    }

    public function testNormalizeArticleGroupData()
    {
        $source = [
            [
                'ID' => 1,
                'GroupID' => 234,
                'CanEdit' => '1',
            ],
            [
                'ID' => 2,
                'GroupID' => 345,
                'CanEdit' => '0',
            ]
        ];

        $data = Wiki::normalizeArticleGroupData($source);
        $this->assertSame(2, count($data));
        $this->assertSame('edit', $data[234]);
        $this->assertSame('read', $data[345]);
    }

    public function testGroupDataDiffNoOp()
    {
        $db = [
            123 => 'read',
            234 => 'edit',
        ];
        $form = [
            234 => 'edit',
            123 => 'read',
        ];

        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertEmpty($ops);

        $form[345] = 'none';
        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertEmpty($ops);

        $db = [];
        $form = [
            123 => 'none',
        ];
        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertEmpty($ops);
    }

    public function testGroupDataDiffInsert()
    {
        $db = [
            123 => 'read',
        ];
        $form = [
            123 => 'read',
            234 => 'edit',
        ];

        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertSame(1, count($ops));
        $this->assertSame(1, count($ops['insert']));
        $this->assertSame('edit', $ops['insert'][234]);
    }

    public function testGroupDataDiffDelete()
    {
        $db = [
            123 => 'read',
            234 => 'edit',
        ];
        $form = [
            123 => 'read',
        ];

        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertSame(1, count($ops));
        $this->assertSame(1, count($ops['delete']));
        $this->assertSame(true, $ops['delete'][234]);
    }

    public function testGroupDataDiffDeleteNone()
    {
        $db = [
            123 => 'read',
        ];
        $form = [
            123 => 'none',
        ];

        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertSame(1, count($ops));
        $this->assertSame(1, count($ops['delete']));
        $this->assertSame(true, $ops['delete'][123]);
    }

    public function testGroupDataDiffUpdate()
    {
        $db = [
            123 => 'read',
        ];
        $form = [
            123 => 'edit',
        ];

        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertSame(1, count($ops));
        $this->assertSame(1, count($ops['update']));
        $this->assertSame('edit', $ops['update'][123]);
    }

    public function testGroupDataDiffDeleteMultiple()
    {
        $db = [
            123 => 'read',
            234 => 'edit',
        ];
        $form = [
            123 => 'none',
            234 => 'none',
        ];

        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertSame(1, count($ops));
        $this->assertSame(2, count($ops['delete']));
    }

    public function testGroupDataDiffUpdateAndDelete()
    {
        $db = [
            123 => 'read',
            234 => 'edit',
        ];
        $form = [
            123 => 'none',
            234 => 'read',
        ];

        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertSame(2, count($ops));
        $this->assertSame(1, count($ops['delete']));
        $this->assertSame(1, count($ops['update']));
    }

    public function testGroupDataDiffInsertAndUpdateAndDelete()
    {
        $db = [
            123 => 'read',
            234 => 'edit',
        ];
        $form = [
            123 => 'none',
            234 => 'read',
            345 => 'edit'
        ];

        $ops = Wiki::getDataDiffAsOperations($db, $form);
        $this->assertSame(3, count($ops));
        $this->assertSame(1, count($ops['delete']));
        $this->assertSame(1, count($ops['update']));
        $this->assertSame(1, count($ops['insert']));
    }

    public function testCanAccessArticleThroughClass()
    {
        $article = [
            'MinClassRead' => 1,
            'MinClassEdit' => 1,
            'GroupsRead' => null,
            'GroupsEdit' => null,
        ];

        $user = [ 'Class' => 1, 'Groups' => [] ];
        $this->assertTrue(Wiki::canReadArticle($article, $user));
        $this->assertTrue(Wiki::canEditArticle($article, $user));

        $user = [ 'Class' => 0, 'Groups' => [] ];
        $this->assertFalse(Wiki::canReadArticle($article, $user));
        $this->assertFalse(Wiki::canEditArticle($article, $user));
    }

    public function testCantAccessArticleWithoutGroup()
    {
        $user = [ 'Class' => 0, 'Groups' => [] ];
        $this->assertFalse(Wiki::canReadArticle($this->articleWithGroups, $user));
        $this->assertFalse(Wiki::canEditArticle($this->articleWithGroups, $user));
    }

    public function testCanReadArticleThroughGroup()
    {
        $user = [ 'Class' => 0, 'Groups' => [2] ];
        $this->assertTrue(Wiki::canReadArticle($this->articleWithGroups, $user));
        $this->assertFalse(Wiki::canEditArticle($this->articleWithGroups, $user));
    }

    public function testCanEditArticleThroughGroup()
    {
        $user = [ 'Class' => 0, 'Groups' => [4] ];
        $this->assertTrue(Wiki::canReadArticle($this->articleWithGroups, $user));
        $this->assertTrue(Wiki::canEditArticle($this->articleWithGroups, $user));
    }
}
