<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Модель формы обратной связи.
 */
class ContactForm extends Model
{
    /** @var string|null Имя отправителя. */
    public $name;

    /** @var string|null Email отправителя. */
    public $email;

    /** @var string|null Тема сообщения. */
    public $subject;

    /** @var string|null Текст сообщения. */
    public $body;

    /** @var string|null Код капчи. */
    public $verifyCode;

    /**
     * Возвращает правила валидации формы.
     *
     * @return array<int, array>
     */
    public function rules(): array
    {
        return [
            [['name', 'email', 'subject', 'body'], 'required'],
            ['email', 'email'],
            ['verifyCode', 'captcha'],
        ];
    }

    /**
     * Возвращает человекочитаемые названия полей формы.
     *
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'verifyCode' => 'Verification Code',
        ];
    }

    /**
     * Отправляет письмо на указанный адрес.
     *
     * @param string $email email получателя.
     */
    public function contact(string $email): bool
    {
        if (!$this->validate()) {
            return false;
        }

        Yii::$app->mailer->compose()
            ->setTo($email)
            ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
            ->setReplyTo([$this->email => $this->name])
            ->setSubject($this->subject)
            ->setTextBody($this->body)
            ->send();

        return true;
    }
}
