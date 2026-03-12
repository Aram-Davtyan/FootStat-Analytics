<?php

use yii\db\Migration;

/**
 * Создает таблицу сезонной статистики игроков.
 */
class m260205_120100_create_player_season_stat_table extends Migration
{
    /**
     * Применяет миграцию создания таблицы `player_season_stat`.
     */
    public function safeUp()
    {
        $this->createTable('{{%player_season_stat}}', [
            'id' => $this->primaryKey(),
            'favorite_id' => $this->integer()->notNull(),
            'season_id' => $this->integer()->notNull(),
            'season_name' => $this->string()->null(),
            'season_year' => $this->string()->null(),
            'start_year' => $this->integer()->null(),
            'end_year' => $this->integer()->null(),
            'tournament_id' => $this->integer()->null(),
            'tournament_name' => $this->string()->null(),
            'team_id' => $this->integer()->null(),
            'team_name' => $this->string()->null(),
            'position' => $this->string()->null(),
            'minutes_played' => $this->integer()->null(),
            'appearances' => $this->integer()->null(),
            'goals' => $this->integer()->null(),
            'assists' => $this->integer()->null(),
            'expected_goals' => $this->float()->null(),
            'expected_assists' => $this->float()->null(),
            'rating' => $this->float()->null(),
            'key_passes' => $this->integer()->null(),
            'shots_on_target' => $this->integer()->null(),
            'total_shots' => $this->integer()->null(),
            'tackles' => $this->integer()->null(),
            'interceptions' => $this->integer()->null(),
            'accurate_passes' => $this->integer()->null(),
            'total_passes' => $this->integer()->null(),
            'aerial_duels_won' => $this->integer()->null(),
            'successful_dribbles' => $this->integer()->null(),
            'clean_sheet' => $this->integer()->null(),
            'saves' => $this->integer()->null(),
            'goals_conceded' => $this->integer()->null(),
            'goals_prevented' => $this->float()->null(),
            'dribbled_past' => $this->integer()->null(),
            'raw_json' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_player_season_stat_favorite', '{{%player_season_stat}}', 'favorite_id');
        $this->createIndex(
            'uq_player_season_stat_unique',
            '{{%player_season_stat}}',
            ['favorite_id', 'season_id', 'tournament_id', 'team_id'],
            true
        );

        $this->addForeignKey(
            'fk_player_season_stat_favorite',
            '{{%player_season_stat}}',
            'favorite_id',
            '{{%favorite_player}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * Откатывает миграцию таблицы `player_season_stat`.
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_player_season_stat_favorite', '{{%player_season_stat}}');
        $this->dropTable('{{%player_season_stat}}');
    }
}
