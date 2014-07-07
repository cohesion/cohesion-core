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

class CohesionMo extends Mustache_Engine implements TemplateEngine, Configurable {

    protected $config;
    protected $versionCache;

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
}

