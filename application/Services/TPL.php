<?php
namespace Luminance\Services;

use Luminance\Core\Master;

class TPL extends Service {
    # Basically a wrapper around the Twig templating engine

    protected $loader;
    protected $twig;

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->loader = new \Twig_Loader_Filesystem($master->application_path . '/templates');
        $options = [];
        if ($this->master->settings->paths->template_cache) {
            $options['cache'] = $this->master->settings->paths->template_cache;
            $options['auto_reload'] = true;
        }
        $this->twig = new \Twig_Environment($this->loader, $options);
        $this->add_extensions();
    }

    public function add_template_path($templateDir, $namespace) {
        $this->loader->addPath($templateDir, $namespace);
    }

    private function add_extensions() {
        $filter = new \Twig_SimpleFilter('format_size', 'get_size');
        $this->twig->addFilter($filter);

        $func = new \Twig_SimpleFunction('ratio', 'ratio');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('time_diff', 'time_diff');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('check_perms', 'check_perms');
        $this->twig->addFunction($func);
    }

    public function render($template, $values = []) {
        return $this->twig->render($template, $values);
    }

    public function display($template, $values = []) {
        print($this->render($template, $values));
    }
}
