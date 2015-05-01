<?php
namespace Cohesion\Templating;

use \Cohesion\Config\Configurable;
use \Cohesion\Config\Config;
use \Cohesion\DataAccess\Cache\Cache;
use \Cohesion\Structure\View\InvalidTemplateException;
use \MissingAssetException;
use \Mustache_Engine;
use \Mustache_Loader_FilesystemLoader;
use \Mustache_LambdaHelper;
use \DateTime;
use \DateTimeZone;

class CohesionMo extends Mustache_Engine implements TemplateEngine, Configurable {

    protected $config;
    protected $versionCache;
    protected $timezone;

    public function __construct(Config $config) {
        $this->config = $config;
        $this->versionCache = $config->get('global.cache');
        if ($config->get('template.cache.ttl') !== null) {
            $templateCache = new CohesionMoCache($this->versionCache, $config->get('template.cache.ttl'));
        } else {
            $fileCacheDir = $config->get('template.cache.directory');
            if ($fileCacheDir) {
                if ($fileCacheDir[0] !== DIRECTORY_SEPARATOR) {
                    $fileCacheDir = $config->get('global.base_dir') . DIRECTORY_SEPARATOR . $fileCacheDir;
                }
                $templateCache = $fileCacheDir;
            } else {
                $templateCache = null;
            }
        }

        parent::__construct(array(
            'partials_loader' => new MustacheVariablePartialLoader(
                $config->get('global.base_dir') . DIRECTORY_SEPARATOR . $config->get('template.directory'),
                array(
                    'extension' => $config->get('template.extension')
                )
            ),
            'pragmas' => array('FILTERS'),
            'cache' => $templateCache
        ));
    }

    public function renderFromFile($template, $vars = array()) {
        $templateFile = $this->config->get('global.base_dir') . DIRECTORY_SEPARATOR . $this->config->get('template.directory') . DIRECTORY_SEPARATOR . $template;
        $extension = $this->config->get('template.extension');
        if ($extension) {
            $templateFile .= ".$extension";
        }
        if (!is_file($templateFile)) {
            throw new InvalidTemplateException("Template file $templateFile does not exist");
        }
        $template = file_get_contents($templateFile);
        return $this->render($template, $vars);
    }

    public function render($template, $vars = array()) {
        $partialsLoader = $this->getPartialsLoader();
        $partialsLoader->setVars($vars);
        $this->addLambdas($vars);
        return parent::render($template, $vars);
    }

    protected function addLambdas(&$vars) {
        $vars['site_url'] = function ($content, Mustache_LambdaHelper $helper) {
            return $this->getSiteUrl($helper->render($content));
        };
        $vars['asset_url'] = function ($content, Mustache_LambdaHelper $helper) {
            return $this->getAssetUrl($helper->render($content));
        };
        $vars['format_timestamp_as_date'] = function ($content, Mustache_LambdaHelper $helper) {
            return $this->formatTimestampAsDate($helper->render($content));
        };
        $vars['format_timestamp_as_date_time'] = function ($content, Mustache_LambdaHelper $helper) {
            return $this->formatTimestampAsDateTime($helper->render($content));
        };
        $vars['format_timestamp_as_relative_time'] = function ($content, Mustache_LambdaHelper $helper) {
            return $this->formatTimestampAsRelativeTime($helper->render($content));
        };
    }

    protected function getSiteUrl($uri) {
        if ($uri && $uri[0] !== DIRECTORY_SEPARATOR) {
            $uri = DIRECTORY_SEPARATOR . $uri;
        }
        return $this->config->get('global.base_url') . $uri;
    }

    protected function getAssetUrl($asset) {
        $cdns = $this->config->get('cdn.hosts');
        if (!$cdns) {
            return $this->getSiteUrl($asset);
        } else if (count($cdns) == 1) {
            $cdn = $cdns[0];
        } else {
            $cdn = $cdns[crc32($asset) % count($cdns)];
        }

        if (!$asset) {
            return $cdn . DIRECTORY_SEPARATOR;
        }

        if ($asset[0] !== DIRECTORY_SEPARATOR) {
            $asset = DIRECTORY_SEPARATOR . $asset;
        }

        $version = '';
        if ($this->config->get('cdn.version') && $this->versionCache instanceof Cache) {
            $versionCacheKey = $this->config->get('cdn.version.cache_prefix') . $asset;
            $version = $this->versionCache->load($versionCacheKey);
            if ($version === null) {
                $filename = $this->config->get('global.web_root') . $asset;
                $ttl = $this->config->get('cdn.version.ttl');
                if (!$ttl) {
                    $ttl = 0;
                }
                if (file_exists($filename)) {
                    $version = md5_file($filename);
                    $this->versionCache->save($version, $versionCacheKey, $ttl);
                } else {
                    $this->versionCache->save(false, $versionCacheKey, $ttl);
                }
            }
            if (!$version) {
                if ($this->config->get('global.production')) {
                    trigger_error("Included asset $asset does not exist");
                    $version = '';
                } else {
                    throw new MissingAssetException("Asset $asset does not exist");
                }
            }
        }
        return $cdn . $asset . '?v=' . $version;
    }

    protected function formatTimestamp($timestamp, $format = '') {
        if (!is_numeric($timestamp)) {
            return '';
        }
        if (!$this->timezone && $this->config->get('global.timezone')) {
            $this->timezone = new DateTimeZone($this->config->get('global.timezone'));
        }
        $datetime = new DateTime();
        $datetime->setTimestamp($timestamp);
        if ($this->timezone) {
            $datetime->setTimezone($this->timezone);
        }
        return $datetime->format($format);
    }

    protected function formatTimestampAsDate($timestamp) {
        $format = $this->config->get('global.date_format') ?: 'd/m/Y';
        return $this->formatTimestamp($timestamp, $format);
    }

    protected function formatTimestampAsDateTime($timestamp) {
        $format = $this->config->get('global.date_time_format') ?: 'd/m/Y H:i:s';
        return $this->formatTimestamp($timestamp, $format);
    }

    protected function formatTimestampAsRelativeTime($timestamp) {
        $now = time();
        $diff = $now - $timestamp;
        $past = $diff > 0;
        $diff = abs($diff);
        // Less than a minute
        // recently / soon
        if ($diff < 60) {
            return $past ? 'recently' : 'soon';
        }
        // Less than a day
        // X hours and Y minutes ago / in X hours and Y minutes
        if ($diff < 60 * 60 * 24) {
            $hours = (int)($diff / 60 / 60);
            $minutes = (int)($diff - ($hours * 60 * 60) / 60);
            $string = ($hours ? $hours . ' hour' . ($hours > 1 ? 's' : '') . ($minutes ? ' and ' : '') : '') . ($minutes ? $minutes . ' minute' . ($minutes > 1 ? 's' : '') : '');
            return $past ? $string . ' ago' : 'in ' . $string;
        }
        $days = (int)($diff / 60 / 60 / 24);
        // 1 day
        // yesterday / tomorrow
        if ($days == 1) {
            return $past ? 'yesterday' : 'tomorrow';
        }
        // Less than 28 days
        // X days ago / in X days
        if ($days <= 28) {
            $string = $days . ' day' . ($days > 1 ? 's' : '');
            return $past ? $string . ' ago' : 'in ' . $string;
        }
        // More than 28 days
        // d/m/Y
        return $this->formatTimestampAsDate($timestamp);
    }
}

