<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Entities\Option;

class Options extends Service {

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

    protected static $defaultOptions = [
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
        'MinTagNumber'          => ['value' => 1,    'section' => 'legacy',  'displayRow' => 4, 'displayCol' => 3, 'type' => 'int',                                        'description' => 'Minimum number of tags'],
        'IntroPMArticle'        => ['value' => '',   'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 1, 'type' => 'string', 'validation' => ['minlength' => 0], 'description' => 'Tag link for article with intro PM'],
        'EnableUploads'         => ['value' => true, 'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 2, 'type' => 'bool',                                       'description' => 'Global enable for uploads'],
        'EnableDownloads'       => ['value' => true, 'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 3, 'type' => 'bool',                                       'description' => 'Global enable for downloads'],
        'LeakingClients'        => ['value' => 3,    'section' => 'legacy',  'displayRow' => 6, 'displayCol' => 1, 'type' => 'int',                                        'description' => 'Passkey leak detection client threshold'],
        'LeakingIPs'            => ['value' => 10,   'section' => 'legacy',  'displayRow' => 6, 'displayCol' => 2, 'type' => 'int',                                        'description' => 'Passkey leak detection IP threshold'],
        'MinCreateBounty'       => ['value' => 1024*1024*1024,    'section' => 'legacy',  'displayRow' => 7, 'displayCol' => 1, 'type' => 'int',                           'description' => 'Minimum request starting bounty'],
        'MinVoteBounty'         => ['value' => 100*1024*1024,     'section' => 'legacy',  'displayRow' => 7, 'displayCol' => 2, 'type' => 'int',                           'description' => 'Minimum request voting bounty'],
        'ImagesCheck'           => ['value' => true, 'section' => 'legacy',  'displayRow' => 8, 'displayCol' => 1, 'type' => 'bool',                                       'description' => 'Enable images checking in posts'],
        'MaxImagesCount'        => ['value' => 10,   'section' => 'legacy',  'displayRow' => 8, 'displayCol' => 2, 'type' => 'int',                                        'description' => 'Max. number of images in posts'],
        'MaxImagesWeight'       => ['value' => 10,   'section' => 'legacy',  'displayRow' => 8, 'displayCol' => 3, 'type' => 'int',                                        'description' => 'Max. size for images in posts (MB)'],
        'ImagesCheckMinClass'   => ['value' => 0,    'section' => 'legacy',  'displayRow' => 8, 'displayCol' => 4, 'type' => 'int',                                        'description' => 'Disable for users above this rank'],
    ];

    protected static $useRepositories = [
        'options'   => 'OptionRepository',
    ];

    protected static $useServices = [];

    public function __construct(Master $master) {
        parent::__construct($master);
    }

    public function getSections() {
        $sections = [];
        foreach (static::$defaultOptions as $name => $option) {
            if (!in_array($option['section'], $sections))
                $sections[] = $option['section'];
        }
        sort($sections);
        return $sections;
    }

    /* static compare function to sort by display: */
    private static function compareDisplay($optiona, $optionb) {
        if ($optiona['displayRow'] == $optionb['displayRow']) {
            if ($optiona['displayCol'] == $optionb['displayCol']) return 0;
            return ($optiona['displayCol'] > $optionb['displayCol']) ? +1 : -1;
        }
        return ($optiona['displayRow'] > $optionb['displayRow']) ? +1 : -1;
    }

    public function getAll($section = null) {
        $options = static::$defaultOptions;
        foreach (array_keys($options) as $name) {
            $options[$name]['value'] = $this->__get($name);
        }

        if (is_null($section)) {
            return $options;
        } else {
            foreach ($options as $name => $option) {
                if ($option['section'] !== $section)
                    unset($options[$name]);
            }
            uasort($options, ['self', 'compareDisplay']);
            return $options;
        }
    }

    public static function register($pluginOptions) {
        // defaults last so that framework keys take priority
        static::$defaultOptions = array_merge($pluginOptions, static::$defaultOptions);
    }

    public function reset() {
        $this->master->auth->checkAllowed('admin_manage_site_options');
        foreach (static::$defaultOptions as $option => $default) {
            // use set in case tracker needs poked
            $this->__set($option, $default['value']);
        }
    }

    protected function set_type($option) {
        $type = static::$defaultOptions[$option->Name]['type'];
        $value = $option->Value;

        switch ($type) {
            case 'enum':
                $value = (string) $value;
                break;
            case 'date':
                //$value = new \DateTime($value);
                break;
            case 'int':
            case 'bool':
            case 'float':
            case 'double':
            case 'string':
                settype($value, $type);
                break;
        }
        return $value;
    }

    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        } else {
            try {
                $option = $this->options->load($name);
                if ($option instanceof Option) {
                    return $this->set_type($option);
                } else {
                    return static::$defaultOptions[$name]['value'];
                }
            } catch (\PDOException $e) {
                return static::$defaultOptions[$name]['value'];
            }
        }
    }

    public function get_type($name) {
        if (array_key_exists($name, static::$defaultOptions)) {
            return static::$defaultOptions['type'];
        }

        return null;
    }

    public function __set($name, $value) {
        if (array_key_exists($name, static::$defaultOptions)) {
            $this->master->auth->checkAllowed('admin_manage_site_options');
            if (static::$defaultOptions[$name]['type'] == 'date')
                $value = strtotime($value);
            if (isset(static::$defaultOptions[$name]['perm'])) {
                $this->master->auth->checkAllowed(static::$defaultOptions[$name]['perm']);
            }
            $option = $this->options->load($name);
            if (static::$defaultOptions[$name]['value'] == $value) {
                if ($option instanceof Option) {
                    $this->options->delete($option);
                }
            } else {
                if (!($option instanceof Option)) {
                    $option = new Option();
                    $option->Name = $name;
                }
                $option->Value = $value;
                $this->options->save($option);
            }

            if (static::$defaultOptions[$name]['updateTracker']) {
                // Cludge, we can't use tracker service in the normal way or
                // it will introduce a circular dependency
                $this->master->tracker->options($name, $value);
            }
        } else {
            $this->$name = $value;
        }
    }

    public function __isset($section_name) {
        if (is_array(static::$defaultOptions[$section_name])) {
            return true;
        }
    }
}
