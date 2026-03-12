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
        ];
    }

    /**
     * Рассчитывает интегральный индекс эффективности игрока (0-100).
     *
     * @param PlayerSeasonStat $stat запись сезонной статистики.
     * @return array<string, mixed>
     */
    public static function efficiencyIndex(PlayerSeasonStat $stat): array
    {
        $group = self::positionGroup($stat->position);
        $profile = self::PROFILE[$group] ?? self::PROFILE['MF'];
        $metrics = self::buildMetrics($stat);

        $weighted = 0.0;
        foreach ($profile['weights'] as $metric => $weight) {
            $value = $metrics[$metric] ?? 0.0;
            $max = $profile['max'][$metric] ?? 1.0;
            $weighted += $weight * self::clamp($value / $max);
        }

        $penalty = 0.0;
        foreach ($profile['penalties'] as $metric => $weight) {
            $value = $metrics[$metric] ?? 0.0;
            $max = $profile['max'][$metric] ?? 1.0;
            $penalty += $weight * self::clamp($value / $max);
        }

        $rating = (float) ($stat->rating ?? 0);
        $ratingScore = self::clamp($rating / 10.0);

        $index = (0.55 * $ratingScore + 0.45 * $weighted - 0.15 * $penalty) * 100.0;
        $index = self::clamp($index, 0.0, 100.0);

        return [
            'index' => round($index, 1),
            'rating' => $rating,
            'metrics' => $metrics,
            'group' => $group,
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
        $appearances = (int) ($stat->appearances ?? 0);
        $avgMinutes = $appearances > 0 ? ($stat->minutes_played / $appearances) : 75.0;
        $expectedMinutes = max(30.0, min(90.0, $avgMinutes));

        $expectedGoals = $metrics['goals_per90'] * $expectedMinutes / 90.0;
        $expectedAssists = $metrics['assists_per90'] * $expectedMinutes / 90.0;
        $expectedXg = $metrics['xg_per90'] * $expectedMinutes / 90.0;
        $expectedXa = $metrics['xa_per90'] * $expectedMinutes / 90.0;

        $rating = (float) ($stat->rating ?? 0);
        $recentAvg = null;
        if ($recentRatings !== []) {
            $recentAvg = array_sum($recentRatings) / count($recentRatings);
        }

        $predRating = $recentAvg !== null ? (0.7 * $rating + 0.3 * $recentAvg) : $rating;

        $index = self::efficiencyIndex($stat)['index'];
        $predIndex = $index;
        if ($recentAvg !== null) {
            $predIndex = self::clamp(($index + ($recentAvg - $rating) * 4.0), 0.0, 100.0);
        }

        return [
            'expected_minutes' => round($expectedMinutes, 1),
            'expected_goals' => round($expectedGoals, 2),
            'expected_assists' => round($expectedAssists, 2),
            'expected_xg' => round($expectedXg, 2),
            'expected_xa' => round($expectedXa, 2),
            'predicted_rating' => round($predRating, 2),
            'predicted_index' => round($predIndex, 1),
            'recent_rating_avg' => $recentAvg !== null ? round($recentAvg, 2) : null,
        ];
    }
}
