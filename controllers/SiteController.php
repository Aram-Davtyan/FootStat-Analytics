<?php

namespace app\controllers;

use app\models\LoginForm;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Контроллер базовой навигации приложения и аутентификации.
 */
class SiteController extends Controller
{
    /**
     * Настраивает правила доступа и HTTP-методы.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'except' => ['login', 'error', 'captcha'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
                'denyCallback' => static function () {
                    return Yii::$app->getResponse()->redirect(['site/login']);
                },
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Регистрирует встроенные действия Yii (ошибка/капча).
     *
     * @return array<string, mixed>
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => 'yii\\web\\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\\captcha\\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Перенаправляет главную страницу на раздел игроков.
     */
    public function actionIndex(): Response
    {
        return $this->redirect(['player/index']);
    }

    /**
     * Выполняет вход пользователя.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect(['player/index']);
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect(['player/index']);
        }

        $model->password = '';

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Выполняет выход и возвращает пользователя на домашнюю страницу.
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Поддерживает старый URL контактов, перенаправляя в раздел игроков.
     */
    public function actionContact(): Response
    {
        return $this->redirect(['player/index']);
    }

    /**
     * Поддерживает старый URL "О проекте", перенаправляя в раздел игроков.
     */
    public function actionAbout(): Response
    {
        return $this->redirect(['player/index']);
    }

    /**
     * Поддерживает старый URL `/site/players` и прокидывает query-параметры.
     */
    public function actionPlayers(): Response
    {
        return $this->redirect(array_merge(['player/index'], Yii::$app->request->get()));
    }
}
