<?php

use yii\db\Migration;

/**
 * Создает таблицу избранных игроков пользователя.
 */
class m260205_120000_create_favorite_player_table extends Migration
{
    /**
     * Применяет миграцию создания таблицы `favorite_player`.
     */
    public function safeUp()
    {
        $this->createTable('{{%favorite_player}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'player_id' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'position' => $this->string()->null(),
            'team_id' => $this->integer()->null(),
            'team_name' => $this->string()->null(),
            'country' => $this->string()->null(),
            'image_url' => $this->string()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_favorite_player_user', '{{%favorite_player}}', 'user_id');
        $this->createIndex('uq_favorite_player_user_player', '{{%favorite_player}}', ['user_id', 'player_id'], true);

        $this->addForeignKey(
            'fk_favorite_player_user',
            '{{%favorite_player}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * Откатывает миграцию таблицы `favorite_player`.
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_favorite_player_user', '{{%favorite_player}}');
        $this->dropTable('{{%favorite_player}}');
    }
}
