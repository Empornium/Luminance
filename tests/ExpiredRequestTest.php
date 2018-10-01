<?php

use PHPUnit\Framework\TestCase;

class ExpiredRequestTest extends TestCase
{
    public $defaultRequest = [
        "RequestID"   => 1,
        "Title"       => "My Request Title",
        "UserID"      => 1,
        "Bounty"      => 1073741824,
        "OwnerID"     => 1,
        "Description" => "My Request Description",
        "CategoryID"  => 1,
        "Image"       => "My Request Url",
        "Tags"        => "tag1 tag2 tag3"
    ];

    public function setUp()
    {
        require_once SERVER_ROOT.'/sections/requests/functions.php';
    }

    public function testUserIsOwner()
    {
        $request = $this->defaultRequest;
        //$result  = expired_pm($request);
    }
}