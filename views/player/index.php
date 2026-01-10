<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var array $players */
/** @var string|null $error */
/** @var string $query */
/** @var string $country */
/** @var string $position */
/** @var array $positions */
/** @var array $countries */
/** @var int $teamId */
/** @var int $limit */

$this->title = 'Игроки Sofascore';
?>

<div class="site-players container py-4">
    <h1 class="mb-4">Игроки@</h1>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= Html::encode(Url::to(['player/index'])) ?>" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Поиск (имя/фамилия)</label>
                    <input type="text" name="q" value="<?= Html::encode($query) ?>" class="form-control" placeholder="Например: messi">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Страна</label>
                    <select name="country" class="form-select">
                        <option value="">Все</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= Html::encode($c) ?>" <?= $c === $country ? 'selected' : '' ?>><?= Html::encode($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Позиция</label>
                    <select name="position" class="form-select">
                        <option value="">Все</option>
                        <?php foreach ($positions as $p): ?>
                            <option value="<?= Html::encode($p) ?>" <?= $p === $position ? 'selected' : '' ?>><?= Html::encode($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Team ID</label>
                    <input type="number" name="teamId" value="<?= Html::encode($teamId) ?>" class="form-control" placeholder="например 38">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Лимит</label>
                    <select name="limit" class="form-select">
                        <?php foreach ([10, 20, 50, 100] as $l): ?>
                            <option value="<?= $l ?>" <?= $l == $limit ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Искать</button>
                    <a class="btn btn-outline-secondary" href="<?= Html::encode(Url::to(['player/index'])) ?>">Сбросить</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= Html::encode($error) ?></div>
    <?php endif; ?>

    <?php if ($players): ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Имя</th>
                        <th>Позиция</th>
                        <th>Команда</th>
                        <th>Страна</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): ?>
                        <tr>
                            <td><?= Html::encode($player->id ?? '') ?></td>
                            <td>
                                <?php if (!empty($player->id)): ?>
                                    <a href="<?= Html::encode(Url::to(['player/view', 'id' => $player->id])) ?>">
                                        <?= Html::encode($player->name ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    <?= Html::encode($player->name ?? '') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= Html::encode($player->position ?? '') ?></td>
                            <td><?= Html::encode($player->teamName ?? '') ?></td>
                            <td><?= Html::encode($player->country ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($query !== '' && !$error): ?>
        <div class="alert alert-warning">По запросу ничего не найдено.</div>
    <?php else: ?>
        <p class="text-muted">Введите строку поиска, чтобы увидеть игроков.</p>
    <?php endif; ?>
</div>
