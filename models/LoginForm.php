<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Модель формы аутентификации пользователя.
 *
 * @property-read User|null $user
 */
class LoginForm extends Model
{
    /** @var string|null Логин пользователя. */
    public $username;

    /** @var string|null Пароль пользователя. */
    public $password;

    /** @var bool Сохранять ли сессию между посещениями. */
    public $rememberMe = true;

    /** @var User|false Кеш найденного пользователя. */
    private $_user = false;

    /**
     * Возвращает человекочитаемые названия полей формы.
     *
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'username' => 'Логин',
            'password' => 'Пароль',
            'rememberMe' => 'Запомнить меня',
        ];
    }

    /**
     * Возвращает правила валидации формы.
     *
     * @return array<int, array>
     */
    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Проверяет корректность пароля.
     *
     * @param string $attribute имя валидируемого атрибута.
     * @param array<string, mixed> $params дополнительные параметры правила.
     */
    public function validatePassword($attribute, $params): void
    {
        if ($this->hasErrors()) {
            return;
        }

        $user = $this->getUser();
        if ($user === null || !$user->validatePassword((string) $this->password)) {
            $this->addError($attribute, 'Неверный логин или пароль.');
        }
    }

    /**
     * Выполняет аутентификацию и логин пользователя.
     */
    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        return Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600 * 24 * 30 : 0);
    }

    /**
     * Ищет и кеширует пользователя по логину.
     */
    public function getUser(): ?User
    {
        if ($this->_user === false) {
            $this->_user = User::findByUsername((string) $this->username);
        }

        return $this->_user instanceof User ? $this->_user : null;
    }
}
