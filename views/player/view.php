<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var array $profile */
/** @var string|null $error */
/** @var \app\models\FavoritePlayer|null $favorite */

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

$marketValueRaw = $player['proposedMarketValueRaw'] ?? ($player['marketValueRaw'] ?? null);
$marketValue = null;
$marketCurrency = null;

if (is_array($marketValueRaw)) {
    $marketValue = $marketValueRaw['value'] ?? null;
    $marketCurrency = $marketValueRaw['currency'] ?? ($player['marketValueCurrency'] ?? null);
} else {
    $fallbackValue = $player['marketValue'] ?? ($player['proposedMarketValue'] ?? null);
    if (is_array($fallbackValue)) {
        $marketValue = $fallbackValue['value'] ?? null;
        $marketCurrency = $fallbackValue['currency'] ?? ($player['marketValueCurrency'] ?? null);
    } else {
        $marketValue = $fallbackValue;
        $marketCurrency = $player['marketValueCurrency'] ?? null;
    }
}

$marketValueLabel = null;
if (is_numeric($marketValue)) {
    $numericMarketValue = (float) $marketValue;
    $currencyCode = strtoupper((string) $marketCurrency);
    $currencySymbol = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'RUB' => '₽',
    ][$currencyCode] ?? ($currencyCode !== '' ? $currencyCode : '€');

    if ($numericMarketValue >= 1_000_000_000) {
        $displayValue = number_format($numericMarketValue / 1_000_000_000, 2, '.', '');
        $marketValueLabel = "{$displayValue} млрд {$currencySymbol}";
    } elseif ($numericMarketValue >= 1_000_000) {
        $displayValue = number_format($numericMarketValue / 1_000_000, 2, '.', '');
        $marketValueLabel = "{$displayValue} млн {$currencySymbol}";
    } elseif ($numericMarketValue >= 1_000) {
        $displayValue = number_format($numericMarketValue / 1_000, 1, '.', '');
        $marketValueLabel = "{$displayValue} тыс {$currencySymbol}";
    } else {
        $displayValue = number_format($numericMarketValue, 0, '.', ' ');
        $marketValueLabel = "{$displayValue} {$currencySymbol}";
    }
}

$image = $profile['imageUrl'] ?? ($profile['image']['image'] ?? ($profile['image']['url'] ?? null));

$this->title = "Профиль: {$name}";
?>

<div class="player-view container py-4">
    <div class="d-flex align-items-center mb-4">
        <div class="me-3">
            <div class="rounded-circle shadow overflow-hidden position-relative" style="width:110px;height:110px;">
                <?php
                    $imgSrc = $image ?: ($playerId ? Url::to(['player/image', 'id' => $playerId]) : null);
                    $initials = mb_substr($name, 0, 1);
                ?>
                <?php if ($imgSrc): ?>
                    <img src="<?= Html::encode($imgSrc) ?>"
                         alt="<?= Html::encode($name) ?>"
                         style="width:100%;height:100%;object-fit:cover;display:block;"
                         referrerpolicy="no-referrer"
                         onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                <?php endif; ?>
                <div class="bg-light d-flex align-items-center justify-content-center position-absolute top-0 start-0 w-100 h-100 <?= $imgSrc ? 'd-none' : '' ?>">
                    <span class="text-muted fs-4"><?= Html::encode($initials) ?></span>
                </div>
            </div>
        </div>
        <div>
            <h1 class="h3 mb-1"><?= Html::encode($name) ?></h1>
            <div class="text-muted">
                <?= Html::encode($positions ?: '—') ?> · <?= Html::encode($teamName ?: 'Без команды') ?> <?= $country ? '· ' . Html::encode($country) : '' ?>
            </div>
            <div class="small text-muted mt-1 player-meta-line">
                <?php if ($age): ?>Возраст: <?= Html::encode($age) ?><?php endif; ?>
                <?php if ($height): ?> · Рост: <?= Html::encode($height) ?><?php endif; ?>
                <?php if ($weight): ?> · Вес: <?= Html::encode($weight) ?><?php endif; ?>
            </div>
            <?php if ($marketValueLabel !== null): ?>
                <div class="player-market-value mt-2">
                    Стоимость: <?= Html::encode($marketValueLabel) ?>
                </div>
            <?php endif; ?>
            <div class="mt-3 d-flex flex-wrap gap-2">
                <?php if ($favorite): ?>
                    <?= Html::beginForm(['player/sync', 'id' => $playerId], 'post') ?>
                    <?= Html::submitButton('Обновить статистику', ['class' => 'btn btn-primary btn-sm']) ?>
                    <?= Html::endForm() ?>

                    <?= Html::beginForm(['player/remove-favorite', 'id' => $playerId], 'post') ?>
                    <?= Html::submitButton('Удалить из избранных', ['class' => 'btn btn-outline-danger btn-sm']) ?>
                    <?= Html::endForm() ?>
                <?php else: ?>
                    <?= Html::beginForm(['player/add-favorite', 'id' => $playerId], 'post') ?>
                    <?= Html::submitButton('Добавить в избранные', ['class' => 'btn btn-success btn-sm']) ?>
                    <?= Html::endForm() ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= Html::encode($error) ?></div>
    <?php endif; ?>

    <div class="row g-4 profile-top-row">
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

    <div class="row g-4 mt-2 profile-chart-row">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Аналитическая оценка эффективности</div>
                <div class="card-body" data-block="analytics">
                    <div class="text-muted">Загрузка...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2 profile-chart-row">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">График рейтинга по годам</div>
                <div class="card-body" data-block="rating-trend">
                    <div class="text-muted">Загрузка...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2 profile-chart-row profile-chart-row--extra">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Голы и ассисты по годам</div>
                <div class="card-body" data-block="goal-assist-trend">
                    <div class="text-muted">Загрузка...</div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Матчи по годам</div>
                <div class="card-body" data-block="matches-trend">
                    <div class="text-muted">Загрузка...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2 profile-bottom-row">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Последние матчи</div>
                <div class="card-body" data-block="matches">
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
    const currentTeamId = <?= isset($team['id']) && is_numeric($team['id']) ? (int) $team['id'] : 'null' ?>;
    const currentTeamName = <?= json_encode($teamName !== '' ? $teamName : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    fetch(targetUrl)
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) throw new Error(resp.error || 'Ошибка загрузки данных');
            const data = resp.data || {};
            renderAttributes(data);
            renderRatings(data);
            renderStats(data);
            renderAnalytics(data);
            renderRatingTrend(data);
            renderGoalAssistTrend(data);
            renderMatchesTrend(data);
            renderMatches(data);
        })
        .catch(err => {
            ['attributes','ratings','stats','analytics','rating-trend','goal-assist-trend','matches-trend','matches'].forEach(key => {
                const el = document.querySelector(`[data-block="${key}"]`);
                if (el) el.innerHTML = `<div class="text-danger">${escapeHtml(err.message)}</div>`;
            });
        });

    function renderAttributes(data) {
        const box = document.querySelector('[data-block="attributes"]');
        if (!box) return;

        let list = asArray(data.characteristics?.characteristics || data.characteristics?.items || data.characteristics);
        if (!list.length && isObject(data.characteristics)) {
            list = Object.entries(data.characteristics)
                .filter(([, value]) => ['string', 'number', 'boolean'].includes(typeof value))
                .map(([key, value]) => ({ name: humanizeKey(key), value }));
        }

        if (!list.length) {
            const seasonStats = extractSeasons(data)[0]?.statistics || {};
            list = [
                ['Матчи', seasonStats.appearances ?? seasonStats.matches],
                ['Минуты', seasonStats.minutesPlayed ?? seasonStats.minutes],
                ['Голы', seasonStats.goals],
                ['Ассисты', seasonStats.assists],
                ['xG', seasonStats.expectedGoals],
                ['xA', seasonStats.expectedAssists],
            ]
                .filter(([, value]) => value !== undefined && value !== null && value !== '')
                .map(([name, value]) => ({ name, value }));
        }

        if (!list.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }

        const priorityOrder = ['Матчи', 'Минуты', 'Голы', 'Ассисты', 'xG', 'xA', 'Рейтинг'];
        const normalized = list
            .map(attr => {
                const name = String(attr.name || attr.label || '—');
                const value = attr.value ?? attr.rating ?? '—';
                return { name, value };
            })
            .filter(item => item.value !== '' && item.value !== null && item.value !== undefined)
            .sort((a, b) => {
                const ai = priorityOrder.indexOf(a.name);
                const bi = priorityOrder.indexOf(b.name);
                if (ai === -1 && bi === -1) return a.name.localeCompare(b.name);
                if (ai === -1) return 1;
                if (bi === -1) return -1;
                return ai - bi;
            })
            .slice(0, 8);

        box.innerHTML = `<div class="profile-kpi-grid">${normalized.map(item => {
            const numeric = isFiniteNumber(item.value);
            const displayValue = numeric ? formatCompactNumber(Number(item.value)) : item.value;

            return `
                <div class="profile-kpi-item">
                    <div class="profile-kpi-label">${escapeHtml(item.name)}</div>
                    <div class="profile-kpi-value">${escapeHtml(displayValue)}</div>
                </div>
            `;
        }).join('')}</div>`;
    }

    function renderRatings(data) {
        const box = document.querySelector('[data-block="ratings"]');
        if (!box) return;

        let list = asArray(data.ratings?.ratings || data.ratings);
        if (!list.length) {
            list = extractSeasons(data)
                .map(season => {
                    const rating = season.statistics?.rating ?? season.rating;
                    if (rating === undefined || rating === null || rating === '') return null;
                    const tournament = season.uniqueTournament?.name || 'Сезон';
                    const seasonName = season.season?.year || season.season?.name || season.year || '';
                    return { name: `${tournament} ${seasonName}`.trim(), value: rating };
                })
                .filter(Boolean);
        }

        if (!list.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }

        const ranked = list
            .map(row => {
                const name = String(row.type || row.name || 'Матч');
                const ratingRaw = row.value ?? row.rating ?? null;
                const rating = isFiniteNumber(ratingRaw) ? Number(ratingRaw) : null;
                return { name, rating };
            })
            .filter(item => item.rating !== null)
            .sort((a, b) => (b.rating ?? 0) - (a.rating ?? 0))
            .slice(0, 12);

        if (!ranked.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }

        box.innerHTML = `
            <div class="profile-rank-list">
                ${ranked.map((item, index) => `
                    <div class="profile-rank-row">
                        <span class="profile-rank-pos">${index + 1}</span>
                        <span class="profile-rank-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</span>
                        <span class="profile-rank-val">${escapeHtml(item.rating.toFixed(2))}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function renderStats(data) {
        const box = document.querySelector('[data-block="stats"]');
        if (!box) return;

        const source = extractSeasons(data);
        if (!source.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }

        const cards = source
            .map(stat => {
                const stats = stat.statistics || stat;
                const year = extractSeasonYear(stat) || (stat.season?.year ?? stat.season?.name ?? stat.year ?? '—');
                const tournament = stat.uniqueTournament?.name || stat.tournament?.name || stat.name || 'Сезон';
                const matches = stats.appearances ?? stats.matches ?? stats.apps ?? stats.minutesPlayed ?? '—';
                const goals = stats.goals ?? '—';
                const assists = stats.assists ?? '—';
                const rating = isFiniteNumber(stats.rating) ? Number(stats.rating).toFixed(2) : '—';

                return { year: String(year), tournament: String(tournament), matches, goals, assists, rating };
            })
            .sort((a, b) => (parseInt(b.year, 10) || 0) - (parseInt(a.year, 10) || 0))
            .slice(0, 12);

        box.innerHTML = `
            <div class="profile-season-list">
                ${cards.map(item => `
                    <div class="profile-season-item">
                        <div class="profile-season-head">
                            <span class="profile-season-year">${escapeHtml(item.year)}</span>
                            <span class="profile-season-title" title="${escapeHtml(item.tournament)}">${escapeHtml(item.tournament)}</span>
                            <span class="profile-season-rating">${escapeHtml(item.rating)}</span>
                        </div>
                        <div class="profile-season-metrics">
                            <span><b>${escapeHtml(item.matches)}</b> матчей</span>
                            <span><b>${escapeHtml(item.goals)}</b> голов</span>
                            <span><b>${escapeHtml(item.assists)}</b> ассистов</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function renderAnalytics(data) {
        const box = document.querySelector('[data-block="analytics"]');
        if (!box) return;

        const analytics = isObject(data.analytics) ? data.analytics : null;
        const forecast = isObject(data.forecast) ? data.forecast : {};
        if (!analytics) {
            box.innerHTML = '<div class="text-muted">Аналитика появится после добавления игрока в избранное и синхронизации статистики.</div>';
            return;
        }

        const strengths = asArray(analytics.strengths);
        const risks = asArray(analytics.risks);
        const xgi = (isFiniteNumber(forecast.expected_goals) ? Number(forecast.expected_goals) : 0)
            + (isFiniteNumber(forecast.expected_assists) ? Number(forecast.expected_assists) : 0);

        box.innerHTML = `
            <div class="profile-kpi-grid">
                ${renderAnalyticsTile('Индекс эффективности', analytics.index)}
                ${renderAnalyticsTile('Сезонный вклад', analytics.season_score)}
                ${renderAnalyticsTile('Форма', analytics.form_score)}
                ${renderAnalyticsTile('Надежность', analytics.reliability_score)}
                ${renderAnalyticsTile('Стабильность', analytics.stability_score)}
                ${renderAnalyticsTile('Прогноз индекса', forecast.predicted_index)}
            </div>
            <div class="profile-kpi-grid mt-3">
                ${renderAnalyticsTile('Взвешенный рейтинг формы', analytics.weighted_recent_rating ?? analytics.rating)}
                ${renderAnalyticsTile('Тренд формы', formatSignedNumber(analytics.trend_delta))}
                ${renderAnalyticsTile('Прогноз рейтинга', forecast.predicted_rating)}
                ${renderAnalyticsTile('Ожидаемое участие в голах', xgi ? xgi.toFixed(2) : '0.00')}
            </div>
            ${strengths.length ? `<div class="mt-3"><b>Сильные стороны:</b> ${escapeHtml(strengths.map(item => `${item.label} (${item.contribution})`).join(', '))}</div>` : ''}
            ${risks.length ? `<div class="text-muted mt-2"><b>Факторы риска:</b> ${escapeHtml(risks.map(item => `${item.label} (${item.contribution})`).join(', '))}</div>` : ''}
        `;
    }

    function renderRatingTrend(data) {
        const box = document.querySelector('[data-block="rating-trend"]');
        if (!box) return;

        const points = buildYearlyRatings(extractSeasons(data));
        if (!points.length) {
            box.innerHTML = '<div class="text-muted">Нет данных для графика рейтинга</div>';
            return;
        }

        if (points.length === 1) {
            const point = points[0];
            box.innerHTML = `<div class="rating-trend-single">Рейтинг ${escapeHtml(point.year)}: <b>${escapeHtml(point.rating.toFixed(2))}</b></div>`;
            return;
        }

        const width = 900;
        const height = 280;
        const padLeft = 48;
        const padRight = 28;
        const padTop = 20;
        const padBottom = 42;
        const chartW = width - padLeft - padRight;
        const chartH = height - padTop - padBottom;

        const values = points.map(p => p.rating);
        let yMin = Math.min(...values);
        let yMax = Math.max(...values);
        const margin = Math.max(0.12, (yMax - yMin) * 0.2);
        yMin = Math.max(0, yMin - margin);
        yMax = Math.min(10, yMax + margin);
        if (Math.abs(yMax - yMin) < 0.2) {
            yMin = Math.max(0, yMin - 0.3);
            yMax = Math.min(10, yMax + 0.3);
        }

        const mapX = index => padLeft + (index / (points.length - 1)) * chartW;
        const mapY = value => padTop + ((yMax - value) / (yMax - yMin)) * chartH;

        const polyline = points.map((point, index) => `${mapX(index)},${mapY(point.rating)}`).join(' ');
        const area = `${padLeft},${padTop + chartH} ${polyline} ${padLeft + chartW},${padTop + chartH}`;

        const yTicks = 5;
        const yGrid = [];
        for (let i = 0; i <= yTicks; i++) {
            const ratio = i / yTicks;
            const y = padTop + ratio * chartH;
            const value = yMax - ratio * (yMax - yMin);
            yGrid.push({ y, value });
        }

        const xLabels = points.map((point, index) => ({
            x: mapX(index),
            y: padTop + chartH + 22,
            value: point.year,
        }));

        const dots = points.map((point, index) => ({
            cx: mapX(index),
            cy: mapY(point.rating),
            label: point.rating.toFixed(2),
        }));

        const svg = `
            <svg class="rating-trend-svg" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" aria-label="Рейтинг по годам">
                ${yGrid.map(tick => `
                    <line class="rating-trend-grid" x1="${padLeft}" y1="${tick.y}" x2="${padLeft + chartW}" y2="${tick.y}" />
                    <text class="rating-trend-y-label" x="${padLeft - 10}" y="${tick.y + 4}" text-anchor="end">${tick.value.toFixed(2)}</text>
                `).join('')}

                <polygon class="rating-trend-area" points="${area}" />
                <polyline class="rating-trend-line" points="${polyline}" />

                ${dots.map(dot => `
                    <circle class="rating-trend-point" cx="${dot.cx}" cy="${dot.cy}" r="4.2" />
                    <text class="rating-trend-point-label" x="${dot.cx}" y="${dot.cy - 10}" text-anchor="middle">${dot.label}</text>
                `).join('')}

                ${xLabels.map(label => `
                    <text class="rating-trend-x-label" x="${label.x}" y="${label.y}" text-anchor="middle">${escapeHtml(label.value)}</text>
                `).join('')}
            </svg>
        `;

        const latest = points[points.length - 1];
        const first = points[0];
        const delta = latest.rating - first.rating;
        const deltaClass = delta >= 0 ? 'rating-trend-delta up' : 'rating-trend-delta down';
        const deltaSign = delta >= 0 ? '+' : '';

        box.innerHTML = `
            <div class="rating-trend-wrap">
                ${svg}
                <div class="rating-trend-summary">
                    <span>Последний рейтинг: <b>${escapeHtml(latest.rating.toFixed(2))}</b></span>
                    <span class="${deltaClass}">Δ ${escapeHtml(deltaSign + delta.toFixed(2))}</span>
                </div>
            </div>
        `;
    }

    function renderGoalAssistTrend(data) {
        const box = document.querySelector('[data-block="goal-assist-trend"]');
        if (!box) return;

        const seasons = extractSeasons(data);
        const goalsByYear = buildYearlyMetricSums(seasons, ['goals']);
        const assistsByYear = buildYearlyMetricSums(seasons, ['assists']);
        const years = sortYearKeys([...new Set([...Object.keys(goalsByYear), ...Object.keys(assistsByYear)])]);

        const points = years
            .map(year => {
                const goals = goalsByYear[year];
                const assists = assistsByYear[year];
                if (!isFiniteNumber(goals) && !isFiniteNumber(assists)) return null;
                return {
                    year,
                    goals: isFiniteNumber(goals) ? Number(goals) : 0,
                    assists: isFiniteNumber(assists) ? Number(assists) : 0,
                };
            })
            .filter(Boolean);

        if (!points.length) {
            box.innerHTML = '<div class="text-muted">Нет данных для графика голов и ассистов</div>';
            return;
        }

        if (points.length === 1) {
            const point = points[0];
            box.innerHTML = `
                <div class="duo-trend-single">
                    ${escapeHtml(point.year)}: <b>${escapeHtml(formatCompactNumber(point.goals))}</b> гол., <b>${escapeHtml(formatCompactNumber(point.assists))}</b> асс.
                </div>
            `;
            return;
        }

        const width = 880;
        const height = 248;
        const padLeft = 44;
        const padRight = 24;
        const padTop = 18;
        const padBottom = 36;
        const chartW = width - padLeft - padRight;
        const chartH = height - padTop - padBottom;

        const maxValue = Math.max(...points.map(point => Math.max(point.goals, point.assists)), 1);
        const yMax = Math.max(3, Math.ceil(maxValue * 1.2));
        const mapX = index => padLeft + (index / (points.length - 1)) * chartW;
        const mapY = value => padTop + ((yMax - value) / yMax) * chartH;

        const goalsPolyline = points.map((point, index) => `${mapX(index)},${mapY(point.goals)}`).join(' ');
        const assistsPolyline = points.map((point, index) => `${mapX(index)},${mapY(point.assists)}`).join(' ');
        const goalsArea = `${padLeft},${padTop + chartH} ${goalsPolyline} ${padLeft + chartW},${padTop + chartH}`;
        const assistsArea = `${padLeft},${padTop + chartH} ${assistsPolyline} ${padLeft + chartW},${padTop + chartH}`;

        const yTicks = 4;
        const yGrid = [];
        for (let i = 0; i <= yTicks; i++) {
            const ratio = i / yTicks;
            const y = padTop + ratio * chartH;
            const value = Math.round(yMax - ratio * yMax);
            yGrid.push({ y, value });
        }

        const xLabels = points.map((point, index) => ({
            x: mapX(index),
            y: padTop + chartH + 20,
            value: point.year,
        }));

        const goalsDots = points.map((point, index) => ({
            cx: mapX(index),
            cy: mapY(point.goals),
            label: formatCompactNumber(point.goals),
        }));

        const assistsDots = points.map((point, index) => ({
            cx: mapX(index),
            cy: mapY(point.assists),
            label: formatCompactNumber(point.assists),
        }));

        const totalGoals = points.reduce((sum, point) => sum + point.goals, 0);
        const totalAssists = points.reduce((sum, point) => sum + point.assists, 0);

        box.innerHTML = `
            <div class="duo-trend-wrap">
                <svg class="duo-trend-svg" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" aria-label="Голы и ассисты по годам">
                    ${yGrid.map(tick => `
                        <line class="duo-trend-grid" x1="${padLeft}" y1="${tick.y}" x2="${padLeft + chartW}" y2="${tick.y}" />
                        <text class="duo-trend-y-label" x="${padLeft - 9}" y="${tick.y + 4}" text-anchor="end">${tick.value}</text>
                    `).join('')}

                    <polygon class="duo-trend-area duo-trend-area--goals" points="${goalsArea}" />
                    <polygon class="duo-trend-area duo-trend-area--assists" points="${assistsArea}" />
                    <polyline class="duo-trend-line duo-trend-line--goals" points="${goalsPolyline}" />
                    <polyline class="duo-trend-line duo-trend-line--assists" points="${assistsPolyline}" />

                    ${goalsDots.map(dot => `
                        <circle class="duo-trend-point duo-trend-point--goals" cx="${dot.cx}" cy="${dot.cy}" r="3.8" />
                        <text class="duo-trend-point-label duo-trend-point-label--goals" x="${dot.cx}" y="${dot.cy - 9}" text-anchor="middle">${escapeHtml(dot.label)}</text>
                    `).join('')}

                    ${assistsDots.map(dot => `
                        <circle class="duo-trend-point duo-trend-point--assists" cx="${dot.cx}" cy="${dot.cy}" r="3.8" />
                        <text class="duo-trend-point-label duo-trend-point-label--assists" x="${dot.cx}" y="${dot.cy + 15}" text-anchor="middle">${escapeHtml(dot.label)}</text>
                    `).join('')}

                    ${xLabels.map(label => `
                        <text class="duo-trend-x-label" x="${label.x}" y="${label.y}" text-anchor="middle">${escapeHtml(label.value)}</text>
                    `).join('')}
                </svg>
                <div class="duo-trend-meta">
                    <span class="duo-trend-chip goals">Голы: <b>${escapeHtml(formatCompactNumber(totalGoals))}</b></span>
                    <span class="duo-trend-chip assists">Ассисты: <b>${escapeHtml(formatCompactNumber(totalAssists))}</b></span>
                </div>
            </div>
        `;
    }

    function renderMatchesTrend(data) {
        const box = document.querySelector('[data-block="matches-trend"]');
        if (!box) return;

        const seasons = extractSeasons(data);
        const matchesByYear = buildYearlyMetricSums(seasons, ['appearances', 'matches', 'apps']);
        const points = sortYearKeys(Object.keys(matchesByYear))
            .map(year => ({ year, value: Number(matchesByYear[year]) }))
            .filter(point => Number.isFinite(point.value));

        if (!points.length) {
            box.innerHTML = '<div class="text-muted">Нет данных для графика матчей</div>';
            return;
        }

        const maxValue = Math.max(...points.map(point => point.value), 1);
        const totalMatches = points.reduce((sum, point) => sum + point.value, 0);
        const bestYear = points.reduce((best, point) => (point.value > best.value ? point : best), points[0]);

        box.innerHTML = `
            <div class="bar-trend-wrap">
                <div class="bar-trend-chart">
                    ${points.map(point => {
                        const percent = Math.max(6, (point.value / maxValue) * 100);
                        return `
                            <div class="bar-trend-col">
                                <span class="bar-trend-value">${escapeHtml(formatCompactNumber(point.value))}</span>
                                <div class="bar-trend-track">
                                    <span class="bar-trend-bar" style="height:${percent.toFixed(2)}%"></span>
                                </div>
                                <span class="bar-trend-year">${escapeHtml(point.year)}</span>
                            </div>
                        `;
                    }).join('')}
                </div>
                <div class="bar-trend-summary">
                    <span>Всего матчей: <b>${escapeHtml(formatCompactNumber(totalMatches))}</b></span>
                    <span>Пик: <b>${escapeHtml(bestYear.year)}</b> (${escapeHtml(formatCompactNumber(bestYear.value))})</span>
                </div>
            </div>
        `;
    }

    function renderMatches(data) {
        const box = document.querySelector('[data-block="matches"]');
        if (!box) return;

        const list = asArray(data.lastMatches?.events || data.lastMatches?.matches || data.lastMatches);
        if (!list.length) { box.innerHTML = '<div class="text-muted">Нет данных</div>'; return; }
        const rows = list.map(m => {
            const date = m.startTimestamp ? new Date(m.startTimestamp * 1000) : null;
            const dateStr = date ? date.toLocaleDateString() : '—';
            const home = extractScoreValue(m.homeScore);
            const away = extractScoreValue(m.awayScore);
            const score = home === null && away === null ? '—' : `${home ?? '—'} : ${away ?? '—'}`;
            const opponent = resolveOpponentName(m);
            return [dateStr, opponent, score, m.rating ?? '—'];
        });

        box.innerHTML = renderDataTable(
            ['Дата', 'Соперник', 'Счет', 'Рейтинг'],
            rows,
            { className: 'profile-data-table--matches', maxHeight: '430px' }
        );
    }

    function resolveOpponentName(match) {
        const homeTeam = getTeamInfo(match?.homeTeam);
        const awayTeam = getTeamInfo(match?.awayTeam);
        const playerTeam = getPlayerTeamInfo(match);

        const explicitOpponent = getExplicitOpponentName(match);
        if (explicitOpponent !== null) {
            const explicitNormalized = normalizeTeamName(explicitOpponent);
            const playerNormalized = normalizeTeamName(playerTeam.name);

            if (explicitNormalized !== '' && playerNormalized !== '' && explicitNormalized === playerNormalized) {
                const inferred = inferOpponentFromSides(homeTeam, awayTeam, playerTeam);
                if (inferred !== null) return inferred;
            }

            return explicitOpponent;
        }

        const inferred = inferOpponentFromSides(homeTeam, awayTeam, playerTeam);
        if (inferred !== null) return inferred;

        return homeTeam.name || awayTeam.name || '—';
    }

    function getExplicitOpponentName(match) {
        if (typeof match?.opponent === 'string' && match.opponent.trim() !== '') {
            return match.opponent.trim();
        }

        if (isObject(match?.opponent)) {
            const objectName = match.opponent.name ?? match.opponent.shortName ?? null;
            if (typeof objectName === 'string' && objectName.trim() !== '') {
                return objectName.trim();
            }
        }

        if (typeof match?.opponentName === 'string' && match.opponentName.trim() !== '') {
            return match.opponentName.trim();
        }

        return null;
    }

    function getPlayerTeamInfo(match) {
        const eventTeam = getTeamInfo(match?.team ?? match?.playerTeam);
        if (eventTeam.id !== null || eventTeam.name !== null) {
            return eventTeam;
        }

        return {
            id: toIntOrNull(currentTeamId),
            name: typeof currentTeamName === 'string' && currentTeamName.trim() !== '' ? currentTeamName.trim() : null,
        };
    }

    function getTeamInfo(node) {
        if (!isObject(node)) {
            return { id: null, name: null };
        }

        return {
            id: toIntOrNull(node.id ?? node.teamId ?? node.team_id),
            name: extractTeamName(node),
        };
    }

    function extractTeamName(node) {
        if (!isObject(node)) return null;

        const candidates = [node.name, node.shortName, node.displayName];
        for (const candidate of candidates) {
            if (typeof candidate === 'string' && candidate.trim() !== '') {
                return candidate.trim();
            }
        }

        return null;
    }

    function inferOpponentFromSides(homeTeam, awayTeam, playerTeam) {
        if (!homeTeam.name && !awayTeam.name) return null;

        if (playerTeam.id !== null) {
            if (homeTeam.id !== null && homeTeam.id === playerTeam.id) return awayTeam.name || null;
            if (awayTeam.id !== null && awayTeam.id === playerTeam.id) return homeTeam.name || null;
        }

        const playerNormalized = normalizeTeamName(playerTeam.name);
        if (playerNormalized !== '') {
            if (normalizeTeamName(homeTeam.name) === playerNormalized) return awayTeam.name || null;
            if (normalizeTeamName(awayTeam.name) === playerNormalized) return homeTeam.name || null;
        }

        return awayTeam.name || homeTeam.name || null;
    }

    function normalizeTeamName(value) {
        return typeof value === 'string' ? value.trim().toLowerCase() : '';
    }

    function extractScoreValue(scoreNode) {
        if (scoreNode === null || scoreNode === undefined) return null;

        if (typeof scoreNode === 'number' && Number.isFinite(scoreNode)) {
            return scoreNode;
        }

        if (typeof scoreNode === 'string') {
            const trimmed = scoreNode.trim();
            return trimmed !== '' ? trimmed : null;
        }

        if (!isObject(scoreNode)) return null;

        const candidates = [
            scoreNode.current,
            scoreNode.display,
            scoreNode.normaltime,
            scoreNode.total,
            scoreNode.value,
        ];

        for (const candidate of candidates) {
            const value = extractScoreValue(candidate);
            if (value !== null) return value;
        }

        return null;
    }

    function toIntOrNull(value) {
        if (value === null || value === undefined || value === '') return null;
        const number = Number(value);
        return Number.isFinite(number) ? Math.trunc(number) : null;
    }

    function extractSeasons(data) {
        const fromStatisticsSeasons = asArray(data.statisticsSeasons?.statisticsSeasons || data.statisticsSeasons?.seasons || data.statisticsSeasons);
        if (fromStatisticsSeasons.length) return fromStatisticsSeasons;

        const fromAllStatistics = asArray(data.allStatistics?.seasons || data.allStatistics?.statistics || data.allStatistics);
        if (fromAllStatistics.length) return fromAllStatistics;

        return asArray(data.statistics?.statistics || data.statistics?.seasons || data.statistics);
    }

    function asArray(value) {
        if (Array.isArray(value)) return value;
        if (!isObject(value)) return [];
        if (Array.isArray(value.items)) return value.items;
        if (Array.isArray(value.data)) return value.data;
        return [];
    }

    function isObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function humanizeKey(key) {
        const label = String(key || '')
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/[_-]+/g, ' ')
            .trim();

        return label ? label.charAt(0).toUpperCase() + label.slice(1) : '—';
    }

    function buildYearlyRatings(seasons) {
        const byYear = {};

        seasons.forEach(season => {
            const stats = season.statistics || season;
            const ratingRaw = stats?.rating ?? season.rating;
            if (!isFiniteNumber(ratingRaw)) return;

            const year = extractSeasonYear(season);
            if (year === null) return;

            if (!byYear[year]) byYear[year] = [];
            byYear[year].push(Number(ratingRaw));
        });

        return Object.keys(byYear)
            .map(year => {
                const values = byYear[year];
                const avg = values.reduce((sum, val) => sum + val, 0) / values.length;
                return { year, rating: avg };
            })
            .sort((a, b) => Number(a.year) - Number(b.year));
    }

    function extractSeasonYear(season) {
        const seasonYear = season?.season?.year ?? season?.year ?? season?.endYear ?? season?.startYear ?? null;
        if (typeof seasonYear === 'number' && Number.isFinite(seasonYear)) {
            return String(Math.trunc(seasonYear));
        }

        const yearText = String(seasonYear || '').trim();
        if (!yearText) return null;

        const fullYearMatch = yearText.match(/(19|20)\d{2}/);
        if (fullYearMatch) return fullYearMatch[0];

        const splitMatch = yearText.match(/^(\d{2})\s*\/\s*(\d{2})$/);
        if (splitMatch) return `20${splitMatch[2]}`;

        return null;
    }

    function buildYearlyMetricSums(seasons, keys) {
        const byYear = {};

        seasons.forEach(season => {
            const year = extractSeasonYear(season);
            if (year === null) return;

            const stats = season.statistics || season;
            const value = firstFiniteMetric(stats, keys);
            if (value === null) return;

            if (!byYear[year]) byYear[year] = 0;
            byYear[year] += value;
        });

        return byYear;
    }

    function firstFiniteMetric(source, keys) {
        if (!isObject(source)) return null;

        for (const key of keys) {
            const raw = source[key];
            if (isFiniteNumber(raw)) return Number(raw);
        }

        return null;
    }

    function sortYearKeys(keys) {
        return [...keys].sort((a, b) => {
            const aNum = parseInt(a, 10);
            const bNum = parseInt(b, 10);
            if (Number.isNaN(aNum) && Number.isNaN(bNum)) return String(a).localeCompare(String(b));
            if (Number.isNaN(aNum)) return 1;
            if (Number.isNaN(bNum)) return -1;
            return aNum - bNum;
        });
    }

    function isFiniteNumber(value) {
        return typeof value === 'number'
            ? Number.isFinite(value)
            : (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value)));
    }

    function formatCompactNumber(value) {
        if (!Number.isFinite(value)) return '—';
        if (Math.abs(value) >= 1000) return Math.round(value).toLocaleString('ru-RU');
        if (Math.abs(value) >= 10) return value.toFixed(1).replace(/\.0$/, '');
        if (Math.abs(value) >= 1) return value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        return String(value);
    }

    function formatSignedNumber(value) {
        if (!isFiniteNumber(value)) return '—';
        const numeric = Number(value);
        return `${numeric > 0 ? '+' : ''}${numeric.toFixed(2)}`;
    }

    function renderAnalyticsTile(label, value) {
        const display = isFiniteNumber(value) ? formatCompactNumber(Number(value)) : String(value ?? '—');
        return `
            <div class="profile-kpi-item">
                <div class="profile-kpi-label">${escapeHtml(label)}</div>
                <div class="profile-kpi-value">${escapeHtml(display)}</div>
            </div>
        `;
    }

    function renderDataTable(headers, rows, options = {}) {
        const className = options.className || '';
        const maxHeight = options.maxHeight || '';
        const styleAttr = maxHeight ? ` style="max-height:${escapeHtml(maxHeight)}"` : '';
        const headHtml = headers.map(h => `<th>${escapeHtml(h)}</th>`).join('');
        const rowsHtml = rows.map(cols => {
            return `<tr>${cols.map(cell => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`;
        }).join('');

        return `<div class="profile-data-table-wrap ${className}"${styleAttr}><table class="profile-data-table"><thead><tr>${headHtml}</tr></thead><tbody>${rowsHtml}</tbody></table></div>`;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
});
</script>
<?php endif; ?>
