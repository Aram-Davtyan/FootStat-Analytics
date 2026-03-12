<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * ActiveRecord-модель избранного игрока пользователя.
 *
 * @property int $id
 * @property int $user_id
 * @property int $player_id
 * @property string $name
 * @property string|null $position
 * @property int|null $team_id
 * @property string|null $team_name
 * @property string|null $country
 * @property string|null $image_url
 * @property int $created_at
 * @property int $updated_at
 */
class FavoritePlayer extends ActiveRecord
{
    /**
     * Возвращает имя таблицы модели.
     */
    public static function tableName(): string
    {
        return '{{%favorite_player}}';
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
            [['user_id', 'player_id', 'name'], 'required'],
            [['user_id', 'player_id', 'team_id'], 'integer'],
            [['name', 'position', 'team_name', 'country', 'image_url'], 'string', 'max' => 255],
            [['user_id', 'player_id'], 'unique', 'targetAttribute' => ['user_id', 'player_id']],
        ];
    }

    /**
     * Возвращает связь со статистикой игрока по сезонам.
     */
    public function getSeasonStats()
    {
        return $this->hasMany(PlayerSeasonStat::class, ['favorite_id' => 'id']);
    }

    /**
     * Возвращает связь со статистикой игрока по матчам.
     */
    public function getMatchStats()
    {
        return $this->hasMany(PlayerMatchStat::class, ['favorite_id' => 'id']);
    }
}
