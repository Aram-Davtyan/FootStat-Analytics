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
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => Yii::getAlias('@web/footstat-favicon.svg')]);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);

$pageTitle = $this->title ?: Yii::$app->name;
$brandName = 'FootStat';
$brandSubtitle = 'Football analytics lab';
$brandMonogram = 'FS';
$playersIcon = <<<'HTML'
<span class="nav-icon nav-icon--players" aria-hidden="true">
    <svg viewBox="0 0 20 20">
        <circle cx="7" cy="6.5" r="2"></circle>
        <circle cx="13.5" cy="7.5" r="1.8"></circle>
        <path d="M3.7 14.8c.5-2 2-3.2 3.9-3.2h.3c2 0 3.5 1.3 4 3.2"></path>
        <path d="M11.4 14.8c.3-1.5 1.4-2.3 2.9-2.3h.1c1.5 0 2.7.9 3 2.3"></path>
    </svg>
</span>
HTML;
$favoritesIcon = <<<'HTML'
<span class="nav-icon nav-icon--chart" aria-hidden="true">
    <svg viewBox="0 0 20 20">
        <rect class="bar" x="2.2" y="11.7" width="2.6" height="5.3" rx="1"></rect>
        <rect class="bar" x="7" y="9.2" width="2.6" height="7.8" rx="1"></rect>
        <rect class="bar" x="11.8" y="6.6" width="2.6" height="10.4" rx="1"></rect>
        <path d="M2.2 9.8 6.6 7.2l3.2 2 6.8-6"></path>
        <path d="M14.5 3.2h2.5v2.5"></path>
    </svg>
</span>
HTML;
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
            <div class="brand-mark"><?= Html::encode($brandMonogram) ?></div>
            <div class="brand-text">
                <div class="brand-title"><?= Html::encode($brandName) ?></div>
                <div class="brand-subtitle"><?= Html::encode($brandSubtitle) ?></div>
            </div>
        </div>

        <?= Nav::widget([
            'options' => ['class' => 'nav app-nav flex-column'],
            'encodeLabels' => false,
            'items' => [
                ['label' => $playersIcon . '<span class="nav-label">Игроки</span>', 'url' => ['/player/index']],
                ['label' => $favoritesIcon . '<span class="nav-label">Избранные</span>', 'url' => ['/player/favorites']],
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
            <div class="mobile-logo"><?= Html::encode($brandMonogram) ?></div>
            <div class="mobile-brand">
                <div class="mobile-title"><?= Html::encode($brandName) ?></div>
                <div class="mobile-subtitle"><?= Html::encode($brandSubtitle) ?></div>
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
