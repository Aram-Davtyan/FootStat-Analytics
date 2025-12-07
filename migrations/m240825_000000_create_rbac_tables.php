<?php

use yii\db\Migration;

/**
 * Создает таблицы RBAC, если еще не созданы.
 */
class m240825_000000_create_rbac_tables extends Migration
{
    public function safeUp()
    {
        $auth = \Yii::$app->authManager;

        // Если основная таблица уже существует, считаем схему установленной.
        if ($this->db->getTableSchema($auth->itemTable, true) !== null) {
            return;
        }

        $this->createTable($auth->ruleTable, [
            'name' => $this->string(64)->notNull(),
            'data' => $this->binary(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
            'PRIMARY KEY(name)',
        ]);

        $this->createTable($auth->itemTable, [
            'name' => $this->string(64)->notNull(),
            'type' => $this->integer()->notNull(),
            'description' => $this->text(),
            'rule_name' => $this->string(64),
            'data' => $this->binary(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
            'PRIMARY KEY(name)',
        ]);
        $this->createIndex('idx-auth_item-type', $auth->itemTable, 'type');
        $this->addForeignKey('fk-auth_item-rule_name', $auth->itemTable, 'rule_name', $auth->ruleTable, 'name', 'SET NULL', 'CASCADE');

        $this->createTable($auth->itemChildTable, [
            'parent' => $this->string(64)->notNull(),
            'child' => $this->string(64)->notNull(),
            'PRIMARY KEY(parent, child)',
        ]);
        $this->addForeignKey('fk-auth_item_child-parent', $auth->itemChildTable, 'parent', $auth->itemTable, 'name', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-auth_item_child-child', $auth->itemChildTable, 'child', $auth->itemTable, 'name', 'CASCADE', 'CASCADE');

        $this->createTable($auth->assignmentTable, [
            'item_name' => $this->string(64)->notNull(),
            'user_id' => $this->string(64)->notNull(),
            'created_at' => $this->integer(),
            'PRIMARY KEY(item_name, user_id)',
        ]);
        $this->createIndex('idx-auth_assignment-user_id', $auth->assignmentTable, 'user_id');
        $this->addForeignKey('fk-auth_assignment-item_name', $auth->assignmentTable, 'item_name', $auth->itemTable, 'name', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $auth = \Yii::$app->authManager;

        if ($this->db->getTableSchema($auth->itemTable, true) === null) {
            return;
        }

        $this->dropForeignKey('fk-auth_assignment-item_name', $auth->assignmentTable);
        $this->dropTable($auth->assignmentTable);

        $this->dropForeignKey('fk-auth_item_child-child', $auth->itemChildTable);
        $this->dropForeignKey('fk-auth_item_child-parent', $auth->itemChildTable);
        $this->dropTable($auth->itemChildTable);

        $this->dropForeignKey('fk-auth_item-rule_name', $auth->itemTable);
        $this->dropTable($auth->itemTable);

        $this->dropTable($auth->ruleTable);
    }
}
