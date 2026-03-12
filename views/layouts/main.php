<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;

AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);

$pageTitle = $this->title ?: Yii::$app->name;
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($pageTitle) ?></title>
    <?php $this->head() ?>
</head>
<body class="app-body">
<?php $this->beginBody() ?>

<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">

<div class="app-shell">
    <label for="nav-toggle" class="nav-overlay" aria-hidden="true"></label>
    <aside class="app-sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark">SC</div>
            <div class="brand-text">
                <div class="brand-title"><?= Html::encode(Yii::$app->name) ?></div>
                <div class="brand-subtitle">Sofascore dashboard</div>
            </div>
        </div>

        <?= Nav::widget([
            'options' => ['class' => 'nav app-nav flex-column'],
            'items' => [
                ['label' => 'Игроки', 'url' => ['/player/index']],
                ['label' => 'Избранные', 'url' => ['/player/favorites']],
            ],
        ]) ?>

        <div class="app-sidebar-footer">
            <?php if (Yii::$app->user->isGuest): ?>
                <?= Html::a('Войти', ['/site/login'], ['class' => 'app-nav-link']) ?>
            <?php else: ?>
                <?= Html::beginForm(['/site/logout'], 'post', ['class' => 'app-logout']) ?>
                <?= Html::submitButton(
                    'Выйти (' . Html::encode(Yii::$app->user->identity->username) . ')',
                    ['class' => 'app-logout-btn']
                ) ?>
                <?= Html::endForm() ?>
            <?php endif; ?>
        </div>
    </aside>

    <div class="app-main">
        <header class="mobile-topbar" aria-label="Панель навигации">
            <label for="nav-toggle" class="burger-btn" aria-label="Открыть меню">
                <span></span>
                <span></span>
                <span></span>
            </label>
            <div class="mobile-logo">SC</div>
            <div class="mobile-brand">
                <div class="mobile-title">Dashboard</div>
                <div class="mobile-subtitle">Sofascore</div>
            </div>
        </header>

        <main id="main" class="app-content" role="main">
            <div class="content-wrap">
                <?php if (!empty($this->params['breadcrumbs'])): ?>
                    <?= Breadcrumbs::widget([
                        'links' => $this->params['breadcrumbs'],
                        'options' => ['class' => 'app-breadcrumbs'],
                    ]) ?>
                <?php endif ?>
                <?= Alert::widget() ?>
                <?= $content ?>
            </div>
        </main>
    </div>
</div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
