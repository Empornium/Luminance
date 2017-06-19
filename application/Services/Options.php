<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Entities\Option;

class Options extends Service {

    protected $cache;

    /*
     *  Options array required values:
     * value          - default value for option
     * section        - section in GUI
     * type           - datatype for validation
     * description    - description for GUI
     *
     *  Options array optional values:
     * displayRow     - optional row for GUI
     * displayCol     - optional col for GUI
     * perm           - optional permission check
     * updateTracker  - optional update tracker with change
     * validation     - optional additional validation
     */

    protected $defaultOptions = [
        'ErrorLoggingFreqS'     => ['value' => 3600, 'section' => 'tracker', 'displayRow' => 1, 'displayCol' => 2, 'type' => 'int',                                        'description' => 'Tracker errors log freq'],
        'MFDReviewHours'        => ['value' => 24,   'section' => 'legacy',  'displayRow' => 1, 'displayCol' => 1, 'type' => 'int',  'perm' => 'torrents_review_manage',   'description' => 'MFD fix time (hours)'],
        'MFDAutoDelete'         => ['value' => true, 'section' => 'legacy',  'displayRow' => 1, 'displayCol' => 2, 'type' => 'bool', 'perm' => 'torrents_review_manage',   'description' => 'Auto Delete MFD Torrents'],
        'DeleteRecordsMins'     => ['value' => 360,  'section' => 'legacy',  'displayRow' => 2, 'displayCol' => 1, 'type' => 'int',  'perm' => 'admin_manage_cheats',      'description' => 'Speedrecord keep time (mins)'],
        'KeepSpeed'             => ['value' => 512,  'section' => 'legacy',  'displayRow' => 2, 'displayCol' => 2, 'type' => 'int',  'perm' => 'admin_manage_cheats',      'description' => 'Speedcheat threshold (bytes/s)'],
        'AvatarWidth'           => ['value' => 150,  'section' => 'legacy',  'displayRow' => 3, 'displayCol' => 1, 'type' => 'int',                                        'description' => 'Default Avatar Width (pix)'],
        'AvatarHeight'          => ['value' => 250,  'section' => 'legacy',  'displayRow' => 3, 'displayCol' => 2, 'type' => 'int',                                        'description' => 'Default Avatar Height (pix)'],
        'AvatarSizeKiB'         => ['value' => 1024, 'section' => 'legacy',  'displayRow' => 3, 'displayCol' => 3, 'type' => 'int',                                        'description' => 'Max Avatar Size (KiB)'],
        'MinTagLength'          => ['value' => 3,    'section' => 'legacy',  'displayRow' => 4, 'displayCol' => 1, 'type' => 'int',                                        'description' => 'Minimum length of tags'],
        'MaxTagLength'          => ['value' => 32,   'section' => 'legacy',  'displayRow' => 4, 'displayCol' => 2, 'type' => 'int',                                        'description' => 'Maximum length of tags'],
        'IntroPMArticle'        => ['value' => '',   'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 1, 'type' => 'string', 'validation' => ['minlength' => 0], 'description' => 'Tag link for article with intro PM'],
        'EnableUploads'         => ['value' => true, 'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 2, 'type' => 'bool',                                       'description' => 'Global enable for uploads'],
        'EnableDownloads'       => ['value' => true, 'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 3, 'type' => 'bool',                                       'description' => 'Global enable for downloads'],
    ];


    protected static $useRepositories = [
        'options'   => 'OptionRepository',
    ];

    protected static $useServices = [];

    public function __construct(Master $master) {
        parent::__construct($master);

        /*
         * If options table does not exist PDO will throw
         * an exception, catch it but do nothing with it.
         */
        try {
            $this->cache = $this->defaultOptions;
            foreach(array_keys($this->cache) as $name) {
                $option = $this->options->load($name);
                if (!is_null($option)) {
                    $this->cache[$name]['value'] = $option->Value;
                }
            }
        } catch(\PDOException $e) {}
    }

    public function getSections() {
        $sections = [];
        foreach ($this->cache as $name => $option) {
            if (!in_array($option['section'], $sections))
                $sections[] = $option['section'];
        }
        sort($sections);
        return $sections;
    }

    /* static compare function to sort by display: */
    static function compareDisplay($optiona, $optionb)
    {
        if ($optiona['displayRow'] == $optionb['displayRow']) {
            if ($optiona['displayCol'] == $optionb['displayCol']) return 0;
            return ($optiona['displayCol'] > $optionb['displayCol']) ? +1 : -1;
        }
        return ($optiona['displayRow'] > $optionb['displayRow']) ? +1 : -1;
    }

    public function getAll($section = null) {
        if (is_null($section)) {
            return $this->cache;
        } else {
            $options = [];
            foreach ($this->cache as $name => $option) {
                if ($option['section'] === $section)
                    $options[$name] = $option;
            }
            uasort($options, ['self', 'compareDisplay']);
            return $options;
        }
    }

    public function register($pluginOptions) {
        // defaults last so that framework keys take priority
        $this->defaultOptions = array_merge($pluginOptions, $this->defaultOptions);
        $this->cache          = array_merge($pluginOptions, $this->cache);

        /*
         * If options table does not exist PDO will throw
         * an exception, catch it but do nothing with it.
         */
        try {
            foreach(array_keys($pluginOptions) as $name) {
                $option = $this->options->load($name);
                if (!is_null($option)) {
                    $this->cache[$name]['value'] = $option->Value;
                }
            }
        } catch(\PDOException $e) {}
    }

    public function reset() {
        $this->master->auth->checkAllowed('admin_manage_site_options');
        foreach ($this->defaultOptions as $option => $default) {
            // use set in case tracker needs poked
            $this->__set($option, $default['value']);
        }
    }

//    protected function settype($option) {
//        if (is_array($option['type'])) {
//            $type  = 'array';
//        } else {
//            $type  = $option['type'];
//        }
//        $value = $options['value'];
//        switch ($type) {
//            case 'date':  break;
//            case 'enum': break;
//            default:
//                settype($type, $value);
//        }
//        return $value;
//    }

    public function __get($name) {
        if (array_key_exists($name, $this->cache)) {
            //$option = $this->cache[$name];
            //return $this->settype($option, $this->cache[$name]);
            return $this->cache[$name]['value'];
        } else {
            return $this->$name;
        }
    }

    public function __set($name, $value) {
        if (array_key_exists($name, $this->defaultOptions)) {
            $this->master->auth->checkAllowed('admin_manage_site_options');
            if ($this->cache[$name]['type'] == 'date')
                $value = strtotime($value);
            if ($this->cache[$name]['value'] != $value) {
                if (isset($this->cache[$name]['perm'])) {
                    $this->master->auth->checkAllowed($this->cache[$name]['perm']);
                }
                $option = $this->options->load($name);
                if ($this->defaultOptions[$name]['value'] == $value) {
                    if (!is_null($option)) {
                        $this->options->delete($option);
                    }
                } else {
                    if(is_null($option)) {
                        $option = new Option();
                        $option->Name    = $name;
                    }
                    $option->Value = $value;
                    $this->cache[$name]['value'] = $value;
                    $this->options->save($option);
                }

                if ($this->cache[$name]['updateTracker']) {
                    // Cludge, we can't use tracker service in the normal way or
                    // it will introduce a circular dependency
                    $this->master->tracker->options($name, $value);
                }

            }
        } else {
            $this->$name = $value;
        }
    }

    public function __isset($section_name) {
        if (is_array($this->cache[$section_name])) {
            return true;
        }
    }

}
