<?php

namespace app\widgets;

use Yii;
use yii\bootstrap5\Alert as BootstrapAlert;
use yii\bootstrap5\Widget;

/**
 * Рендерит flash-сообщения из сессии в виде Bootstrap alert-компонентов.
 */
class Alert extends Widget
{
    /**
     * Соответствие ключа flash и CSS-класса алерта.
     *
     * @var array<string, string>
     */
    public $alertTypes = [
        'error' => 'alert-danger',
        'danger' => 'alert-danger',
        'success' => 'alert-success',
        'info' => 'alert-info',
        'warning' => 'alert-warning',
    ];

    /**
     * Настройки кнопки закрытия alert-компонента.
     *
     * @var array<string, mixed>
     */
    public $closeButton = [];

    /**
     * Отрисовывает все поддерживаемые flash-сообщения и очищает их из сессии.
     */
    public function run(): void
    {
        $session = Yii::$app->session;
        $appendClass = isset($this->options['class']) ? ' ' . $this->options['class'] : '';

        foreach (array_keys($this->alertTypes) as $type) {
            $flash = $session->getFlash($type);

            foreach ((array) $flash as $i => $message) {
                echo BootstrapAlert::widget([
                    'body' => $message,
                    'closeButton' => $this->closeButton,
                    'options' => array_merge($this->options, [
                        'id' => $this->getId() . '-' . $type . '-' . $i,
                        'class' => $this->alertTypes[$type] . $appendClass,
                    ]),
                ]);
            }

            $session->removeFlash($type);
        }
    }
}
