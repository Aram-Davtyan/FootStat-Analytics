<?php

use yii\db\Migration;

/**
 * Создает таблицу пользователей и добавляет базовые аккаунты.
 */
class m240825_000001_create_user_table extends Migration
{
    /**
     * Применяет миграцию создания таблицы `user`.
     */
    public function safeUp()
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string()->notNull()->unique(),
            'password_hash' => $this->string()->notNull(),
            'auth_key' => $this->string(32)->notNull(),
            'access_token' => $this->string(64)->unique(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $security = \Yii::$app->security;
        $now = time();
        $users = [
            [
                'username' => 'admin',
                'password_hash' => $security->generatePasswordHash('admin'),
                'auth_key' => $security->generateRandomString(),
                'access_token' => $security->generateRandomString(64),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'username' => 'demo',
                'password_hash' => $security->generatePasswordHash('demo'),
                'auth_key' => $security->generateRandomString(),
                'access_token' => $security->generateRandomString(64),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $this->batchInsert('{{%user}}', array_keys($users[0]), $users);
    }

    /**
     * Откатывает миграцию таблицы `user`.
     */
    public function safeDown()
    {
        $this->dropTable('{{%user}}');
    }
}
