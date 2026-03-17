<?php

namespace tests\unit\components\analytics;

use app\components\analytics\PlayerAnalytics;
use app\models\PlayerSeasonStat;

class PlayerAnalyticsTest extends \Codeception\Test\Unit
{
    public function testEfficiencyIndexReturnsExtendedAnalyticsPayload(): void
    {
        $stat = $this->makeForwardSeasonStat([
            'minutes_played' => 2520,
            'appearances' => 31,
            'goals' => 18,
            'assists' => 7,
            'expected_goals' => 15.3,
            'expected_assists' => 5.8,
            'rating' => 7.42,
            'shots_on_target' => 41,
            'total_shots' => 82,
            'key_passes' => 36,
            'successful_dribbles' => 49,
        ]);

        $analytics = PlayerAnalytics::efficiencyIndex($stat, [7.9, 7.7, 7.5, 7.2, 7.0]);

        $this->assertSame('FW', $analytics['group']);
        $this->assertArrayHasKey('season_score', $analytics);
        $this->assertArrayHasKey('form_score', $analytics);
        $this->assertArrayHasKey('stability_score', $analytics);
        $this->assertArrayHasKey('reliability_score', $analytics);
        $this->assertArrayHasKey('strengths', $analytics);
        $this->assertArrayHasKey('risks', $analytics);
        $this->assertGreaterThan(0, $analytics['index']);
        $this->assertLessThanOrEqual(100, $analytics['index']);
        $this->assertGreaterThan(50, $analytics['trend_score']);
        $this->assertNotEmpty($analytics['strengths']);
    }

    public function testReliabilityPullsLowSampleTowardNeutralZone(): void
    {
        $richSample = $this->makeForwardSeasonStat([
            'minutes_played' => 2610,
            'appearances' => 30,
            'goals' => 17,
            'assists' => 8,
            'expected_goals' => 14.7,
            'expected_assists' => 5.1,
            'rating' => 7.3,
            'shots_on_target' => 39,
            'total_shots' => 80,
            'key_passes' => 33,
            'successful_dribbles' => 44,
        ]);
        $smallSample = $this->makeForwardSeasonStat([
            'minutes_played' => 180,
            'appearances' => 2,
            'goals' => 2,
            'assists' => 1,
            'expected_goals' => 1.5,
            'expected_assists' => 0.7,
            'rating' => 7.3,
            'shots_on_target' => 4,
            'total_shots' => 8,
            'key_passes' => 3,
            'successful_dribbles' => 5,
        ]);

        $richAnalytics = PlayerAnalytics::efficiencyIndex($richSample, [7.8, 7.6, 7.4, 7.3, 7.2]);
        $smallAnalytics = PlayerAnalytics::efficiencyIndex($smallSample, []);

        $this->assertGreaterThan($smallAnalytics['reliability_score'], $richAnalytics['reliability_score']);
        $this->assertLessThan(
            abs($richAnalytics['index'] - 50.0),
            abs($smallAnalytics['index'] - 50.0)
        );
    }

    public function testPredictNextMatchExposesForecastAndConfidence(): void
    {
        $stat = $this->makeForwardSeasonStat([
            'minutes_played' => 2250,
            'appearances' => 28,
            'goals' => 16,
            'assists' => 6,
            'expected_goals' => 13.8,
            'expected_assists' => 4.9,
            'rating' => 7.18,
            'shots_on_target' => 35,
            'total_shots' => 70,
            'key_passes' => 29,
            'successful_dribbles' => 38,
        ]);

        $forecast = PlayerAnalytics::predictNextMatch($stat, [8.0, 7.8, 7.6, 7.5, 7.4]);

        $this->assertArrayHasKey('predicted_index', $forecast);
        $this->assertArrayHasKey('predicted_rating', $forecast);
        $this->assertArrayHasKey('confidence', $forecast);
        $this->assertGreaterThanOrEqual(0, $forecast['predicted_index']);
        $this->assertLessThanOrEqual(100, $forecast['predicted_index']);
        $this->assertGreaterThanOrEqual(0, $forecast['predicted_rating']);
        $this->assertLessThanOrEqual(10, $forecast['predicted_rating']);
    }

    private function makeForwardSeasonStat(array $attributes): PlayerSeasonStat
    {
        return new class (array_merge([
            'position' => 'FW',
            'accurate_passes' => 620,
            'total_passes' => 770,
            'tackles' => 14,
            'interceptions' => 7,
            'aerial_duels_won' => 52,
            'clean_sheet' => 3,
            'saves' => 0,
            'goals_conceded' => 0,
            'goals_prevented' => 0,
            'dribbled_past' => 12,
        ], $attributes)) extends PlayerSeasonStat {
            public function attributes(): array
            {
                return [
                    'position',
                    'minutes_played',
                    'appearances',
                    'goals',
                    'assists',
                    'expected_goals',
                    'expected_assists',
                    'rating',
                    'shots_on_target',
                    'total_shots',
                    'key_passes',
                    'successful_dribbles',
                    'accurate_passes',
                    'total_passes',
                    'tackles',
                    'interceptions',
                    'aerial_duels_won',
                    'clean_sheet',
                    'saves',
                    'goals_conceded',
                    'goals_prevented',
                    'dribbled_past',
                ];
            }
        };
    }
}
