<?php
namespace Luminance\Services;

use Luminance\Core\Service;
use Luminance\Errors\InternalError;

class Repos extends Service {

    protected $repositories = [];

    protected static $repos = [
        'clientuseragents' => 'ClientUserAgentRepository',
        'clientaccepts'    => 'ClientAcceptRepository',
        'clientscreens'    => 'ClientScreenRepository',
        'ips'              => 'IPRepository',
        'sessions'         => 'SessionRepository',
        'stylesheets'      => 'StylesheetRepository',
        'users'            => 'UserRepository',
        'emails'           => 'EmailRepository',
        'invites'          => 'InviteRepository',
        'options'          => 'OptionRepository',
        'permissions'      => 'PermissionRepository',
        'floods'           => 'RequestFloodRepository',
        'restrictions'     => 'RestrictionRepository',
    ];

    public function __isset($name) {
        return array_key_exists($name, self::$repos);
    }

    public function __get($name) {
        $repository = $this->get_repository($name);
        return $repository;
    }

    public function get_repository($name) {
        if ($this->__isset($name)) {
            return $this->master->getRepository(self::$repos[$name]);
        } else {
            throw new InternalError("No such repository: {$name}");
        }
    }
}
