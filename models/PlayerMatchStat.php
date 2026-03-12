<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * ActiveRecord-модель статистики игрока в отдельном матче.
 *
 * @property int $id
 * @property int $favorite_id
 * @property int $match_id
 * @property int|null $played_at
 * @property int|null $tournament_id
 * @property string|null $tournament_name
 * @property int|null $season_id
 * @property string|null $season_name
 * @property int|null $team_id
 * @property string|null $team_name
 * @property int|null $opponent_id
 * @property string|null $opponent_name
 * @property int|null $minutes_played
 * @property float|null $rating
 * @property int|null $goals
 * @property int|null $assists
 * @property int|null $key_passes
 * @property int|null $shots_on_target
 * @property int|null $total_shots
 * @property int|null $accurate_passes
 * @property int|null $total_passes
 * @property int|null $aerial_won
 * @property int|null $aerial_lost
 * @property int|null $duel_won
 * @property int|null $duel_lost
 * @property int|null $fouls
 * @property int|null $was_fouled
 * @property int|null $possession_lost
 * @property int|null $dispossessed
 * @property int|null $touches
 * @property string|null $raw_json
 * @property int $created_at
 * @property int $updated_at
 */
class PlayerMatchStat extends ActiveRecord
{
    /**
     * Возвращает имя таблицы модели.
     */
    public static function tableName(): string
    {
        return '{{%player_match_stat}}';
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
            [['favorite_id', 'match_id'], 'required'],
            [[
                'favorite_id',
                'match_id',
                'played_at',
                'tournament_id',
                'season_id',
                'team_id',
                'opponent_id',
                'minutes_played',
                'goals',
                'assists',
                'key_passes',
                'shots_on_target',
                'total_shots',
                'accurate_passes',
                'total_passes',
                'aerial_won',
                'aerial_lost',
                'duel_won',
                'duel_lost',
                'fouls',
                'was_fouled',
                'possession_lost',
                'dispossessed',
                'touches',
            ], 'integer'],
            [['rating'], 'number'],
            [['raw_json'], 'string'],
            [['tournament_name', 'season_name', 'team_name', 'opponent_name'], 'string', 'max' => 255],
            [['favorite_id', 'match_id'], 'unique', 'targetAttribute' => ['favorite_id', 'match_id']],
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
