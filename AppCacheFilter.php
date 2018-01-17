<?php

namespace aminkt\components\appcache;

use Yii;
use yii\base\ActionFilter;
use yii\helpers\FileHelper;
use yii\web\View;

/**
 * Create an appcache filter.
 *
 * Class AppCacheFilter
 *
 * @package app\components
 *
 * @author  Amin Keshavarz <amin@keshavarz.pro>
 */
class AppCacheFilter extends ActionFilter
{
    public $extraCaches = [];
    public $actions = [];
    public $rel = true;
    private $_manifest_file;

    /**
     * Refresh manifest file.
     *
     * @param $id
     *
     * @author Amin Keshavarz <amin@keshavarz.pro>
     */
    public static function invalidate($id)
    {
        $filename = static::getFileName($id);
        if (($content = @file_get_contents($filename)) !== false) {
            $lines = explode("\n", $content);
            $lines[1] = '#' . time();
            file_put_contents($filename, implode("\n", $lines));
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        $view = $action->controller->view;
        $js = <<<JS
if (window.applicationCache) {
	window.applicationCache.addEventListener('updateready', function (e) {
		if (window.applicationCache.status == window.applicationCache.UPDATEREADY) {
			window.applicationCache.swapCache();
			//window.location.reload();
		}
	}, false);
}
JS;
        $view->registerJs($js, View::POS_BEGIN);
        $this->_manifest_file = static::getFileName($action->uniqueId, true, $this->rel);
        return true;
    }

    /**
     * Return manifest file
     *
     * @param      $id
     * @param bool $url Return file as url or path
     * @param bool $rel Relative url return or absolute
     *
     * @return bool|string
     *
     * @author Amin Keshavarz <amin@keshavarz.pro>
     */
    private static function getFileName($id, $url = false, $rel = true)
    {
        $key = sprintf('%x', crc32($id . __CLASS__));
        if ($url) {
            return ($rel ? '' : Yii::getAlias('@web') . '/') . "{$key}.manifest";
        } else {
            return Yii::getAlias("@webroot/{$key}.manifest");
        }
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        $this->createManifest($action->uniqueId, $result);
        return $result;
    }

    /**
     * Create manifest file.
     *
     * @param $id
     * @param $html
     *
     * @author Amin Keshavarz <amin@keshavarz.pro>
     */
    protected function createManifest($id, $html)
    {
        try {
            $filename = $this->getFileName($id);
            if (@file_get_contents($filename) == false) {
                $caches = [];
                $paths = [];
                $baseUrl = Yii::getAlias('@web') . '/';
                $basePath = Yii::getAlias('@webroot') . '/';


                // css
                $matches = [];
                $pattern = '/<link [^>]*href="?([^">]+)"?/';
                preg_match_all($pattern, $html, $matches);
                if (isset($matches[1])) {
                    foreach ($matches[1] as $href) {
                        $caches[$href] = true;
                        if (($path = $this->convertUrlToPath($href, $basePath, $baseUrl)) !== false) {
                            $path = dirname($path);
                            if (!isset($paths[$path]) && is_dir($path)) {
                                $paths[$path] = true;
                                foreach (FileHelper::findFiles($path) as $file) {
                                    $caches[$this->convertPathToUrl($file, $basePath, $baseUrl)] = true;
                                }
                            }
                        }
                    }
                }


                // js
                $matches = [];
                $pattern = '/<script [^>]*src="?([^">]+)"?/';
                preg_match_all($pattern, $html, $matches);
                if (isset($matches[1])) {
                    foreach ($matches[1] as $src) {
                        $caches[$src] = true;
                    }
                }


                // img
                $matches = [];
                $pattern = '/<img [^>]*src="?([^">]+)"?/';
                preg_match_all($pattern, $html, $matches);
                if (isset($matches[1])) {
                    foreach ($matches[1] as $src) {
                        if (strpos($src, 'data:') !== 0) {
                            $caches[$src] = true;
                        }
                    }
                }


                unset($caches[false]);
                $data = array_keys($caches);
                if ($this->rel) {
                    $l = strlen($baseUrl);
                    foreach ($data as $key => $url) {
                        if (strpos($url, $baseUrl) === 0) {
                            $data[$key] = substr($url, $l);
                        }
                    }
                }
                $view = new View();
                $manifest = $view->renderPhpFile(Yii::getAlias('@vendor/aminkt/yii2-appcache/manifest.php'), [
                    'caches' => array_merge($data, $this->extraCaches)
                ]);
                FileHelper::createDirectory(dirname($filename));
                file_put_contents($filename, $manifest);
            }
        } catch (\Exception $exc) {
            \Yii::error($exc->getMessage());
        }
    }

    /**
     * Convert url to path
     *
     * @param $url
     * @param $basePath
     * @param $baseUrl
     *
     * @return bool|string
     *
     * @author Amin Keshavarz <amin@keshavarz.pro>
     */
    private function convertUrlToPath($url, $basePath, $baseUrl)
    {
        if ($baseUrl && $basePath && strpos($url, $baseUrl) === 0) {
            return $basePath . substr($url, strlen($baseUrl));
        }
        return false;
    }

    /**
     * Convert path to url.
     *
     * @param $path
     * @param $basePath
     * @param $baseUrl
     *
     * @return bool|string
     *
     * @author Amin Keshavarz <amin@keshavarz.pro>
     */
    private function convertPathToUrl($path, $basePath, $baseUrl)
    {
        if ($baseUrl && $basePath && strpos($path, $basePath) === 0) {
            return $baseUrl . substr($path, strlen($basePath));
        }
        return false;
    }

    /**
     * Return manifest file.
     *
     * @return mixed
     *
     * @author Amin Keshavarz <amin@keshavarz.pro>
     */
    public function getManifestFile()
    {
        return $this->_manifest_file;
    }

    /**
     * @inheritdoc
     *
     * @author Amin Keshavarz <amin@keshavarz.pro>
     */
    protected function isActive($action)
    {
        return in_array($action->id, $this->actions, true);
    }
}