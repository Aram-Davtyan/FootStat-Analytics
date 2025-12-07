<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */

/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Вход';
$this->registerCss(<<<CSS
.login-page {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.login-card {
    width: 100%;
    max-width: 420px;
    padding: 28px 32px;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
}
.login-card h1 {
    font-weight: 700;
    margin-bottom: 12px;
    letter-spacing: 0.02em;
}
.login-card p {
    color: #475467;
    margin-bottom: 22px;
}
.login-card .form-control {
    border-radius: 10px;
    padding: 12px 14px;
}
.login-card .btn-primary {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    font-weight: 600;
    letter-spacing: 0.01em;
}
.login-card .custom-control {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}
CSS);
?>
<div class="site-login login-page">
    <div class="login-card">
        <h1><?= Html::encode($this->title) ?></h1>
        <p>Введите логин и пароль, чтобы войти в систему.</p>

        <?php $form = ActiveForm::begin([
            'id' => 'login-form',
            'fieldConfig' => [
                'template' => "{label}\n{input}\n{error}",
                'labelOptions' => ['class' => 'form-label fw-semibold'],
                'inputOptions' => ['class' => 'form-control'],
                'errorOptions' => ['class' => 'invalid-feedback d-block'],
            ],
        ]); ?>

        <?= $form->field($model, 'username')->textInput(['autofocus' => true, 'placeholder' => 'Ваш логин']) ?>

        <?= $form->field($model, 'password')->passwordInput(['placeholder' => 'Ваш пароль']) ?>

        <?= $form->field($model, 'rememberMe')->checkbox([
            'template' => "<div class=\"custom-control custom-checkbox\">{input} {label}</div>\n<div class=\"col-lg-8\">{error}</div>",
        ]) ?>

        <div class="form-group mt-3">
            <?= Html::submitButton('Войти', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
