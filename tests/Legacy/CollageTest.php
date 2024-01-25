<?php

use Luminance\Legacy\Collage;
use PHPUnit\Framework\TestCase;

class CollageTest extends TestCase
{
    public function testCannotAddTorrentsIfLocked()
    {
        $collage = [
            'Locked' => 1,
        ];

        $this->assertFalse(Collage::canAnyoneAddTorrents($collage));
    }

    public function testCannotAddTorrentsIfMaxGroupsReached()
    {
        $collage = [
            'MaxGroups' => 2,
            'NumTorrents' => 2,
        ];

        $this->assertFalse(Collage::canAnyoneAddTorrents($collage));
    }

    public function testCanAddTorrentsIfMaxGroupsNotSet()
    {
        $collage = [
            'MaxGroups' => 0,
            'NumTorrents' => 2,
        ];

        $this->assertTrue(Collage::canAnyoneAddTorrents($collage));
    }

    public function testCanEditOwnCollage()
    {
        $collage = [
            'UserID' => 100
        ];
        $user = [
            'ID' => 100,
            'Class' => 0,
            'Groups' => []
        ];

        $this->assertTrue(Collage::canEditCollage($collage, $user));
    }

    public function testCanEditCollageThroughClass()
    {
        $collage = [
            'UserID' => 5,
            'Permissions' => 10
        ];
        $user = [
            'ID' => 6,
            'Class' => 10,
            'Groups' => []
        ];

        $this->assertTrue(Collage::canEditCollage($collage, $user));
    }

    public function testCannotEditCollageThroughClassIfPermissionsNotSet()
    {
        $collage = [
            'UserID' => 5,
            'Permissions' => 0
        ];
        $user = [
            'ID' => 6,
            'Class' => 10,
            'Groups' => []
        ];

        $this->assertFalse(Collage::canEditCollage($collage, $user));
    }

    public function testCanEditCollageThroughGroup()
    {
        $collage = [
            'UserID' => 5,
            'Permissions' => 10,
            'Groups' => '2,3'
        ];
        $user = [
            'ID' => 6,
            'Class' => 0,
            'Groups' => [2]
        ];

        $this->assertTrue(Collage::canEditCollage($collage, $user));
    }

    public function testCannotEditCollage()
    {
        $collage = [
            'UserID' => 5,
            'Permissions' => 10,
            'Groups' => '2,3'
        ];
        $user = [
            'ID' => 6,
            'Class' => 0,
            'Groups' => [4]
        ];

        $this->assertFalse(Collage::canEditCollage($collage, $user));
    }

    public function testExtractPostGroupData()
    {
        $post = [];

        $data = Collage::getGroupData($post);
        $this->assertEmpty($data);

        $post = [
            'groups' => [ '2' ]
        ];
        $data = Collage::getGroupData($post);
        $this->assertSame(1, count($data));
        $this->assertTrue($data[2]);

        $post = [
            'groups' => [ '2', '3' ]
        ];
        $data = Collage::getGroupData($post);
        $this->assertSame(2, count($data));
        $this->assertTrue($data[2]);
        $this->assertTrue($data[3]);
    }

    public function testNormalizeGroupData()
    {
        $source = [
            [
                'ID' => 1,
                'GroupID' => 234,
            ],
            [
                'ID' => 2,
                'GroupID' => 345,
            ]
        ];

        $data = Collage::normalizeCollageGroupData($source);
        $this->assertSame(2, count($data));
        $this->assertTrue($data[234]);
        $this->assertTrue($data[345]);
    }

    public function testGroupDataDiffNoOp()
    {
        $db = [
            123 => true,
            234 => true,
        ];
        $form = [
            123 => true,
            234 => true,
        ];

        $ops = Collage::getDataDiffAsOperations($db, $form);
        $this->assertEmpty($ops);
    }

    public function testGroupDataDiffInsert()
    {
        $db = [
            123 => true,
        ];
        $form = [
            123 => true,
            234 => true,
        ];

        $ops = Collage::getDataDiffAsOperations($db, $form);
        $this->assertSame(1, count($ops));
        $this->assertSame(1, count($ops['insert']));
        $this->assertTrue($ops['insert'][234]);
    }

    public function testGroupDataDiffDelete()
    {
        $db = [
            123 => true,
            234 => true,
        ];
        $form = [
            123 => true,
        ];

        $ops = Collage::getDataDiffAsOperations($db, $form);
        $this->assertSame(1, count($ops));
        $this->assertSame(1, count($ops['delete']));
        $this->assertTrue($ops['delete'][234]);
    }

    public function testGroupDataDiffDeleteMultiple()
    {
        $db = [
            123 => true,
            234 => true,
        ];
        $form = [];

        $ops = Collage::getDataDiffAsOperations($db, $form);
        $this->assertSame(1, count($ops));
        $this->assertSame(2, count($ops['delete']));
    }
}
