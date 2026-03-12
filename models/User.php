<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * ActiveRecord-модель пользователя, реализующая IdentityInterface.
 *
 * @property int $id
 * @property string $username
 * @property string $password_hash
 * @property string $auth_key
 * @property string|null $access_token
 * @property int $created_at
 * @property int $updated_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    /**
     * Возвращает имя таблицы модели.
     */
    public static function tableName(): string
    {
        return '{{%user}}';
    }

    /**
     * Подключает авто-заполнение `created_at` и `updated_at`.
     *
     * @return array<int, string>
     */
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * Находит пользователя по первичному ключу.
     *
     * @param int|string $id идентификатор пользователя.
     * @return static|null
     */
    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    /**
     * Находит пользователя по access token.
     *
     * @param string $token токен доступа.
     * @param mixed $type не используется, оставлен для сигнатуры интерфейса.
     * @return static|null
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['access_token' => $token]);
    }

    /**
     * Находит пользователя по логину.
     *
     * @param string $username логин пользователя.
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username]);
    }

    /**
     * Возвращает идентификатор текущей identity-модели.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Возвращает auth key пользователя.
     */
    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    /**
     * Сверяет auth key.
     *
     * @param string $authKey ключ для проверки.
     */
    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    /**
     * Проверяет пароль пользователя.
     *
     * @param string $password пароль в открытом виде.
     */
    public function validatePassword($password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }
}
