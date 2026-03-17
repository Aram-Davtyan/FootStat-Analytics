<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var array $cards */

$this->title = 'Избранные игроки';
?>

<div class="favorites-view container py-4">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="mb-1">Избранные игроки</h1>
            <div class="text-muted">Сохраненные игроки с аналитикой эффективности и прогнозом на следующий матч.</div>
        </div>
        <a class="btn btn-outline-light" href="<?= Html::encode(Url::to(['player/index'])) ?>">Найти новых игроков</a>
    </div>

    <?php if (empty($cards)): ?>
        <div class="alert alert-info">Пока нет избранных игроков. Открой профиль игрока и добавь его в избранные.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($cards as $card): ?>
                <?php
                    $favorite = $card['favorite'];
                    $season = $card['season'];
                    $analytics = $card['analytics'];
                    $forecast = $card['forecast'];
                    $metrics = $analytics['metrics'] ?? [];
                    $imageUrl = $favorite->player_id ? Url::to(['player/image', 'id' => $favorite->player_id]) : null;
                    $minutes = $season->minutes_played ?? null;
                    $needsMatchSync = $card['needsMatchSync'] ?? false;
                    $trendDelta = $analytics['trend_delta'] ?? null;
                    $trendDeltaLabel = $trendDelta === null
                        ? '—'
                        : (($trendDelta > 0 ? '+' : '') . number_format((float) $trendDelta, 2));
                    $xgiForecast = null;
                    if (isset($forecast['expected_goals'], $forecast['expected_assists'])) {
                        $xgiForecast = (float) $forecast['expected_goals'] + (float) $forecast['expected_assists'];
                    }
                    $strengthLabels = array_map(static function (array $row): string {
                        return sprintf('%s (%s)', $row['label'], $row['contribution']);
                    }, $analytics['strengths'] ?? []);
                    $riskLabels = array_map(static function (array $row): string {
                        return sprintf('%s (%s)', $row['label'], $row['contribution']);
                    }, $analytics['risks'] ?? []);
                ?>
                <div class="col-12">
                    <div class="card analytics-card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <div class="player-avatar">
                                    <?php if ($imageUrl): ?>
                                        <img src="<?= Html::encode($imageUrl) ?>"
                                             alt="<?= Html::encode($favorite->name) ?>"
                                             referrerpolicy="no-referrer"
                                             onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                                        <span class="d-none"><?= Html::encode(mb_substr($favorite->name, 0, 1)) ?></span>
                                    <?php else: ?>
                                        <span><?= Html::encode(mb_substr($favorite->name, 0, 1)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <a class="h5 mb-0" href="<?= Html::encode(Url::to(['player/view', 'id' => $favorite->player_id])) ?>">
                                            <?= Html::encode($favorite->name) ?>
                                        </a>
                                        <span class="tag"><?= Html::encode($favorite->position ?: 'Позиция') ?></span>
                                        <?php if ($favorite->team_name): ?>
                                            <span class="tag muted"><?= Html::encode($favorite->team_name) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <?= Html::encode($favorite->country ?: 'Страна не указана') ?>
                                        <?php if ($season && $season->season_name): ?> · сезон <?= Html::encode($season->season_name) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <?= Html::beginForm(['player/sync', 'id' => $favorite->player_id], 'post') ?>
                                    <?= Html::submitButton('Обновить статистику', ['class' => 'btn btn-primary btn-sm']) ?>
                                    <?= Html::endForm() ?>

                                    <?= Html::beginForm(['player/remove-favorite', 'id' => $favorite->player_id], 'post') ?>
                                    <?= Html::submitButton('Удалить', ['class' => 'btn btn-outline-danger btn-sm']) ?>
                                    <?= Html::endForm() ?>
                                </div>
                            </div>

                            <?php if (!$season): ?>
                                <div class="alert alert-warning mt-4">Статистика еще не загружена. Нажми «Обновить статистику».</div>
                            <?php else: ?>
                                <?php if ($needsMatchSync): ?>
                                    <div class="alert alert-info mt-4">Матчевые данные не загружены. Нажми «Обновить статистику», чтобы сохранить последние матчи для расчета формы.</div>
                                <?php endif; ?>
                                <div class="row g-3 mt-3">
                                    <div class="col-lg-2 col-md-4">
                                        <div class="metric-tile">
                                            <div class="metric-label">Индекс эффективности</div>
                                            <div class="metric-value"><?= Html::encode($analytics['index'] ?? '—') ?></div>
                                            <div class="metric-sub">Рейтинг: <?= Html::encode($analytics['rating'] ?? '—') ?></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4">
                                        <div class="metric-tile">
                                            <div class="metric-label">Сезонный вклад</div>
                                            <div class="metric-value"><?= Html::encode($analytics['season_score'] ?? '—') ?></div>
                                            <div class="metric-sub">Выборка: <?= Html::encode($analytics['sample_minutes'] ?? '—') ?> мин</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4">
                                        <div class="metric-tile">
                                            <div class="metric-label">Форма</div>
                                            <div class="metric-value"><?= Html::encode($analytics['form_score'] ?? '—') ?></div>
                                            <div class="metric-sub">Тренд: <?= Html::encode($trendDeltaLabel) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4">
                                        <div class="metric-tile">
                                            <div class="metric-label">Надежность оценки</div>
                                            <div class="metric-value"><?= Html::encode($analytics['reliability_score'] ?? '—') ?></div>
                                            <div class="metric-sub">Стабильность: <?= Html::encode($analytics['stability_score'] ?? '—') ?></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4">
                                        <div class="metric-tile">
                                            <div class="metric-label">Прогноз индекса</div>
                                            <div class="metric-value"><?= Html::encode($forecast['predicted_index'] ?? '—') ?></div>
                                            <div class="metric-sub">Рейтинг: <?= Html::encode($forecast['predicted_rating'] ?? '—') ?></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4">
                                        <div class="metric-tile">
                                            <div class="metric-label">Ожидаемое участие в голах</div>
                                            <div class="metric-value"><?= Html::encode($xgiForecast !== null ? number_format($xgiForecast, 2) : '—') ?></div>
                                            <div class="metric-sub">xG: <?= Html::encode($forecast['expected_xg'] ?? '—') ?> · xA: <?= Html::encode($forecast['expected_xa'] ?? '—') ?></div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($strengthLabels !== [] || $riskLabels !== []): ?>
                                    <div class="mt-3 small">
                                        <?php if ($strengthLabels !== []): ?>
                                            <div><b>Сильные стороны:</b> <?= Html::encode(implode(', ', $strengthLabels)) ?></div>
                                        <?php endif; ?>
                                        <?php if ($riskLabels !== []): ?>
                                            <div class="text-muted mt-1"><b>Факторы риска:</b> <?= Html::encode(implode(', ', $riskLabels)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="table-responsive mt-4">
                                    <table class="table table-sm table-borderless stats-table">
                                        <thead>
                                            <tr>
                                                <th>Минуты</th>
                                                <th>Матчи</th>
                                                <th>Голы</th>
                                                <th>Ассисты</th>
                                                <th>Ключ. передачи</th>
                                                <th>Удары в створ</th>
                                                <th>Отборы</th>
                                                <th>Перехваты</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= Html::encode($minutes ?? '—') ?></td>
                                                <td><?= Html::encode($season->appearances ?? '—') ?></td>
                                                <td><?= Html::encode($season->goals ?? '—') ?></td>
                                                <td><?= Html::encode($season->assists ?? '—') ?></td>
                                                <td><?= Html::encode($season->key_passes ?? '—') ?></td>
                                                <td><?= Html::encode($season->shots_on_target ?? '—') ?></td>
                                                <td><?= Html::encode($season->tackles ?? '—') ?></td>
                                                <td><?= Html::encode($season->interceptions ?? '—') ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="row g-3 mt-2">
                                    <div class="col-md-4">
                                        <div class="mini-stat">
                                            <div>Голы/90</div>
                                            <strong><?= Html::encode(number_format($metrics['goals_per90'] ?? 0, 2)) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mini-stat">
                                            <div>Ассисты/90</div>
                                            <strong><?= Html::encode(number_format($metrics['assists_per90'] ?? 0, 2)) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mini-stat">
                                            <div>Ключ. передачи/90</div>
                                            <strong><?= Html::encode(number_format($metrics['key_passes_per90'] ?? 0, 2)) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
