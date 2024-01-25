<?php
namespace Luminance\Services;

use Luminance\Core\Service;

class Repos extends Service {

    protected $systemRepositories = null;

    public function __isset($name) {
        return true;
    }

    public function __get($name) {
        if (is_null($this->systemRepositories) === true) {
            # Take repo names from entities rather than directly from repos
            # so we can support anonymous repositories
            foreach (glob($this->master->applicationPath."/Entities/*.php") as $repo) {
                $repo = str_replace($this->master->applicationPath.'/Entities/', '', $repo);
                $repo = str_replace('.php', 'Repository', $repo);
                $shortRepoName = mb_strtolower(str_replace('Repository', 's', $repo));
                if (str_ends_with($shortRepoName, 'ys')) {
                    # Don't do ies if there's a vowel before the y
                    if (!in_array(substr($shortRepoName, -3, 1), ['a', 'e', 'i', 'o', 'u'])) {
                        $shortRepoName = rtrim($shortRepoName, 'ys');
                        $shortRepoName .= 'ies';
                    }
                }
                $this->systemRepositories[$shortRepoName] = $repo;
            }
        }

        $name = mb_strtolower($name);

        return $this->master->getRepository($this->systemRepositories[$name]);
    }
}
