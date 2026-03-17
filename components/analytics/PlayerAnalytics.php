<?php

namespace app\components\analytics;

use app\models\PlayerSeasonStat;

/**
 * Статический модуль футбольной аналитики по сезонной статистике игрока.
 */
class PlayerAnalytics
{
    /**
     * Профили весов и нормализации метрик по группам позиций.
     */
    private const PROFILE = [
        'FW' => [
            'max' => [
                'xg_per90' => 1.0,
                'goals_per90' => 1.0,
                'shots_on_target_per90' => 3.0,
                'assists_per90' => 0.5,
                'key_passes_per90' => 2.5,
                'successful_dribbles_per90' => 4.0,
                'dribbled_past_per90' => 2.0,
            ],
            'weights' => [
                'xg_per90' => 0.22,
                'goals_per90' => 0.20,
                'shots_on_target_per90' => 0.15,
                'assists_per90' => 0.12,
                'key_passes_per90' => 0.12,
                'successful_dribbles_per90' => 0.09,
            ],
            'penalties' => [
                'dribbled_past_per90' => 0.03,
            ],
        ],
        'MF' => [
            'max' => [
                'xg_per90' => 0.6,
                'assists_per90' => 0.6,
                'key_passes_per90' => 3.5,
                'accurate_passes_per90' => 70.0,
                'tackles_per90' => 4.0,
                'interceptions_per90' => 3.0,
                'dribbled_past_per90' => 2.0,
            ],
            'weights' => [
                'xg_per90' => 0.12,
                'assists_per90' => 0.18,
                'key_passes_per90' => 0.22,
                'accurate_passes_per90' => 0.18,
                'tackles_per90' => 0.15,
                'interceptions_per90' => 0.15,
            ],
            'penalties' => [
                'dribbled_past_per90' => 0.02,
            ],
        ],
        'DF' => [
            'max' => [
                'tackles_per90' => 5.0,
                'interceptions_per90' => 4.0,
                'aerial_duels_won_per90' => 6.0,
                'accurate_passes_per90' => 60.0,
                'clean_sheets_per90' => 0.6,
                'dribbled_past_per90' => 3.0,
                'goals_conceded_per90' => 2.0,
            ],
            'weights' => [
                'tackles_per90' => 0.25,
                'interceptions_per90' => 0.22,
                'aerial_duels_won_per90' => 0.20,
                'accurate_passes_per90' => 0.15,
                'clean_sheets_per90' => 0.18,
            ],
            'penalties' => [
                'dribbled_past_per90' => 0.05,
                'goals_conceded_per90' => 0.05,
            ],
        ],
        'GK' => [
            'max' => [
                'saves_per90' => 6.0,
                'goals_prevented_per90' => 1.0,
                'clean_sheets_per90' => 0.6,
                'goals_conceded_per90' => 2.0,
            ],
            'weights' => [
                'saves_per90' => 0.35,
                'goals_prevented_per90' => 0.30,
                'clean_sheets_per90' => 0.35,
            ],
            'penalties' => [
                'goals_conceded_per90' => 0.15,
            ],
        ],
    ];

    /**
     * Человекочитаемые имена метрик.
     */
    private const METRIC_LABELS = [
        'goals_per90' => 'Голы/90',
        'assists_per90' => 'Ассисты/90',
        'xg_per90' => 'xG/90',
        'xa_per90' => 'xA/90',
        'shots_on_target_per90' => 'Удары в створ/90',
        'key_passes_per90' => 'Ключевые передачи/90',
        'tackles_per90' => 'Отборы/90',
        'interceptions_per90' => 'Перехваты/90',
        'accurate_passes_per90' => 'Точные передачи/90',
        'aerial_duels_won_per90' => 'Выигранные верховые единоборства/90',
        'successful_dribbles_per90' => 'Успешный дриблинг/90',
        'clean_sheets_per90' => 'Сухие матчи/90',
        'saves_per90' => 'Сейвы/90',
        'goals_conceded_per90' => 'Пропущенные голы/90',
        'goals_prevented_per90' => 'Предотвращенные голы/90',
        'dribbled_past_per90' => 'Обводки соперником/90',
        'shot_accuracy' => 'Точность ударов',
        'pass_accuracy' => 'Точность передач',
        'goal_involvement_per90' => 'Участие в голах/90',
        'expected_involvement_per90' => 'Ожидаемое участие в голах/90',
    ];

    private const FORM_DECAY = 0.72;
    private const BASE_SAMPLE_MINUTES = 1800.0;
    private const BASE_SAMPLE_MATCHES = 5.0;
    private const BASE_SAMPLE_APPEARANCES = 20.0;
    private const NORMALIZATION_SHARPNESS = 3.0;
    private const RATING_FLOOR = 5.5;
    private const RATING_SPAN = 3.0;
    private const TREND_SLOPE_SCALE = 0.18;

    /**
     * Нормализует текстовую позицию игрока к одной из групп (FW/MF/DF/GK).
     *
     * @param string|null $position позиция игрока.
     */
    public static function positionGroup(?string $position): string
    {
        $pos = strtolower((string) $position);
        if ($pos === '') {
            return 'MF';
        }

        if (stripos($pos, 'gk') !== false || stripos($pos, 'goal') !== false) {
            return 'GK';
        }

        if (
            stripos($pos, 'def') !== false
            || stripos($pos, 'back') !== false
            || stripos($pos, 'cb') !== false
            || stripos($pos, 'lb') !== false
            || stripos($pos, 'rb') !== false
        ) {
            return 'DF';
        }

        if (
            stripos($pos, 'mid') !== false
            || stripos($pos, 'am') !== false
            || stripos($pos, 'dm') !== false
            || stripos($pos, 'cm') !== false
        ) {
            return 'MF';
        }

        if (
            stripos($pos, 'forw') !== false
            || stripos($pos, 'str') !== false
            || stripos($pos, 'fw') !== false
        ) {
            return 'FW';
        }

        return 'MF';
    }

    /**
     * Пересчитывает значение метрики в формат "за 90 минут".
     *
     * @param float $value исходное абсолютное значение.
     * @param int $minutes сыгранные минуты.
     */
    public static function per90(float $value, int $minutes): float
    {
        if ($minutes <= 0) {
            return 0.0;
        }

        return ($value / $minutes) * 90.0;
    }

    /**
     * Ограничивает значение интервалом [min, max].
     *
     * @param float $value значение.
     * @param float $min нижняя граница.
     * @param float $max верхняя граница.
     */
    public static function clamp(float $value, float $min = 0.0, float $max = 1.0): float
    {
        return max($min, min($max, $value));
    }

    /**
     * Строит набор производных метрик из сезонной статистики.
     *
     * @param PlayerSeasonStat $stat запись сезонной статистики.
     * @return array<string, float|int>
     */
    public static function buildMetrics(PlayerSeasonStat $stat): array
    {
        $minutes = (int) ($stat->minutes_played ?? 0);
        $goals = (float) ($stat->goals ?? 0);
        $assists = (float) ($stat->assists ?? 0);
        $xg = (float) ($stat->expected_goals ?? 0);
        $xa = (float) ($stat->expected_assists ?? 0);
        $shotsOnTarget = (float) ($stat->shots_on_target ?? 0);
        $keyPasses = (float) ($stat->key_passes ?? 0);
        $tackles = (float) ($stat->tackles ?? 0);
        $interceptions = (float) ($stat->interceptions ?? 0);
        $accuratePasses = (float) ($stat->accurate_passes ?? 0);
        $aerialDuelsWon = (float) ($stat->aerial_duels_won ?? 0);
        $successfulDribbles = (float) ($stat->successful_dribbles ?? 0);
        $cleanSheet = (float) ($stat->clean_sheet ?? 0);
        $saves = (float) ($stat->saves ?? 0);
        $goalsConceded = (float) ($stat->goals_conceded ?? 0);
        $goalsPrevented = (float) ($stat->goals_prevented ?? 0);
        $dribbledPast = (float) ($stat->dribbled_past ?? 0);
        $totalShots = (float) ($stat->total_shots ?? 0);
        $totalPasses = (float) ($stat->total_passes ?? 0);

        return [
            'minutes' => $minutes,
            'goals_per90' => self::per90($goals, $minutes),
            'assists_per90' => self::per90($assists, $minutes),
            'xg_per90' => self::per90($xg, $minutes),
            'xa_per90' => self::per90($xa, $minutes),
            'shots_on_target_per90' => self::per90($shotsOnTarget, $minutes),
            'key_passes_per90' => self::per90($keyPasses, $minutes),
            'tackles_per90' => self::per90($tackles, $minutes),
            'interceptions_per90' => self::per90($interceptions, $minutes),
            'accurate_passes_per90' => self::per90($accuratePasses, $minutes),
            'aerial_duels_won_per90' => self::per90($aerialDuelsWon, $minutes),
            'successful_dribbles_per90' => self::per90($successfulDribbles, $minutes),
            'clean_sheets_per90' => self::per90($cleanSheet, $minutes),
            'saves_per90' => self::per90($saves, $minutes),
            'goals_conceded_per90' => self::per90($goalsConceded, $minutes),
            'goals_prevented_per90' => self::per90($goalsPrevented, $minutes),
            'dribbled_past_per90' => self::per90($dribbledPast, $minutes),
            'shot_accuracy' => self::ratio($shotsOnTarget, $totalShots),
            'pass_accuracy' => self::ratio($accuratePasses, $totalPasses),
            'goal_involvement_per90' => self::per90($goals + $assists, $minutes),
            'expected_involvement_per90' => self::per90($xg + $xa, $minutes),
        ];
    }

    /**
     * Рассчитывает интегральный индекс эффективности игрока (0-100).
     *
     * @param PlayerSeasonStat $stat запись сезонной статистики.
     * @param float[] $recentRatings рейтинги последних матчей, отсортированные от новых к старым.
     * @return array<string, mixed>
     */
    public static function efficiencyIndex(PlayerSeasonStat $stat, array $recentRatings = []): array
    {
        $group = self::positionGroup($stat->position);
        $profile = self::PROFILE[$group] ?? self::PROFILE['MF'];
        $metrics = self::buildMetrics($stat);
        $rating = (float) ($stat->rating ?? 0);
        $season = self::buildSeasonComponent($profile, $metrics, $rating);
        $form = self::buildFormComponent($recentRatings, $rating);
        $reliability = self::buildReliabilityComponent($stat, $form['sample_size']);

        $quality = 0.58 * $season['score']
            + 0.22 * $form['score']
            + 0.12 * $form['stability_score']
            + 0.08 * $form['trend_score'];

        // При малой выборке итог стягивается к нейтральному значению 50.
        $index = $reliability['factor'] * $quality + (1.0 - $reliability['factor']) * 50.0;
        $index = self::clamp($index, 0.0, 100.0);

        return [
            'index' => round($index, 1),
            'rating' => $rating,
            'metrics' => $metrics,
            'group' => $group,
            'season_score' => round($season['score'], 1),
            'form_score' => round($form['score'], 1),
            'stability_score' => round($form['stability_score'], 1),
            'trend_score' => round($form['trend_score'], 1),
            'trend_delta' => round($form['delta'], 2),
            'weighted_recent_rating' => $form['weighted_rating'] !== null ? round($form['weighted_rating'], 2) : null,
            'recent_rating_avg' => $form['average_rating'] !== null ? round($form['average_rating'], 2) : null,
            'reliability_score' => round($reliability['score'], 1),
            'reliability_factor' => round($reliability['factor'], 4),
            'sample_matches' => $form['sample_size'],
            'sample_minutes' => (int) ($metrics['minutes'] ?? 0),
            'components' => [
                'season' => round($season['score'], 1),
                'form' => round($form['score'], 1),
                'stability' => round($form['stability_score'], 1),
                'trend' => round($form['trend_score'], 1),
                'reliability' => round($reliability['score'], 1),
            ],
            'strengths' => array_slice($season['strengths'], 0, 3),
            'risks' => array_slice($season['risks'], 0, 2),
        ];
    }

    /**
     * Строит прогноз на следующий матч на базе сезонной и недавней формы.
     *
     * @param PlayerSeasonStat $stat запись сезонной статистики.
     * @param float[] $recentRatings рейтинги из последних матчей.
     * @return array<string, float|null>
     */
    public static function predictNextMatch(PlayerSeasonStat $stat, array $recentRatings = []): array
    {
        $metrics = self::buildMetrics($stat);
        $analytics = self::efficiencyIndex($stat, $recentRatings);
        $appearances = (int) ($stat->appearances ?? 0);
        $avgMinutes = $appearances > 0 ? ($stat->minutes_played / $appearances) : 75.0;
        $expectedMinutes = max(30.0, min(90.0, $avgMinutes));
        $formMultiplier = 0.9 + (($analytics['form_score'] - 50.0) / 250.0);
        $trendMultiplier = 0.95 + (($analytics['trend_score'] - 50.0) / 500.0);
        $performanceMultiplier = max(0.75, min(1.2, $formMultiplier * $trendMultiplier));

        $baseGoalsPer90 = 0.65 * $metrics['xg_per90'] + 0.35 * $metrics['goals_per90'];
        $baseAssistsPer90 = 0.65 * $metrics['xa_per90'] + 0.35 * $metrics['assists_per90'];

        $expectedGoals = $baseGoalsPer90 * $expectedMinutes / 90.0 * $performanceMultiplier;
        $expectedAssists = $baseAssistsPer90 * $expectedMinutes / 90.0 * $performanceMultiplier;
        $expectedXg = $metrics['xg_per90'] * $expectedMinutes / 90.0 * $performanceMultiplier;
        $expectedXa = $metrics['xa_per90'] * $expectedMinutes / 90.0 * $performanceMultiplier;

        $rating = (float) ($stat->rating ?? 0);
        $recentWeighted = $analytics['weighted_recent_rating'];
        $predRating = $recentWeighted !== null
            ? (0.6 * $rating + 0.4 * $recentWeighted + (($analytics['trend_score'] - 50.0) / 50.0))
            : $rating;
        $predRating = max(0.0, min(10.0, $predRating));

        $predIndex = 0.65 * $analytics['index']
            + 0.20 * $analytics['form_score']
            + 0.15 * $analytics['trend_score'];
        $predIndex = self::clamp($predIndex, 0.0, 100.0);

        return [
            'expected_minutes' => round($expectedMinutes, 1),
            'expected_goals' => round($expectedGoals, 2),
            'expected_assists' => round($expectedAssists, 2),
            'expected_xg' => round($expectedXg, 2),
            'expected_xa' => round($expectedXa, 2),
            'predicted_rating' => round($predRating, 2),
            'predicted_index' => round($predIndex, 1),
            'recent_rating_avg' => $analytics['recent_rating_avg'] !== null ? round($analytics['recent_rating_avg'], 2) : null,
            'confidence' => $analytics['reliability_score'],
        ];
    }

    /**
     * Возвращает отношение числителя к знаменателю в диапазоне [0, 1].
     */
    private static function ratio(float $numerator, float $denominator): float
    {
        if ($denominator <= 0.0) {
            return 0.0;
        }

        return self::clamp($numerator / $denominator);
    }

    /**
     * Нелинейная нормализация положительной метрики через экспоненциальное насыщение.
     */
    private static function normalizePositive(float $value, float $benchmark): float
    {
        if ($value <= 0.0 || $benchmark <= 0.0) {
            return 0.0;
        }

        return self::clamp(1.0 - exp(-self::NORMALIZATION_SHARPNESS * ($value / $benchmark)));
    }

    /**
     * Переводит футбольный рейтинг в шкалу [0, 1].
     */
    private static function ratingToScore(float $rating): float
    {
        return self::clamp(($rating - self::RATING_FLOOR) / self::RATING_SPAN);
    }

    /**
     * Строит сезонную компоненту на основе позиционного профиля.
     *
     * @param array<string, array<string, float>> $profile
     * @param array<string, float|int> $metrics
     * @return array<string, mixed>
     */
    private static function buildSeasonComponent(array $profile, array $metrics, float $rating): array
    {
        $weighted = 0.0;
        $strengths = [];

        foreach ($profile['weights'] as $metric => $weight) {
            $value = (float) ($metrics[$metric] ?? 0.0);
            $max = (float) ($profile['max'][$metric] ?? 1.0);
            $normalized = self::normalizePositive($value, $max);
            $contribution = $weight * $normalized * 100.0;
            $weighted += $weight * $normalized;

            $strengths[] = [
                'metric' => $metric,
                'label' => self::METRIC_LABELS[$metric] ?? $metric,
                'value' => round($value, 3),
                'contribution' => round($contribution, 1),
            ];
        }

        $penalty = 0.0;
        $risks = [];
        foreach ($profile['penalties'] as $metric => $weight) {
            $value = (float) ($metrics[$metric] ?? 0.0);
            $max = (float) ($profile['max'][$metric] ?? 1.0);
            $normalized = self::normalizePositive($value, $max);
            $contribution = $weight * $normalized * 100.0;
            $penalty += $weight * $normalized;

            $risks[] = [
                'metric' => $metric,
                'label' => self::METRIC_LABELS[$metric] ?? $metric,
                'value' => round($value, 3),
                'contribution' => round($contribution, 1),
            ];
        }

        usort($strengths, static function (array $a, array $b): int {
            return $b['contribution'] <=> $a['contribution'];
        });
        usort($risks, static function (array $a, array $b): int {
            return $b['contribution'] <=> $a['contribution'];
        });

        $seasonScore = 100.0 * self::clamp(
            0.35 * self::ratingToScore($rating)
            + 0.65 * $weighted
            - 0.45 * $penalty
        );

        return [
            'score' => $seasonScore,
            'strengths' => $strengths,
            'risks' => $risks,
        ];
    }

    /**
     * Рассчитывает форму, устойчивость и тренд по последним матчам.
     *
     * @param float[] $recentRatings
     * @return array<string, float|int|null>
     */
    private static function buildFormComponent(array $recentRatings, float $seasonRating): array
    {
        $ratings = array_values(array_filter(array_map(static function ($value): ?float {
            return is_numeric($value) ? (float) $value : null;
        }, $recentRatings), static function (?float $value): bool {
            return $value !== null;
        }));

        if ($ratings === []) {
            $fallbackScore = self::ratingToScore($seasonRating) * 100.0;

            return [
                'score' => $fallbackScore,
                'stability_score' => 50.0,
                'trend_score' => 50.0,
                'weighted_rating' => null,
                'average_rating' => null,
                'delta' => 0.0,
                'sample_size' => 0,
            ];
        }

        $weightSum = 0.0;
        $weightedSum = 0.0;
        foreach ($ratings as $index => $rating) {
            $weight = pow(self::FORM_DECAY, $index);
            $weightSum += $weight;
            $weightedSum += $rating * $weight;
        }

        $weightedRating = $weightSum > 0.0 ? $weightedSum / $weightSum : null;
        $averageRating = array_sum($ratings) / count($ratings);
        $formScore = $weightedRating !== null ? self::ratingToScore($weightedRating) * 100.0 : 50.0;

        $mean = $averageRating;
        $variance = 0.0;
        foreach ($ratings as $rating) {
            $variance += pow($rating - $mean, 2);
        }
        $variance /= count($ratings);
        $std = sqrt($variance);
        $stabilityScore = 100.0 * self::clamp(1.0 - ($std / max($mean, 1.0)));

        $chronological = array_reverse($ratings);
        $slope = self::linearSlope($chronological);
        $trendScore = 50.0 + 50.0 * self::clamp($slope / self::TREND_SLOPE_SCALE, -1.0, 1.0);
        $delta = $ratings[0] - $ratings[count($ratings) - 1];

        return [
            'score' => $formScore,
            'stability_score' => $stabilityScore,
            'trend_score' => $trendScore,
            'weighted_rating' => $weightedRating,
            'average_rating' => $averageRating,
            'delta' => $delta,
            'sample_size' => count($ratings),
        ];
    }

    /**
     * Оценивает надежность вывода по объему сезонной и матчевой выборки.
     *
     * @return array{factor:float,score:float}
     */
    private static function buildReliabilityComponent(PlayerSeasonStat $stat, int $recentMatchCount): array
    {
        $minutes = (float) ($stat->minutes_played ?? 0.0);
        $appearances = (float) ($stat->appearances ?? 0.0);

        $minutesFactor = sqrt(self::clamp($minutes / self::BASE_SAMPLE_MINUTES));
        $appearancesFactor = self::clamp($appearances / self::BASE_SAMPLE_APPEARANCES);
        $matchFactor = self::clamp($recentMatchCount / self::BASE_SAMPLE_MATCHES);

        $factor = self::clamp(0.55 * $minutesFactor + 0.20 * $appearancesFactor + 0.25 * $matchFactor);

        return [
            'factor' => $factor,
            'score' => $factor * 100.0,
        ];
    }

    /**
     * Считает наклон линейного тренда.
     *
     * @param float[] $values
     */
    private static function linearSlope(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumXX = 0.0;

        foreach ($values as $index => $value) {
            $x = (float) $index;
            $y = (float) $value;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = $count * $sumXX - $sumX * $sumX;
        if ($denominator == 0.0) {
            return 0.0;
        }

        return ($count * $sumXY - $sumX * $sumY) / $denominator;
    }
}
