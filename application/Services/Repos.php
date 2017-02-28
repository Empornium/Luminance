<?php
namespace Luminance\Services;

use Luminance\Errors\InternalError;

class Repos extends Service {

    protected $repositories = [];

    public function __get($name) {
        $repository = $this->get_repository($name);
        return $repository;
    }

    public function get_repository($name) {
        switch ($name) {
            case 'clientuseragents':
                $cls = 'ClientUserAgentRepository';
                break;
            case 'clientaccepts':
                $cls = 'ClientAcceptRepository';
                break;
            case 'clientscreens':
                $cls = 'ClientScreenRepository';
                break;
            case 'ips':
                $cls = 'IPRepository';
                break;
            case 'sessions':
                $cls = 'SessionRepository';
                break;
            case 'stylesheets':
                $cls = 'StylesheetRepository';
                break;
            case 'users':
                $cls = 'UserRepository';
                break;
            case 'emails':
                $cls = 'EmailRepository';
                break;
            case 'invites':
                $cls = 'InviteRepository';
                break;
            default:
                throw new InternalError("No such repository: {$name}");
        }
        return $this->master->getRepository($cls);
    }

}
