<?php

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Пример консольной команды для проверки CLI-окружения.
 */
class HelloController extends Controller
{
    /**
     * Выводит переданное сообщение в консоль.
     *
     * @param string $message сообщение для вывода.
     */
    public function actionIndex($message = 'hello world'): int
    {
        echo $message . "\n";

        return ExitCode::OK;
    }
}
