<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * ActiveRecord-модель сезонной статистики игрока.
 *
 * @property int $id
 * @property int $favorite_id
 * @property int $season_id
 * @property string|null $season_name
 * @property string|null $season_year
 * @property int|null $start_year
 * @property int|null $end_year
 * @property int|null $tournament_id
 * @property string|null $tournament_name
 * @property int|null $team_id
 * @property string|null $team_name
 * @property string|null $position
 * @property int|null $minutes_played
 * @property int|null $appearances
 * @property int|null $goals
 * @property int|null $assists
 * @property float|null $expected_goals
 * @property float|null $expected_assists
 * @property float|null $rating
 * @property int|null $key_passes
 * @property int|null $shots_on_target
 * @property int|null $total_shots
 * @property int|null $tackles
 * @property int|null $interceptions
 * @property int|null $accurate_passes
 * @property int|null $total_passes
 * @property int|null $aerial_duels_won
 * @property int|null $successful_dribbles
 * @property int|null $clean_sheet
 * @property int|null $saves
 * @property int|null $goals_conceded
 * @property float|null $goals_prevented
 * @property int|null $dribbled_past
 * @property string|null $raw_json
 * @property int $created_at
 * @property int $updated_at
 */
class PlayerSeasonStat extends ActiveRecord
{
    /**
     * Возвращает имя таблицы модели.
     */
    public static function tableName(): string
    {
        return '{{%player_season_stat}}';
    }

    /**
     * Подключает авто-заполнение `created_at` и `updated_at`.
     *
     * @return array<int, string>
     */
    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    /**
     * Возвращает правила валидации модели.
     *
     * @return array<int, array>
     */
    public function rules(): array
    {
        return [
            [['favorite_id', 'season_id'], 'required'],
            [[
                'favorite_id',
                'season_id',
                'start_year',
                'end_year',
                'tournament_id',
                'team_id',
                'minutes_played',
                'appearances',
                'goals',
                'assists',
                'key_passes',
                'shots_on_target',
                'total_shots',
                'tackles',
                'interceptions',
                'accurate_passes',
                'total_passes',
                'aerial_duels_won',
                'successful_dribbles',
                'clean_sheet',
                'saves',
                'goals_conceded',
                'dribbled_past',
            ], 'integer'],
            [['expected_goals', 'expected_assists', 'rating', 'goals_prevented'], 'number'],
            [['raw_json'], 'string'],
            [['season_name', 'season_year', 'tournament_name', 'team_name', 'position'], 'string', 'max' => 255],
        ];
    }

    /**
     * Возвращает связь с записью избранного игрока.
     */
    public function getFavorite()
    {
        return $this->hasOne(FavoritePlayer::class, ['id' => 'favorite_id']);
    }
}
