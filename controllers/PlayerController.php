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
use yii\helpers\Url;
use yii\httpclient\Client;
use yii\httpclient\CurlTransport;
use yii\web\Controller;
use yii\web\Response;

/**
 * Управляет поиском игроков, профилем и списком избранных.
 */
class PlayerController extends Controller
{
    private const DEFAULT_SEARCH_LIMIT = 20;
    private const MAX_SEARCH_LIMIT = 100;
    private const MATCH_SYNC_LIMIT = 5;
    private const MATCH_FORCE_REFRESH_COUNT = 2;
    private const PROFILE_CACHE_TTL = 300;
    private const PROFILE_DETAIL_CACHE_TTL = 900;
    private const PROFILE_DB_FALLBACK_MATCH_LIMIT = 10;
    private const IMAGE_CACHE_TTL = 86400;

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
                        'actions' => ['image'],
                        'roles' => ['?', '@'],
                    ],
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
            $profile['detail'] = $this->getCachedPlayerDetails($playerId);
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
        $cacheKey = $this->buildProfileCacheKey($playerId);
        $cached = Yii::$app->cache->get($cacheKey);

        if (is_array($cached)) {
            return ['success' => true, 'data' => $cached];
        }

        try {
            $profile = $this->sofascoreService->getPlayerProfile($playerId);
            $favorite = $this->findFavoriteForCurrentUser($playerId);
            if ($favorite !== null) {
                $profile = $this->mergeProfileWithStoredStats($profile, $favorite);
                $profile = array_merge($profile, $this->buildFavoriteAnalyticsPayload($favorite));
            }

            Yii::$app->cache->set($cacheKey, $profile, self::PROFILE_CACHE_TTL);

            return ['success' => true, 'data' => $profile];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Проксирует фотографию игрока через сервер приложения.
     *
     * @param int|string $id идентификатор игрока.
     */
    public function actionImage($id): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        $playerId = (int) $id;

        if ($playerId <= 0) {
            Yii::$app->response->statusCode = 404;
            return '';
        }

        $cacheKey = 'player:image:blob:' . $playerId;
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached) && isset($cached['body'], $cached['type'])) {
            Yii::$app->response->headers->set('Content-Type', (string) $cached['type']);
            Yii::$app->response->headers->set('Cache-Control', 'public, max-age=' . self::IMAGE_CACHE_TTL);
            return (string) $cached['body'];
        }

        $image = $this->fetchPlayerImageBinary($playerId);
        if ($image === null) {
            Yii::$app->response->statusCode = 404;
            return '';
        }

        Yii::$app->cache->set($cacheKey, $image, self::IMAGE_CACHE_TTL);

        Yii::$app->response->headers->set('Content-Type', (string) $image['type']);
        Yii::$app->response->headers->set('Cache-Control', 'public, max-age=' . self::IMAGE_CACHE_TTL);

        return (string) $image['body'];
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
                $analytics = PlayerAnalytics::efficiencyIndex($mainSeason, $recentRatings);
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
            $detail = $this->getCachedPlayerDetails($playerId);
            $favorite = $this->findFavoriteForCurrentUser($playerId) ?? new FavoritePlayer();
            $this->hydrateFavoriteFromApi($favorite, $userId, $playerId, $detail);

            if ($favorite->save()) {
                $this->invalidateProfileCache($playerId);
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
            $this->invalidateProfileCache($playerId);
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
            $seasons = is_array($allStats['seasons'] ?? null) ? $allStats['seasons'] : [];
            $savedSeasons = 0;
            $unchangedSeasons = 0;
            $seasonMap = $this->loadSeasonStatsMap((int) $favorite->id);

            foreach ($seasons as $season) {
                $saved = $this->saveSeasonStats($favorite, $season, $seasonMap);
                if ($saved === 1) {
                    $savedSeasons++;
                } else {
                    $unchangedSeasons++;
                }
            }

            $matchSync = $this->syncMatchStats($favorite, self::MATCH_SYNC_LIMIT);
            $this->invalidateProfileCache($playerId);
            Yii::$app->session->setFlash(
                'success',
                "Статистика обновлена. Сезонов обновлено: {$savedSeasons}, без изменений: {$unchangedSeasons}, матчей: {$matchSync['saved']}, пропущено матчей: {$matchSync['skipped']}"
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
     * @param array<string, PlayerSeasonStat> $seasonMap карта существующих сезонов.
     * @return int 1 при успешном сохранении, иначе 0.
     */
    private function saveSeasonStats(FavoritePlayer $favorite, array $season, array &$seasonMap): int
    {
        $stats = $season['statistics'] ?? [];
        $seasonInfo = $season['season'] ?? [];
        $tournament = $season['uniqueTournament'] ?? [];
        $team = $season['team'] ?? [];

        $seasonId = $seasonInfo['id'] ?? null;
        if (!$seasonId) {
            return 0;
        }

        $tournamentId = isset($tournament['id']) ? (int) $tournament['id'] : null;
        $teamId = isset($team['id']) ? (int) $team['id'] : null;
        $mapKey = $this->buildSeasonMapKey((int) $seasonId, $tournamentId, $teamId);
        $model = $seasonMap[$mapKey] ?? new PlayerSeasonStat();
        $isNewRecord = $model->isNewRecord;
        $rawJson = $this->encodeJson($season);

        if (!$isNewRecord && $model->raw_json === $rawJson) {
            return 0;
        }

        $model->favorite_id = $favorite->id;
        $model->season_id = (int) $seasonId;
        $model->season_name = $seasonInfo['name'] ?? null;
        $model->season_year = $seasonInfo['year'] ?? ($season['year'] ?? null);
        $model->start_year = $season['startYear'] ?? null;
        $model->end_year = $season['endYear'] ?? null;
        $model->tournament_id = $tournamentId;
        $model->tournament_name = $tournament['name'] ?? null;
        $model->team_id = $teamId;
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
        $model->raw_json = $rawJson;

        if (!$model->save(false)) {
            return 0;
        }

        if ($isNewRecord) {
            $seasonMap[$mapKey] = $model;
        }

        return 1;
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
     * Собирает локально рассчитанную аналитику игрока для JSON-профиля.
     *
     * @return array<string, mixed>
     */
    private function buildFavoriteAnalyticsPayload(FavoritePlayer $favorite): array
    {
        $mainSeason = $this->pickMainSeason($favorite->seasonStats);
        if ($mainSeason === null) {
            return [];
        }

        $recentRatings = $this->getRecentRatingsFromDb((int) $favorite->id, 5);

        return [
            'analytics' => PlayerAnalytics::efficiencyIndex($mainSeason, $recentRatings),
            'forecast' => PlayerAnalytics::predictNextMatch($mainSeason, $recentRatings),
        ];
    }

    /**
     * Загружает и сохраняет последние матчи игрока.
     *
     * @param FavoritePlayer $favorite запись избранного игрока.
     * @param int $limit количество матчей для синхронизации.
     * @return array{saved:int,skipped:int} статистика синхронизации матчей.
     */
    private function syncMatchStats(FavoritePlayer $favorite, int $limit = self::MATCH_SYNC_LIMIT): array
    {
        $data = $this->sofascoreService->getPlayerLastMatches((int) $favorite->player_id, $limit);
        $events = $data['events'] ?? $data ?? [];
        $saved = 0;
        $skipped = 0;

        $matchIds = [];
        foreach ($events as $event) {
            $matchId = $event['id'] ?? $event['matchId'] ?? null;
            if ($matchId) {
                $matchIds[] = (int) $matchId;
            }
        }

        $existingMap = $this->loadMatchStatsMap((int) $favorite->id, $matchIds);

        foreach ($events as $index => $event) {
            $matchId = $event['id'] ?? $event['matchId'] ?? null;
            if (!$matchId) {
                continue;
            }
            $matchId = (int) $matchId;

            $mustRefresh = $index < self::MATCH_FORCE_REFRESH_COUNT;
            if (!$mustRefresh && isset($existingMap[$matchId])) {
                $skipped++;
                continue;
            }

            try {
                $stats = $this->sofascoreService->getMatchPlayerStatistics($matchId, (int) $favorite->player_id);
            } catch (\Throwable) {
                continue;
            }

            $saved += $this->saveMatchStat($favorite, $event, $stats, $matchId, $existingMap);
        }

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    /**
     * Сохраняет статистику одного матча игрока.
     *
     * @param FavoritePlayer $favorite запись избранного игрока.
     * @param array<string, mixed> $event объект события матча.
     * @param array<string, mixed> $stats объект статистики игрока в матче.
     * @param int $matchId идентификатор матча.
     * @param array<int, PlayerMatchStat> $matchMap карта существующих матчей.
     * @return int 1 при успешном сохранении, иначе 0.
     */
    private function saveMatchStat(
        FavoritePlayer $favorite,
        array $event,
        array $stats,
        int $matchId,
        array &$matchMap
    ): int {
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

        $model = $matchMap[$matchId] ?? new PlayerMatchStat();
        $isNewRecord = $model->isNewRecord;
        $rawJson = $this->encodeJson(['event' => $event, 'stats' => $stats]);

        if (!$isNewRecord && $model->raw_json === $rawJson) {
            return 0;
        }

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
        $model->raw_json = $rawJson;

        if (!$model->save(false)) {
            return 0;
        }

        if ($isNewRecord) {
            $matchMap[$matchId] = $model;
        }

        return 1;
    }

    /**
     * Загружает существующие сезонные записи в карту для быстрого доступа.
     *
     * @param int $favoriteId идентификатор записи избранного игрока.
     * @return array<string, PlayerSeasonStat>
     */
    private function loadSeasonStatsMap(int $favoriteId): array
    {
        $models = PlayerSeasonStat::find()
            ->where(['favorite_id' => $favoriteId])
            ->all();

        $map = [];
        foreach ($models as $model) {
            $key = $this->buildSeasonMapKey(
                (int) $model->season_id,
                $model->tournament_id !== null ? (int) $model->tournament_id : null,
                $model->team_id !== null ? (int) $model->team_id : null
            );
            $map[$key] = $model;
        }

        return $map;
    }

    /**
     * Формирует ключ для карты сезонной статистики.
     *
     * @param int $seasonId идентификатор сезона.
     * @param int|null $tournamentId идентификатор турнира.
     * @param int|null $teamId идентификатор команды.
     */
    private function buildSeasonMapKey(int $seasonId, ?int $tournamentId, ?int $teamId): string
    {
        return $seasonId . ':' . ($tournamentId ?? 'null') . ':' . ($teamId ?? 'null');
    }

    /**
     * Загружает существующие матчевые записи в карту по match_id.
     *
     * @param int $favoriteId идентификатор записи избранного игрока.
     * @param int[] $matchIds список матчей из последнего API-ответа.
     * @return array<int, PlayerMatchStat>
     */
    private function loadMatchStatsMap(int $favoriteId, array $matchIds): array
    {
        if ($matchIds === []) {
            return [];
        }

        return PlayerMatchStat::find()
            ->where([
                'favorite_id' => $favoriteId,
                'match_id' => $matchIds,
            ])
            ->indexBy('match_id')
            ->all();
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
     * Загружает детальные данные игрока с кэшированием.
     *
     * @param int $playerId идентификатор игрока.
     * @return array<string, mixed>
     */
    private function getCachedPlayerDetails(int $playerId): array
    {
        $cacheKey = 'player:detail:' . $playerId;
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $detail = $this->sofascoreService->getPlayerDetails($playerId);
        Yii::$app->cache->set($cacheKey, $detail, self::PROFILE_DETAIL_CACHE_TTL);

        return $detail;
    }

    /**
     * Формирует ключ кэша профиля с учетом текущего пользователя.
     *
     * @param int $playerId идентификатор игрока.
     */
    private function buildProfileCacheKey(int $playerId): string
    {
        return 'player:profile:' . $this->getCurrentUserId() . ':' . $playerId;
    }

    /**
     * Очищает кэш профиля игрока.
     *
     * @param int $playerId идентификатор игрока.
     */
    private function invalidateProfileCache(int $playerId): void
    {
        Yii::$app->cache->delete($this->buildProfileCacheKey($playerId));
    }

    /**
     * Подмешивает локальную БД-статистику, если удаленный API вернул пустые блоки.
     *
     * @param array<string, mixed> $profile исходный профиль из API.
     * @param FavoritePlayer $favorite запись избранного игрока.
     * @return array<string, mixed>
     */
    private function mergeProfileWithStoredStats(array $profile, FavoritePlayer $favorite): array
    {
        $favoriteId = (int) $favorite->id;

        $allStatistics = $profile['allStatistics'] ?? [];
        if (!is_array($allStatistics)) {
            $allStatistics = [];
        }
        $seasons = $allStatistics['seasons'] ?? [];
        if (!is_array($seasons) || $seasons === []) {
            $seasonFallback = $this->loadSeasonRowsAsApiFallback($favoriteId);
            if ($seasonFallback !== []) {
                $allStatistics['seasons'] = $seasonFallback;
                $profile['allStatistics'] = $allStatistics;
                $profile['statisticsSeasons'] = ['statisticsSeasons' => $seasonFallback];
            }
        }

        $ratings = $profile['ratings']['ratings'] ?? ($profile['ratings'] ?? []);
        if (!is_array($ratings) || $ratings === []) {
            $ratingFallback = $this->loadRatingsFallback($favoriteId);
            if ($ratingFallback !== []) {
                $profile['ratings'] = $ratingFallback;
            }
        }

        $characteristics = $profile['characteristics']['characteristics'] ?? ($profile['characteristics'] ?? []);
        if (!is_array($characteristics) || $characteristics === []) {
            $characteristicsFallback = $this->loadCharacteristicsFallback($favorite, $favoriteId);
            if ($characteristicsFallback !== []) {
                $profile['characteristics'] = $characteristicsFallback;
            }
        }

        $matches = $profile['lastMatches']['events'] ?? ($profile['lastMatches'] ?? []);
        if (!is_array($matches) || $matches === []) {
            $matchesFallback = $this->loadLastMatchesFallback($favoriteId);
            if ($matchesFallback !== []) {
                $profile['lastMatches'] = ['events' => $matchesFallback];
            }
        }

        return $profile;
    }

    /**
     * Формирует fallback сезона в формате, близком к API.
     *
     * @param int $favoriteId идентификатор записи избранного.
     * @return array<int, array<string, mixed>>
     */
    private function loadSeasonRowsAsApiFallback(int $favoriteId): array
    {
        $rows = PlayerSeasonStat::find()
            ->where(['favorite_id' => $favoriteId])
            ->orderBy(['end_year' => SORT_DESC, 'start_year' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(12)
            ->asArray()
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'season' => [
                    'id' => $row['season_id'] !== null ? (int) $row['season_id'] : null,
                    'name' => $row['season_name'] ?? null,
                    'year' => $row['season_year'] ?? null,
                ],
                'uniqueTournament' => [
                    'id' => $row['tournament_id'] !== null ? (int) $row['tournament_id'] : null,
                    'name' => $row['tournament_name'] ?? null,
                ],
                'team' => [
                    'id' => $row['team_id'] !== null ? (int) $row['team_id'] : null,
                    'name' => $row['team_name'] ?? null,
                ],
                'year' => $row['season_year'] ?? null,
                'startYear' => $row['start_year'] !== null ? (int) $row['start_year'] : null,
                'endYear' => $row['end_year'] !== null ? (int) $row['end_year'] : null,
                'statistics' => [
                    'appearances' => $row['appearances'] !== null ? (int) $row['appearances'] : null,
                    'minutesPlayed' => $row['minutes_played'] !== null ? (int) $row['minutes_played'] : null,
                    'goals' => $row['goals'] !== null ? (int) $row['goals'] : null,
                    'assists' => $row['assists'] !== null ? (int) $row['assists'] : null,
                    'expectedGoals' => $row['expected_goals'] !== null ? (float) $row['expected_goals'] : null,
                    'expectedAssists' => $row['expected_assists'] !== null ? (float) $row['expected_assists'] : null,
                    'rating' => $row['rating'] !== null ? (float) $row['rating'] : null,
                ],
            ];
        }

        return $result;
    }

    /**
     * Формирует fallback списка рейтингов из локальных сезонных данных.
     *
     * @param int $favoriteId идентификатор записи избранного.
     * @return array<int, array{name:string,value:float}>
     */
    private function loadRatingsFallback(int $favoriteId): array
    {
        $rows = PlayerSeasonStat::find()
            ->select(['tournament_name', 'season_name', 'season_year', 'rating'])
            ->where(['favorite_id' => $favoriteId])
            ->andWhere(['not', ['rating' => null]])
            ->orderBy(['end_year' => SORT_DESC, 'start_year' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(8)
            ->asArray()
            ->all();

        $ratings = [];
        foreach ($rows as $row) {
            $label = trim(
                (string) ($row['tournament_name'] ?? 'Сезон')
                . ' '
                . (string) ($row['season_year'] ?? $row['season_name'] ?? '')
            );

            $ratings[] = [
                'name' => $label !== '' ? $label : 'Сезон',
                'value' => (float) $row['rating'],
            ];
        }

        return $ratings;
    }

    /**
     * Формирует fallback блока характеристик из локальных данных.
     *
     * @param FavoritePlayer $favorite запись избранного.
     * @param int $favoriteId идентификатор записи избранного.
     * @return array<int, array{name:string,value:string|int|float}>
     */
    private function loadCharacteristicsFallback(FavoritePlayer $favorite, int $favoriteId): array
    {
        $mainSeason = PlayerSeasonStat::find()
            ->where(['favorite_id' => $favoriteId])
            ->orderBy(['end_year' => SORT_DESC, 'start_year' => SORT_DESC, 'minutes_played' => SORT_DESC])
            ->asArray()
            ->one();

        $rows = [];
        if ($favorite->position) {
            $rows[] = ['name' => 'Позиция', 'value' => (string) $favorite->position];
        }
        if ($favorite->team_name) {
            $rows[] = ['name' => 'Команда', 'value' => (string) $favorite->team_name];
        }
        if ($favorite->country) {
            $rows[] = ['name' => 'Страна', 'value' => (string) $favorite->country];
        }

        if (is_array($mainSeason)) {
            if ($mainSeason['appearances'] !== null) {
                $rows[] = ['name' => 'Матчи', 'value' => (int) $mainSeason['appearances']];
            }
            if ($mainSeason['minutes_played'] !== null) {
                $rows[] = ['name' => 'Минуты', 'value' => (int) $mainSeason['minutes_played']];
            }
            if ($mainSeason['goals'] !== null) {
                $rows[] = ['name' => 'Голы', 'value' => (int) $mainSeason['goals']];
            }
            if ($mainSeason['assists'] !== null) {
                $rows[] = ['name' => 'Ассисты', 'value' => (int) $mainSeason['assists']];
            }
        }

        return $rows;
    }

    /**
     * Формирует fallback последних матчей из локальной БД.
     *
     * @param int $favoriteId идентификатор записи избранного.
     * @return array<int, array<string, mixed>>
     */
    private function loadLastMatchesFallback(int $favoriteId): array
    {
        $rows = PlayerMatchStat::find()
            ->where(['favorite_id' => $favoriteId])
            ->orderBy(['played_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(self::PROFILE_DB_FALLBACK_MATCH_LIMIT)
            ->asArray()
            ->all();

        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'startTimestamp' => $row['played_at'] !== null ? (int) $row['played_at'] : null,
                'opponent' => $row['opponent_name'] ?? '',
                'rating' => $row['rating'] !== null ? (float) $row['rating'] : null,
                'homeScore' => ['current' => null],
                'awayScore' => ['current' => null],
            ];
        }

        return $events;
    }

    /**
     * Возвращает прямую ссылку на публичную картинку игрока.
     *
     * @param int $playerId идентификатор игрока.
     */
    private function resolvePlayerImageUrl(int $playerId): ?string
    {
        if ($playerId <= 0) {
            return null;
        }

        return Url::to(['player/image', 'id' => $playerId]);
    }

    /**
     * Пытается получить бинарные данные фото из нескольких источников.
     *
     * @param int $playerId идентификатор игрока.
     * @return array{body:string,type:string}|null
     */
    private function fetchPlayerImageBinary(int $playerId): ?array
    {
        $urls = [
            "https://api.sofascore.com/api/v1/player/{$playerId}/image",
            "https://api.sofascore.app/api/v1/player/{$playerId}/image",
        ];

        try {
            $imagePayload = $this->sofascoreService->getPlayerImage($playerId);
            $urls = array_merge($urls, $this->extractImageUrlsFromPayload($imagePayload));
        } catch (\Throwable $e) {
            Yii::warning('Unable to fetch image metadata: ' . $e->getMessage(), __METHOD__);
        }

        $normalizedUrls = [];
        foreach ($urls as $url) {
            $normalized = $this->normalizeImageUrl((string) $url);
            if ($normalized !== null) {
                $normalizedUrls[] = $normalized;
            }
        }
        $normalizedUrls = array_values(array_unique($normalizedUrls));

        foreach ($normalizedUrls as $url) {
            $image = $this->downloadImageFromUrl($url);
            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    /**
     * Достает потенциальные URL изображений из произвольного payload API.
     *
     * @param array<string, mixed> $payload ответ API.
     * @return array<int, string>
     */
    private function extractImageUrlsFromPayload(array $payload): array
    {
        $urls = [];
        $stack = [$payload];

        while ($stack !== []) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }

            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                    continue;
                }

                if (!is_string($value) || trim($value) === '') {
                    continue;
                }

                $keyName = is_string($key) ? strtolower($key) : '';
                if (str_contains($keyName, 'image') || str_contains($keyName, 'url')) {
                    $urls[] = $value;
                }
            }
        }

        return $urls;
    }

    /**
     * Нормализует URL изображения до абсолютного HTTPS-адреса.
     */
    private function normalizeImageUrl(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '//')) {
            return 'https:' . $trimmed;
        }

        if (str_starts_with($trimmed, '/')) {
            return 'https://api.sofascore.com' . $trimmed;
        }

        if (preg_match('#^https?://#i', $trimmed)) {
            return $trimmed;
        }

        return null;
    }

    /**
     * Загружает бинарное изображение по URL.
     *
     * @param string $url абсолютный URL изображения.
     * @return array{body:string,type:string}|null
     */
    private function downloadImageFromUrl(string $url): ?array
    {
        try {
            $client = new Client([
                'transport' => CurlTransport::class,
            ]);

            $response = $client
                ->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->addHeaders([
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer' => 'https://www.sofascore.com/',
                    'User-Agent' => 'Mozilla/5.0',
                ])
                ->setOptions([
                    'connectTimeout' => 3,
                    'timeout' => 8,
                ])
                ->send();
        } catch (\Throwable $e) {
            Yii::warning('Unable to download player image from ' . $url . ': ' . $e->getMessage(), __METHOD__);
            return null;
        }

        $body = $response->getContent();
        if (!$response->isOk || !is_string($body) || $body === '') {
            return null;
        }

        $contentType = strtolower((string) ($response->headers->get('content-type') ?? ''));
        if ($contentType === '' || str_contains($contentType, 'octet-stream')) {
            $contentType = 'image/jpeg';
        }

        if (!str_starts_with($contentType, 'image/')) {
            return null;
        }

        return ['body' => $body, 'type' => $contentType];
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
