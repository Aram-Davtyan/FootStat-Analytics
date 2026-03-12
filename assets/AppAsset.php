<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Основной asset bundle приложения.
 */
class AppAsset extends AssetBundle
{
    /** @var string Базовый путь к статическим файлам. */
    public $basePath = '@webroot';

    /** @var string Базовый URL к статическим файлам. */
    public $baseUrl = '@web';

    /** @var string[] CSS-файлы, подключаемые на всех страницах. */
    public $css = [
        'css/site.css',
    ];

    /** @var string[] JS-файлы, подключаемые на всех страницах. */
    public $js = [];

    /** @var string[] Зависимости от других asset bundle. */
    public $depends = [
        'yii\\web\\YiiAsset',
        'yii\\bootstrap5\\BootstrapAsset',
        'yii\\bootstrap5\\BootstrapPluginAsset',
    ];
}
