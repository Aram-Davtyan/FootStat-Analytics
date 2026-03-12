<?php

namespace app\controllers;

use app\components\analytics\PlayerAnalytics;
use app\components\services\APISofascoreServices;
use app\models\FavoritePlayer;
use app\models\PlayerMatchStat;
use app\models\PlayerSeasonStat;
use Yii;
use yii\db\Expression;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Управляет поиском игроков, профилем и списком избранных.
 */
class PlayerController extends Controller
{
    private const DEFAULT_SEARCH_LIMIT = 20;
    private const MAX_SEARCH_LIMIT = 100;
    private const MATCH_SYNC_LIMIT = 8;

    /**
     * Сервис доступа к данным Sofascore.
     */
    protected APISofascoreServices $sofascoreService;

    /**
     * Настраивает правила доступа и HTTP-методы для действий контроллера.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
                'denyCallback' => static function () {
                    if (Yii::$app->request->isAjax) {
                        Yii::$app->response->format = Response::FORMAT_JSON;
                        Yii::$app->response->statusCode = 401;

                        return ['success' => false, 'error' => 'Unauthorized'];
                    }

                    return Yii::$app->getResponse()->redirect(['site/login']);
                },
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'add-favorite' => ['post'],
                    'remove-favorite' => ['post'],
                    'sync' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Инициализирует контроллер с возможностью подмены сервиса в тестах.
     *
     * @param string $id идентификатор контроллера.
     * @param \yii\base\Module $module модуль, в котором объявлен контроллер.
     * @param array<string, mixed> $config конфигурация контроллера.
     * @param APISofascoreServices|null $sofascoreService внешний сервис (опционально).
     */
    public function __construct($id, $module, $config = [], ?APISofascoreServices $sofascoreService = null)
    {
        $this->sofascoreService = $sofascoreService ?? new APISofascoreServices();
        parent::__construct($id, $module, $config);
    }

    /**
     * Отображает страницу поиска игроков с фильтрами.
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;
        $query = trim((string) $request->get('q', ''));
        $country = trim((string) $request->get('country', ''));
        $position = trim((string) $request->get('position', ''));
        $teamId = (int) $request->get('teamId', 0);

        $limit = (int) $request->get('limit', self::DEFAULT_SEARCH_LIMIT);
        if ($limit <= 0) {
            $limit = self::DEFAULT_SEARCH_LIMIT;
        }
        $limit = min($limit, self::MAX_SEARCH_LIMIT);

        $players = [];
        $positions = [];
        $countries = [];
        $error = null;

        if ($query !== '') {
            try {
                $result = $this->sofascoreService->getPlayersWithFilters(
                    $query,
                    $country,
                    $position,
                    $teamId > 0 ? $teamId : null,
                    $limit
                );
                $players = $result['players'];
                $positions = $result['positions'];
                $countries = $result['countries'];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('index', [
            'players' => $players,
            'error' => $error,
            'query' => $query,
            'country' => $country,
            'position' => $position,
            'positions' => $positions,
            'countries' => $countries,
            'teamId' => $teamId,
            'limit' => $limit,
        ]);
    }

    /**
     * Отображает карточку игрока и состояние избранного для текущего пользователя.
     *
     * @param int|string $id идентификатор игрока.
     */
    public function actionView($id): string
    {
        $playerId = (int) $id;
        $error = null;
        $profile = ['detail' => [], 'imageUrl' => null];
        $favorite = null;

        try {
            $profile['detail'] = $this->sofascoreService->getPlayerDetails($playerId);
            $profile['imageUrl'] = $this->resolvePlayerImageUrl($playerId);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        if (!Yii::$app->user->isGuest) {
            $favorite = $this->findFavoriteForCurrentUser($playerId);
        }

        return $this->render('view', [
            'profile' => $profile,
            'error' => $error,
            'playerId' => $playerId,
            'favorite' => $favorite,
        ]);
    }

    /**
     * Возвращает расширенные данные профиля игрока в JSON.
     *
     * @param int|string $id идентификатор игрока.
     * @return array<string, mixed>
     */
    public function actionProfileData($id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $playerId = (int) $id;

        try {
            return ['success' => true, 'data' => $this->sofascoreService->getPlayerProfile($playerId)];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Отображает список избранных игроков с вычисленной аналитикой.
     */
    public function actionFavorites(): string
    {
        $favorites = FavoritePlayer::find()
            ->where(['user_id' => $this->getCurrentUserId()])
            ->with('seasonStats')
            ->orderBy(['updated_at' => SORT_DESC])
            ->all();

        $matchCountByFavorite = $this->loadMatchCounts($favorites);
        $cards = [];

        foreach ($favorites as $favorite) {
            $mainSeason = $this->pickMainSeason($favorite->seasonStats);
            $recentRatings = $this->getRecentRatingsFromDb((int) $favorite->id, 5);
            $matchCount = $matchCountByFavorite[(int) $favorite->id] ?? 0;
            $analytics = null;
            $forecast = null;

            if ($mainSeason !== null) {
                $analytics = PlayerAnalytics::efficiencyIndex($mainSeason);
                $forecast = PlayerAnalytics::predictNextMatch($mainSeason, $recentRatings);
            }

            $cards[] = [
                'favorite' => $favorite,
                'season' => $mainSeason,
                'analytics' => $analytics,
                'forecast' => $forecast,
                'matchCount' => $matchCount,
                'needsMatchSync' => $matchCount === 0,
            ];
        }

        return $this->render('favorites', [
            'cards' => $cards,
        ]);
    }

    /**
     * Добавляет игрока в избранное текущего пользователя.
     *
     * @param int|string $id идентификатор игрока.
     */
    public function actionAddFavorite($id): Response
    {
        $playerId = (int) $id;
        $userId = $this->getCurrentUserId();

        try {
            $detail = $this->sofascoreService->getPlayerDetails($playerId);
            $favorite = $this->findFavoriteForCurrentUser($playerId) ?? new FavoritePlayer();
            $this->hydrateFavoriteFromApi($favorite, $userId, $playerId, $detail);

            if ($favorite->save()) {
                Yii::$app->session->setFlash('success', 'Игрок добавлен в избранные.');
            } else {
                Yii::$app->session->setFlash('error', $this->extractFirstModelError($favorite));
            }
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['player/favorites']);
    }

    /**
     * Удаляет игрока из избранного текущего пользователя.
     *
     * @param int|string $id идентификатор игрока.
     */
    public function actionRemoveFavorite($id): Response
    {
        $playerId = (int) $id;
        $favorite = $this->findFavoriteForCurrentUser($playerId);

        if ($favorite !== null && $favorite->delete()) {
            Yii::$app->session->setFlash('success', 'Игрок удален из избранных.');
        } else {
            Yii::$app->session->setFlash('error', 'Игрок не найден.');
        }

        return $this->redirect(['player/favorites']);
    }

    /**
     * Синхронизирует сезонную и матчевую статистику игрока из избранного.
     *
     * @param int|string $id идентификатор игрока.
     */
    public function actionSync($id): Response
    {
        $playerId = (int) $id;
        $favorite = $this->findFavoriteForCurrentUser($playerId);

        if ($favorite === null) {
            Yii::$app->session->setFlash('error', 'Игрок не найден в избранных.');

            return $this->redirect(['player/favorites']);
        }

        try {
            $allStats = $this->sofascoreService->getPlayerAllStatistics($playerId);
            $savedSeasons = 0;

            foreach (($allStats['seasons'] ?? []) as $season) {
                $savedSeasons += $this->saveSeasonStats($favorite, $season);
            }

            $savedMatches = $this->syncMatchStats($favorite, self::MATCH_SYNC_LIMIT);
            Yii::$app->session->setFlash(
                'success',
                "Статистика обновлена. Сезонов: {$savedSeasons}, матчей: {$savedMatches}"
            );
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['player/favorites']);
    }

    /**
     * Сохраняет статистику одного сезона в БД.
     *
     * @param FavoritePlayer $favorite запись избранного игрока.
     * @param array<string, mixed> $season данные сезона из API.
     * @return int 1 при успешном сохранении, иначе 0.
     */
    private function saveSeasonStats(FavoritePlayer $favorite, array $season): int
    {
        $stats = $season['statistics'] ?? [];
        $seasonInfo = $season['season'] ?? [];
        $tournament = $season['uniqueTournament'] ?? [];
        $team = $season['team'] ?? [];

        $seasonId = $seasonInfo['id'] ?? null;
        if (!$seasonId) {
            return 0;
        }

        $model = PlayerSeasonStat::findOne([
            'favorite_id' => $favorite->id,
            'season_id' => (int) $seasonId,
            'tournament_id' => isset($tournament['id']) ? (int) $tournament['id'] : null,
            'team_id' => isset($team['id']) ? (int) $team['id'] : null,
        ]) ?? new PlayerSeasonStat();

        $model->favorite_id = $favorite->id;
        $model->season_id = (int) $seasonId;
        $model->season_name = $seasonInfo['name'] ?? null;
        $model->season_year = $seasonInfo['year'] ?? ($season['year'] ?? null);
        $model->start_year = $season['startYear'] ?? null;
        $model->end_year = $season['endYear'] ?? null;
        $model->tournament_id = $tournament['id'] ?? null;
        $model->tournament_name = $tournament['name'] ?? null;
        $model->team_id = $team['id'] ?? null;
        $model->team_name = $team['name'] ?? null;
        $model->position = $favorite->position;
        $model->minutes_played = $stats['minutesPlayed'] ?? null;
        $model->appearances = $stats['appearances'] ?? null;
        $model->goals = $stats['goals'] ?? null;
        $model->assists = $stats['assists'] ?? null;
        $model->expected_goals = $stats['expectedGoals'] ?? null;
        $model->expected_assists = $stats['expectedAssists'] ?? null;
        $model->rating = $stats['rating'] ?? null;
        $model->key_passes = $stats['keyPasses'] ?? null;
        $model->shots_on_target = $stats['shotsOnTarget'] ?? null;
        $model->total_shots = $stats['totalShots'] ?? null;
        $model->tackles = $stats['tackles'] ?? null;
        $model->interceptions = $stats['interceptions'] ?? null;
        $model->accurate_passes = $stats['accuratePasses'] ?? null;
        $model->total_passes = $stats['totalPasses'] ?? null;
        $model->aerial_duels_won = $stats['aerialDuelsWon'] ?? null;
        $model->successful_dribbles = $stats['successfulDribbles'] ?? null;
        $model->clean_sheet = $stats['cleanSheet'] ?? null;
        $model->saves = $stats['saves'] ?? null;
        $model->goals_conceded = $stats['goalsConceded'] ?? null;
        $model->goals_prevented = $stats['goalsPrevented'] ?? null;
        $model->dribbled_past = $stats['dribbledPast'] ?? null;
        $model->raw_json = $this->encodeJson($season);

        return $model->save() ? 1 : 0;
    }

    /**
     * Выбирает основной сезон: самый свежий, при равенстве по дате — с большим числом минут.
     *
     * @param array<int, PlayerSeasonStat> $stats массив статистик по сезонам.
     */
    private function pickMainSeason(array $stats): ?PlayerSeasonStat
    {
        if ($stats === []) {
            return null;
        }

        usort($stats, static function (PlayerSeasonStat $a, PlayerSeasonStat $b): int {
            $yearA = (int) ($a->end_year ?? $a->start_year ?? 0);
            $yearB = (int) ($b->end_year ?? $b->start_year ?? 0);

            if ($yearA === $yearB) {
                return (int) ($b->minutes_played ?? 0) <=> (int) ($a->minutes_played ?? 0);
            }

            return $yearB <=> $yearA;
        });

        return $stats[0];
    }

    /**
     * Возвращает последние рейтинги игрока из локальной матчевой статистики.
     *
     * @param int $favoriteId идентификатор избранного игрока.
     * @param int $limit количество матчей для выборки.
     * @return float[]
     */
    private function getRecentRatingsFromDb(int $favoriteId, int $limit = 5): array
    {
        $matches = PlayerMatchStat::find()
            ->where(['favorite_id' => $favoriteId])
            ->orderBy(['played_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();

        $ratings = [];
        foreach ($matches as $match) {
            if (is_numeric($match->rating)) {
                $ratings[] = (float) $match->rating;
            }
        }

        return $ratings;
    }

    /**
     * Загружает и сохраняет последние матчи игрока.
     *
     * @param FavoritePlayer $favorite запись избранного игрока.
     * @param int $limit количество матчей для синхронизации.
     * @return int количество успешно сохраненных матчей.
     */
    private function syncMatchStats(FavoritePlayer $favorite, int $limit = self::MATCH_SYNC_LIMIT): int
    {
        $data = $this->sofascoreService->getPlayerLastMatches((int) $favorite->player_id, $limit);
        $events = $data['events'] ?? $data ?? [];
        $saved = 0;

        foreach ($events as $event) {
            $matchId = $event['id'] ?? $event['matchId'] ?? null;
            if (!$matchId) {
                continue;
            }

            try {
                $stats = $this->sofascoreService->getMatchPlayerStatistics((int) $matchId, (int) $favorite->player_id);
            } catch (\Throwable) {
                continue;
            }

            $saved += $this->saveMatchStat($favorite, $event, $stats, (int) $matchId);
        }

        return $saved;
    }

    /**
     * Сохраняет статистику одного матча игрока.
     *
     * @param FavoritePlayer $favorite запись избранного игрока.
     * @param array<string, mixed> $event объект события матча.
     * @param array<string, mixed> $stats объект статистики игрока в матче.
     * @param int $matchId идентификатор матча.
     * @return int 1 при успешном сохранении, иначе 0.
     */
    private function saveMatchStat(FavoritePlayer $favorite, array $event, array $stats, int $matchId): int
    {
        $team = $stats['team'] ?? [];
        $statistics = $stats['statistics'] ?? [];

        $tournament = $event['tournament'] ?? ($event['uniqueTournament'] ?? []);
        $season = $event['season'] ?? [];
        $playedAt = $event['startTimestamp'] ?? null;

        $homeTeam = $event['homeTeam'] ?? [];
        $awayTeam = $event['awayTeam'] ?? [];

        $opponent = null;
        if (!empty($team['id'])) {
            if (!empty($homeTeam['id']) && (int) $team['id'] === (int) $homeTeam['id']) {
                $opponent = $awayTeam;
            } elseif (!empty($awayTeam['id']) && (int) $team['id'] === (int) $awayTeam['id']) {
                $opponent = $homeTeam;
            }
        }

        $model = PlayerMatchStat::findOne([
            'favorite_id' => $favorite->id,
            'match_id' => $matchId,
        ]) ?? new PlayerMatchStat();

        $model->favorite_id = $favorite->id;
        $model->match_id = $matchId;
        $model->played_at = $playedAt ? (int) $playedAt : null;
        $model->tournament_id = $tournament['id'] ?? null;
        $model->tournament_name = $tournament['name'] ?? null;
        $model->season_id = $season['id'] ?? null;
        $model->season_name = $season['name'] ?? null;
        $model->team_id = $team['id'] ?? null;
        $model->team_name = $team['name'] ?? null;
        $model->opponent_id = $opponent['id'] ?? null;
        $model->opponent_name = $opponent['name'] ?? null;
        $model->minutes_played = $statistics['minutesPlayed'] ?? null;
        $model->rating = $statistics['rating'] ?? null;
        $model->goals = $statistics['goals'] ?? null;
        $model->assists = $statistics['assists'] ?? ($statistics['assist'] ?? null);
        $model->key_passes = $statistics['keyPass'] ?? null;
        $model->shots_on_target = $statistics['onTargetScoringAttempt'] ?? null;
        $model->total_shots = $statistics['totalShots'] ?? null;
        $model->accurate_passes = $statistics['accuratePass'] ?? null;
        $model->total_passes = $statistics['totalPass'] ?? null;
        $model->aerial_won = $statistics['aerialWon'] ?? null;
        $model->aerial_lost = $statistics['aerialLost'] ?? null;
        $model->duel_won = $statistics['duelWon'] ?? null;
        $model->duel_lost = $statistics['duelLost'] ?? null;
        $model->fouls = $statistics['fouls'] ?? null;
        $model->was_fouled = $statistics['wasFouled'] ?? null;
        $model->possession_lost = $statistics['possessionLostCtrl'] ?? null;
        $model->dispossessed = $statistics['dispossessed'] ?? null;
        $model->touches = $statistics['touches'] ?? null;
        $model->raw_json = $this->encodeJson(['event' => $event, 'stats' => $stats]);

        return $model->save() ? 1 : 0;
    }

    /**
     * Ищет запись избранного игрока у текущего пользователя.
     *
     * @param int $playerId идентификатор игрока.
     */
    private function findFavoriteForCurrentUser(int $playerId): ?FavoritePlayer
    {
        return FavoritePlayer::findOne([
            'user_id' => $this->getCurrentUserId(),
            'player_id' => $playerId,
        ]);
    }

    /**
     * Возвращает id текущего авторизованного пользователя.
     */
    private function getCurrentUserId(): int
    {
        return (int) Yii::$app->user->id;
    }

    /**
     * Преобразует данные API в поля модели избранного игрока.
     *
     * @param FavoritePlayer $favorite модель избранного игрока.
     * @param int $userId идентификатор пользователя.
     * @param int $playerId идентификатор игрока.
     * @param array<string, mixed> $detail данные игрока из API.
     */
    private function hydrateFavoriteFromApi(FavoritePlayer $favorite, int $userId, int $playerId, array $detail): void
    {
        $player = $detail['player'] ?? $detail;
        $team = $player['team'] ?? [];
        $country = $player['country'] ?? null;

        $favorite->user_id = $userId;
        $favorite->player_id = $playerId;
        $favorite->name = $player['name'] ?? ($player['shortName'] ?? 'Игрок');
        $favorite->position = $player['position'] ?? null;
        $favorite->team_id = $team['id'] ?? null;
        $favorite->team_name = $team['name'] ?? ($team['shortName'] ?? null);
        $favorite->country = is_array($country) ? ($country['name'] ?? null) : $country;
        $favorite->image_url = $this->resolvePlayerImageUrl($playerId);
    }

    /**
     * Загружает и нормализует URL изображения игрока.
     *
     * @param int $playerId идентификатор игрока.
     */
    private function resolvePlayerImageUrl(int $playerId): ?string
    {
        try {
            $imgResp = $this->sofascoreService->getPlayerImage($playerId);
            $rawUrl = $imgResp['image'] ?? ($imgResp['url'] ?? null);

            return $this->normalizeImageUrl(is_string($rawUrl) ? $rawUrl : null);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Дополняет относительный URL до абсолютного API-адреса.
     *
     * @param string|null $imageUrl исходный URL.
     */
    private function normalizeImageUrl(?string $imageUrl): ?string
    {
        if ($imageUrl === null || $imageUrl === '') {
            return null;
        }

        if (strpos($imageUrl, 'http') === 0) {
            return $imageUrl;
        }

        return 'https://api.sofascore.com/api/v1' . $imageUrl;
    }

    /**
     * Считает количество сохраненных матчей по каждому избранному игроку.
     *
     * @param FavoritePlayer[] $favorites список избранных игроков.
     * @return array<int, int> ключ — favorite_id, значение — число матчей.
     */
    private function loadMatchCounts(array $favorites): array
    {
        if ($favorites === []) {
            return [];
        }

        $favoriteIds = array_map(static function (FavoritePlayer $favorite): int {
            return (int) $favorite->id;
        }, $favorites);

        $rows = PlayerMatchStat::find()
            ->select([
                'favorite_id',
                'match_count' => new Expression('COUNT(*)'),
            ])
            ->where(['favorite_id' => $favoriteIds])
            ->groupBy('favorite_id')
            ->asArray()
            ->all();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['favorite_id']] = (int) $row['match_count'];
        }

        return $counts;
    }

    /**
     * Возвращает первое сообщение об ошибке валидации модели.
     *
     * @param \yii\base\Model $model модель с ошибками.
     */
    private function extractFirstModelError(\yii\base\Model $model): string
    {
        foreach ($model->getFirstErrors() as $error) {
            return (string) $error;
        }

        return 'Не удалось сохранить игрока.';
    }

    /**
     * Кодирует массив в JSON без экранирования unicode и URL.
     *
     * @param array<string, mixed> $payload данные для кодирования.
     */
    private function encodeJson(array $payload): ?string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : null;
    }
}
