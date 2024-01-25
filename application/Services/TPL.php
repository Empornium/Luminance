<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Services\Debug;

class TPL extends Service {
    # Basically a wrapper around the Twig templating engine

    public static $templates = [];
    public static $time = 0.0;
    protected $loader;
    protected $twig;
    public $profile;

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->loader = new \Twig_Loader_Filesystem($this->master->applicationPath . '/Templates');
        # Allow global overrides
        $this->loader->prependPath($this->master->applicationPath . '/Overrides');
        $this->loader->addPath($this->master->resourcePath . '/icons', 'icon');
        $options = [];
        if ($this->master->settings->paths->template_cache && $this->master->request->cli === false) {
            $options['cache'] = $this->master->settings->paths->template_cache;
            $options['auto_reload'] = true;
        }
        if ($this->master->settings->site->debug_mode) {
            $options['debug'] = true;
            $options['strict_variables'] = true;
        }

        $options['strict_variables'] = false;
        $this->twig = new \Twig_Environment($this->loader, $options);
        $this->addExtensions();
    }

    public function decode($value) {
        return html_entity_decode($value, ENT_QUOTES|ENT_HTML5, 'UTF-8');
    }

    // phpcs:ignore
    public function var_dump($value) {
        return var_export($value, true);
    }

    public function addTemplatePath($templateDir, $namespace) {
        $this->loader->addPath($templateDir, $namespace);
    }

    public function overrideTemplatePath($tplPath) {
        $this->loader->prependPath($tplPath);
    }


    public function multitrim($str) {
        # Leeloo Dallas?
        $strs = explode(PHP_EOL, $str);
        foreach ($strs as &$str) {
            $str = trim($str);
        }
        return implode(' ', $strs);
    }

    public function shuffle($array) {
        shuffle($array);
        return $array;
    }

    /**
     * $context is a special array which hold all know variables inside
     * If $key is not defined unset the whole variable inside context
     * If $key is set test if $context[$variable] is defined if so unset $key inside multidimensional array
     */
    public function unset(&$context, $variable, $key = null) {
        if ($key === null) {
            unset($context[$variable]);
        } else {
            if (isset($context[$variable])) {
                unset($context[$variable][$key]);
            }
        }
    }

    public function static($class, $property) {
        if (property_exists($class, $property)) {
            return $class::$$property;
        }
        return null;
    }

    public function secure($link) {
        return preg_replace('/^http:/', 'https:', $link);
    }

    public function instanceof($var, $instance) {
        return $var instanceof $instance;
    }

    private function addExtensions() {
        $filters = [
            'format_size' => 'get_size',
            'decode'      => [$this, 'decode'],
            'multitrim'   => [$this, 'multitrim'],
            'shuffle'     => [$this, 'shuffle'],
        ];

        $functions = [
            'var_dump'                => [$this, 'var_dump'],
            'static'                  => [$this, 'static'],
            'secure'                  => [$this, 'secure'],
            'unset'                   => [[$this, 'secure'], [ 'needs_context' => true ]],

            'ratio'                   => 'ratio',
            'time_diff'               => 'time_diff',
            'time_ago'                => 'time_ago',
            'hoursdays'               => 'hoursdays',
            'sqltime'                 => 'sqltime',
            'check_perms'             => 'check_perms',
            'check_paranoia'          => 'check_paranoia',
            'check_perms_here'        => 'check_perms_here',
            'check_paranoia_here'     => 'check_paranoia_here',
            'check_force_anon'        => 'check_force_anon',
            'get_article'             => 'get_article',
            'get_size'                => 'get_size',
            'get_avatar_css'          => 'get_avatar_css',
            'get_host'                => 'get_host',
            'display_ip'              => 'display_ip',
            'get_permissions_advtags' => 'get_permissions_advtags',
            'print_badges_array'      => 'print_badges_array',
            'trim_filter'             => 'trim_filter',
            'selected'                => 'selected',
            'make_secret'             => 'make_secret',
            'header_link'             => 'header_link',
            'torrent_username'        => 'torrent_username',
            'has_bookmarked'          => 'has_bookmarked',
            'make_class_string'       => 'make_class_string',
            'is_integer_string'       => 'is_integer_string',
            'get_status_icon'         => 'get_status_icon',
            'get_warning_message'     => 'get_warning_message',
            'fapping_preview'         => 'fapping_preview',
            'print_compose_staff_pm'  => 'print_compose_staff_pm',
            'trimDate'                => 'trimDate',
        ];

        $tests = [
            'instanceof'    => [$this, 'instanceof'],
        ];

        foreach ($filters as $filterName => $filterArguments) {
            $filter = new \Twig\TwigFilter($filterName, $filterArguments);
            $this->addFilter($filter);
        }

        foreach ($functions as $functionName => $functionArguments) {
            $functionOptions = [];
            if (is_array($functionArguments)) {
                # Makes some assumptions
                if (is_array($functionArguments[0])) {
                    $functionOptions   = $functionArguments[1];
                    $functionArguments = $functionArguments[0];
                }
            }
            $function = new \Twig\TwigFunction($functionName, $functionArguments, $functionOptions);
            $this->addFunction($function);
        }

        foreach ($tests as $testName => $testArguments) {
            $test = new \Twig\TwigTest($testName, $testArguments);
            $this->addTest($test);
        }

        if ($this->master->settings->site->debug_mode) {
            $this->twig->addExtension(new \Twig_Extension_Debug());
        }

        # For text and such
        $this->twig->addExtension(new \Twig_Extensions_Extension_Text());

        $this->profile = new \Twig\Profiler\Profile();
        $this->twig->addExtension(new \Twig\Extension\ProfilerExtension($this->profile));
    }

    public function render($template, $values = [], $block = null) {
        $templateStartTime=microtime(true);
        if (is_null($block)) {
            $rendered = $this->twig->render($template, $values);
        } else {
            $rendered = $this->twig->load($template);
            $rendered = $rendered->renderBlock($block, $values);
        }
        $templateEndTime=microtime(true);
        if (Debug::getEnabled()) {
            if (count(self::$templates) < 500) {
                self::$templates[] = [
                    'filename'  => $template,
                    'data'      => null, //$values,
                    'microtime' => ($templateEndTime-$templateStartTime)*1000,
                ];
            }
            self::$time+=($templateEndTime-$templateStartTime)*1000;
        }
        return $rendered;
    }

    public function display($template, $values = [], $block = null) {
        print($this->render($template, $values, $block));
    }

    public function addTest($test) {
        $this->twig->addTest($test);
    }

    public function addFunction($function) {
        $this->twig->addFunction($function);
    }

    public function addFilter($filter) {
        $this->twig->addFilter($filter);
    }
}
