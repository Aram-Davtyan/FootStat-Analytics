<?php

use yii\db\Migration;

/**
 * Создает таблицу матчевой статистики игроков.
 */
class m260205_120200_create_player_match_stat_table extends Migration
{
    /**
     * Применяет миграцию создания таблицы `player_match_stat`.
     */
    public function safeUp()
    {
        $this->createTable('{{%player_match_stat}}', [
            'id' => $this->primaryKey(),
            'favorite_id' => $this->integer()->notNull(),
            'match_id' => $this->integer()->notNull(),
            'played_at' => $this->integer()->null(),
            'tournament_id' => $this->integer()->null(),
            'tournament_name' => $this->string()->null(),
            'season_id' => $this->integer()->null(),
            'season_name' => $this->string()->null(),
            'team_id' => $this->integer()->null(),
            'team_name' => $this->string()->null(),
            'opponent_id' => $this->integer()->null(),
            'opponent_name' => $this->string()->null(),
            'minutes_played' => $this->integer()->null(),
            'rating' => $this->float()->null(),
            'goals' => $this->integer()->null(),
            'assists' => $this->integer()->null(),
            'key_passes' => $this->integer()->null(),
            'shots_on_target' => $this->integer()->null(),
            'total_shots' => $this->integer()->null(),
            'accurate_passes' => $this->integer()->null(),
            'total_passes' => $this->integer()->null(),
            'aerial_won' => $this->integer()->null(),
            'aerial_lost' => $this->integer()->null(),
            'duel_won' => $this->integer()->null(),
            'duel_lost' => $this->integer()->null(),
            'fouls' => $this->integer()->null(),
            'was_fouled' => $this->integer()->null(),
            'possession_lost' => $this->integer()->null(),
            'dispossessed' => $this->integer()->null(),
            'touches' => $this->integer()->null(),
            'raw_json' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_player_match_stat_favorite', '{{%player_match_stat}}', 'favorite_id');
        $this->createIndex('idx_player_match_stat_match', '{{%player_match_stat}}', 'match_id');
        $this->createIndex('uq_player_match_stat_unique', '{{%player_match_stat}}', ['favorite_id', 'match_id'], true);

        $this->addForeignKey(
            'fk_player_match_stat_favorite',
            '{{%player_match_stat}}',
            'favorite_id',
            '{{%favorite_player}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * Откатывает миграцию таблицы `player_match_stat`.
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_player_match_stat_favorite', '{{%player_match_stat}}');
        $this->dropTable('{{%player_match_stat}}');
    }
}
