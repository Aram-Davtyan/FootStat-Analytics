<?php

use yii\db\Migration;

/**
 * Создает базовые роли RBAC.
 */
class m240825_000002_seed_rbac extends Migration
{
    /**
     * Добавляет преднастроенные роли в RBAC.
     */
    public function safeUp()
    {
        $auth = \Yii::$app->authManager;

        $roles = [
            'scout',
            'coach',
            'admin',
            'system_admin',
        ];

        foreach ($roles as $roleName) {
            if ($auth->getRole($roleName) === null) {
                $auth->add($auth->createRole($roleName));
            }
        }
    }

    /**
     * Удаляет все RBAC-роли и связи.
     */
    public function safeDown()
    {
        $auth = \Yii::$app->authManager;
        $auth->removeAll();
    }
}
