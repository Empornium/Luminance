<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

class TPL extends Service {
    # Basically a wrapper around the Twig templating engine

    protected $loader;
    protected $twig;

    protected static $useServices = [
        'settings'      => 'Settings',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->loader = new \Twig_Loader_Filesystem($this->master->application_path . '/Templates');
        $options = [];
        if ($this->master->settings->paths->template_cache) {
            $options['cache'] = $this->master->settings->paths->template_cache;
            $options['auto_reload'] = true;
        }
        if ($this->master->settings->site->debug_mode) {
            $options['debug'] = true;
        } else {
            //$options['strict_variables'] = true;
        }

        $options['strict_variables'] = false;
        $this->twig = new \Twig_Environment($this->loader, $options);
        $this->add_extensions();
    }

    public function get_icon($set, $symbol) {
        return '<svg class="'.$set.'" data-src="/static/common/'.$set.'.svg?v='.$this->master->render->public_file_mtime('/static/common/'.$set.'.svg').'#'.$symbol.'"></svg>';
    }

    public function decode($value) {
        return html_entity_decode($value);
    }

    public function add_template_path($templateDir, $namespace) {
        $this->loader->addPath($templateDir, $namespace);
    }

    public function override_template_path($tplPath) {
        $this->loader->prependPath($tplPath);
    }

    private function add_extensions() {
        $filter = new \Twig_SimpleFilter('format_size', 'get_size');
        $this->twig->addFilter($filter);

        $filter = new \Twig_SimpleFilter('decode', [$this, 'decode']);
        $this->twig->addFilter($filter);

        $func = new \Twig_SimpleFunction('ratio', 'ratio');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('time_diff', 'time_diff');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('hoursdays', 'hoursdays');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('sqltime', 'sqltime');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('check_perms', 'check_perms');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('check_paranoia', 'check_paranoia');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('check_perms_here', 'check_perms_here');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('check_paranoia_here', 'check_paranoia_here');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('check_force_anon', 'check_force_anon');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('get_article', 'get_article');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('get_icon', [$this, 'get_icon']);
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('get_size', 'get_size');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('get_avatar_css', 'get_avatar_css');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('get_host', 'get_host');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('display_ip', 'display_ip');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('get_permissions_advtags', 'get_permissions_advtags');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('print_badges_array', 'print_badges_array');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('trim_filter', 'trim_filter');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('selected', 'selected');
        $this->twig->addFunction($func);

        $func = new \Twig_SimpleFunction('make_secret', 'make_secret');
        $this->twig->addFunction($func);

        if ($this->master->settings->site->debug_mode) {
            $this->twig->addExtension(new \Twig_Extension_Debug());
        }
    }

    public function render($template, $values = [], $block = null) {
        if (is_null($block)) {
            return $this->twig->render($template, $values);
        } else {
            $template = $twig->load($template);
            return $template->renderBlock($block, $values);
        }
    }

    public function display($template, $values = [], $block = null) {
        print($this->render($template, $values));
    }
}
