<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var array $profile */
/** @var string|null $error */

$detail = $profile['detail'] ?? [];
$player = $detail['player'] ?? $detail ?? [];
$name = $player['name'] ?? $player['shortName'] ?? 'Игрок';
$country = $player['country']['name'] ?? ($player['country'] ?? '');
$team = $player['team'] ?? [];
$teamName = $team['name'] ?? ($team['shortName'] ?? '');
$positions = $player['position'] ?? '';
$age = isset($player['age']) ? $player['age'] : null;
$height = $player['height'] ?? null;
$weight = $player['weight'] ?? null;
$playerId = $playerId ?? ($player['id'] ?? null);

$image = $profile['imageUrl'] ?? ($profile['image']['image'] ?? ($profile['image']['url'] ?? null));

$this->title = "Профиль: {$name}";
?>

<div class="player-view container py-4">
    <div class="d-flex align-items-center mb-4">
        <div class="me-3">
            <div class="rounded-circle shadow overflow-hidden position-relative" style="width:110px;height:110px;">
                <?php
                    $imgSrc = $image ?: ($playerId ? 'https://api.sofascore.com/api/v1/player/' . $playerId . '/image' : null);
                    $initials = mb_substr($name, 0, 1);
                ?>
                <?php if ($imgSrc): ?>
                    <img src="<?= Html::encode($imgSrc) ?>"
                         alt="<?= Html::encode($name) ?>"
                         style="width:100%;height:100%;object-fit:cover;display:block;"
                         referrerpolicy="no-referrer"
                         onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                <?php endif; ?>
                <div class="bg-light d-flex align-items-center justify-content-center position-absolute top-0 start-0 w-100 h-100 d-none">
                    <span class="text-muted fs-4"><?= Html::encode($initials) ?></span>
                </div>
            </div>
        </div>
        <div>
            <h1 class="h3 mb-1"><?= Html::encode($name) ?></h1>
            <div class="text-muted">
                <?= Html::encode($positions ?: '—') ?> · <?= Html::encode($teamName ?: 'Без команды') ?> <?= $country ? '· ' . Html::encode($country) : '' ?>
            </div>
            <div class="small text-muted mt-1">
                <?php if ($age): ?>Возраст: <?= Html::encode($age) ?><?php endif; ?>
                <?php if ($height): ?> · Рост: <?= Html::encode($height) ?><?php endif; ?>
                <?php if ($weight): ?> · Вес: <?= Html::encode($weight) ?><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= Html::encode($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header fw-semibold">Характеристики</div>
                <div class="card-body" data-block="attributes">
                    <div class="text-muted">Загрузка...</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header fw-semibold">Рейтинги</div>
                <div class="card-body" data-block="ratings">
                    <div class="text-muted">Загрузка...</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header fw-semibold">Статистика по сезонам</div>
                <div class="card-body" data-block="stats">
                    <div class="text-muted">Загрузка...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Последние матчи</div>
                <div class="card-body" data-block="matches">
                    <div class="text-muted">Загрузка...</div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Трансферы</div>
                <div class="card-body" data-block="transfers">
                    <div class="text-muted">Загрузка...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$fetchUrl = $playerId ? Url::to(['player/profile-data', 'id' => $playerId]) : null;
?>
<?php if ($fetchUrl): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const targetUrl = '<?= Html::encode($fetchUrl) ?>';
    fetch(targetUrl)
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) throw new Error(resp.error || 'Ошибка загрузки данных');
            const data = resp.data || {};
            renderAttributes(data);
            renderRatings(data);
            renderStats(data);
            renderMatches(data);
            renderTransfers(data);
        })
        .catch(err => {
            ['attributes','ratings','stats','matches','transfers'].forEach(key => {
                const el = document.querySelector(`[data-block="${key}"]`);
                if (el) el.innerHTML = `<div class="text-danger">${escapeHtml(err.message)}</div>`;
            });
        });

    function renderAttributes(data) {
        const box = document.querySelector('[data-block="attributes"]');
        if (!box) return;
        const list = data.characteristics?.characteristics || data.characteristics || [];
        if (!list.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }
        box.innerHTML = '<ul class="list-unstyled mb-0">' + list.map(attr => {
            const name = attr.name || attr.label || '—';
            const val = attr.value ?? attr.rating ?? '';
            return `<li class="d-flex justify-content-between border-bottom py-1"><span>${escapeHtml(name)}</span><span class="fw-semibold">${escapeHtml(val)}</span></li>`;
        }).join('') + '</ul>';
    }

    function renderRatings(data) {
        const box = document.querySelector('[data-block="ratings"]');
        if (!box) return;
        const list = data.ratings?.ratings || data.ratings || [];
        if (!list.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }
        box.innerHTML = '<ul class="list-unstyled mb-0">' + list.map(r => {
            const name = r.type || r.name || 'Матч';
            const val = r.value ?? r.rating ?? '';
            return `<li class="d-flex justify-content-between border-bottom py-1"><span>${escapeHtml(name)}</span><span class="fw-semibold">${escapeHtml(val)}</span></li>`;
        }).join('') + '</ul>';
    }

    function renderStats(data) {
        const box = document.querySelector('[data-block="stats"]');
        if (!box) return;
        const seasons = data.statisticsSeasons?.statisticsSeasons || data.statisticsSeasons || [];
        const statsAll = data.allStatistics?.statistics || data.allStatistics || [];
        const statsSimple = data.statistics?.statistics || data.statistics || [];
        const source = seasons.length ? seasons : (statsAll.length ? statsAll : statsSimple);
        if (!source.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }
        const header = seasons.length ? ['Сезон','Матчи','Голы','Ассисты'] : ['Турнир','Матчи','Голы','Ассисты'];
        const rows = source.map(stat => {
            const c1 = seasons.length ? (stat.season ?? stat.name ?? '') : (stat.tournament?.name ?? stat.name ?? '');
            const apps = stat.matches ?? stat.apps ?? stat.appearances ?? '';
            return `<tr><td>${escapeHtml(c1)}</td><td>${escapeHtml(apps)}</td><td>${escapeHtml(stat.goals ?? '')}</td><td>${escapeHtml(stat.assists ?? '')}</td></tr>`;
        }).join('');
        box.innerHTML = `<div class="table-responsive"><table class="table table-sm"><thead><tr>${header.map(h=>`<th>${h}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table></div>`;
    }

    function renderMatches(data) {
        const box = document.querySelector('[data-block="matches"]');
        if (!box) return;
        const list = data.lastMatches?.events || data.lastMatches || [];
        if (!list.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }
        const rows = list.map(m => {
            const date = m.startTimestamp ? new Date(m.startTimestamp * 1000) : null;
            const dateStr = date ? date.toLocaleDateString() : '';
            const score = `${m.homeScore?.current ?? ''} : ${m.awayScore?.current ?? ''}`;
            const opponent = m.opponent ?? (m.homeTeam?.name ?? '');
            return `<tr><td>${escapeHtml(dateStr)}</td><td>${escapeHtml(opponent)}</td><td>${escapeHtml(score)}</td><td>${escapeHtml(m.rating ?? '')}</td></tr>`;
        }).join('');
        box.innerHTML = `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Дата</th><th>Соперник</th><th>Счет</th><th>Рейтинг</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    }

    function renderTransfers(data) {
        const box = document.querySelector('[data-block="transfers"]');
        if (!box) return;
        const list = data.transferHistory?.transfers || data.transferHistory || [];
        if (!list.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }
        const rows = list.map(t => `<tr><td>${escapeHtml(t.date ?? '')}</td><td>${escapeHtml(t.fromTeam?.name ?? '')}</td><td>${escapeHtml(t.toTeam?.name ?? '')}</td><td>${escapeHtml(t.fee ?? '')}</td></tr>`).join('');
        box.innerHTML = `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Дата</th><th>Из</th><th>В</th><th>Сумма</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
});
</script>
<?php endif; ?>
