<?php

namespace app\components\services;

use app\components\DTO\PlayerSofascoreDTO;
use app\components\repository\APISofascoreRepository;
use Yii;

/**
 * Сервисный слой для агрегации и нормализации данных Sofascore API.
 */
class APISofascoreServices
{
    /**
     * Максимум матчей в профиле игрока.
     */
    private const PROFILE_LAST_MATCHES_LIMIT = 10;

    /**
     * Репозиторий HTTP-запросов к API.
     */
    private APISofascoreRepository $repository;

    /**
     * @param APISofascoreRepository|null $repository подмена репозитория для тестов.
     */
    public function __construct(?APISofascoreRepository $repository = null)
    {
        $this->repository = $repository ?? new APISofascoreRepository();
    }

    /**
     * Возвращает детальные данные игрока по идентификатору.
     *
     * @param int $playerId идентификатор игрока.
     * @return array<string, mixed>
     */
    public function getPlayerDetails(int $playerId): array
    {
        return $this->repository->get('players/detail', ['playerId' => $playerId]);
    }

    /**
     * Возвращает информацию об изображении игрока.
     *
     * @param int $playerId идентификатор игрока.
     * @return array<string, mixed>
     */
    public function getPlayerImage(int $playerId): array
    {
        return $this->repository->get('players/get-image', ['playerId' => $playerId]);
    }

    /**
     * Возвращает характеристики игрока.
     *
     * @param int $playerId идентификатор игрока.
     * @return array<string, mixed>
     */
    public function getPlayerCharacteristics(int $playerId): array
    {
        return $this->repository->get('players/get-characteristics', ['playerId' => $playerId]);
    }

    /**
     * Возвращает рейтинги игрока.
     *
     * @param int $playerId идентификатор игрока.
     * @param int|null $tournamentId идентификатор турнира.
     * @param int|null $seasonId идентификатор сезона.
     * @return array<string, mixed>
     */
    public function getPlayerRatings(int $playerId, ?int $tournamentId = null, ?int $seasonId = null): array
    {
        if ($tournamentId === null || $seasonId === null) {
            return [];
        }

        return $this->repository->get('players/get-ratings', [
            'playerId' => $playerId,
            'tournamentId' => $tournamentId,
            'seasonId' => $seasonId,
        ]);
    }

    /**
     * Возвращает всю агрегированную статистику игрока.
     *
     * @param int $playerId идентификатор игрока.
     * @return array<string, mixed>
     */
    public function getPlayerAllStatistics(int $playerId): array
    {
        return $this->repository->get('players/get-all-statistics', ['playerId' => $playerId]);
    }

    /**
     * Возвращает статистику игрока по турнирам.
     *
     * @param int $playerId идентификатор игрока.
     * @return array<string, mixed>
     */
    public function getPlayerStatistics(int $playerId): array
    {
        return $this->repository->get('players/get-statistics', ['playerId' => $playerId]);
    }

    /**
     * Возвращает статистику игрока по сезонам.
     *
     * @param int $playerId идентификатор игрока.
     * @return array<string, mixed>
     */
    public function getPlayerStatisticsSeasons(int $playerId): array
    {
        return $this->repository->get('players/get-statistics-seasons', ['playerId' => $playerId]);
    }

    /**
     * Возвращает последние матчи игрока.
     *
     * @param int $playerId идентификатор игрока.
     * @param int $limit максимальное число матчей.
     * @return array<string, mixed>
     */
    public function getPlayerLastMatches(int $playerId, int $limit = 5): array
    {
        return $this->repository->get('players/get-last-matches', [
            'playerId' => $playerId,
            'n' => max(1, $limit),
        ]);
    }

    /**
     * Возвращает статистику игрока в конкретном матче.
     *
     * @param int $matchId идентификатор матча.
     * @param int $playerId идентификатор игрока.
     * @return array<string, mixed>
     */
    public function getMatchPlayerStatistics(int $matchId, int $playerId): array
    {
        return $this->repository->get('matches/get-player-statistics', [
            'matchId' => $matchId,
            'playerId' => $playerId,
        ]);
    }

    /**
     * Собирает профиль игрока из нескольких API-эндпоинтов.
     *
     * @param int $playerId идентификатор игрока.
     * @return array<string, mixed>
     */
    public function getPlayerProfile(int $playerId): array
    {
        $allStatistics = $this->safeFetch(function () use ($playerId): array {
            return $this->getPlayerAllStatistics($playerId);
        }, [], 'get-all-statistics');

        $profile = [
            'characteristics' => $this->safeFetch(function () use ($playerId): array {
                return $this->getPlayerCharacteristics($playerId);
            }, [], 'get-characteristics'),
            'statisticsSeasons' => [
                'statisticsSeasons' => $this->extractSeasons($allStatistics),
            ],
            'allStatistics' => $allStatistics,
            'lastMatches' => $this->safeFetch(function () use ($playerId): array {
                return $this->getPlayerLastMatches($playerId, self::PROFILE_LAST_MATCHES_LIMIT);
            }, [], 'get-last-matches'),
            'ratings' => [],
        ];

        list($tournamentId, $seasonId) = $this->extractRatingContext($allStatistics);
        if ($tournamentId !== null && $seasonId !== null) {
            $profile['ratings'] = $this->safeFetch(function () use ($playerId, $tournamentId, $seasonId): array {
                return $this->getPlayerRatings($playerId, $tournamentId, $seasonId);
            }, [], 'get-ratings');
        }

        if ($profile['ratings'] === []) {
            $profile['ratings'] = $this->buildRatingsFromAllStatistics($allStatistics);
        }

        return $profile;
    }

    /**
     * Выполняет поиск игроков и возвращает DTO-коллекцию.
     *
     * @param string $query поисковая строка.
     * @param int $limit максимальное число элементов.
     * @return PlayerSofascoreDTO[]
     */
    public function searchPlayers(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $data = $this->repository->get('search', [
            'q' => $query,
            'type' => 'player-team-persons',
            'page' => 0,
        ]);

        $results = $data['results'] ?? [];
        $limitedResults = array_slice($results, 0, max(1, $limit));

        return array_map(static function (array $item): PlayerSofascoreDTO {
            return PlayerSofascoreDTO::fromApi($item);
        }, $limitedResults);
    }

    /**
     * Ищет игроков и применяет локальные фильтры (страна, позиция, команда).
     *
     * @param string $query поисковая строка.
     * @param string|null $country фильтр по стране.
     * @param string|null $position фильтр по позиции.
     * @param int|null $teamId фильтр по идентификатору команды.
     * @param int $limit максимум игроков в поисковой выдаче.
     * @return array<string, array>
     */
    public function getPlayersWithFilters(
        string $query,
        ?string $country = null,
        ?string $position = null,
        ?int $teamId = null,
        int $limit = 20
    ): array {
        $query = trim($query);
        $country = $country !== null ? trim($country) : '';
        $position = $position !== null ? trim($position) : '';
        $teamId = $teamId ?: 0;

        if ($query === '') {
            return ['players' => [], 'positions' => [], 'countries' => []];
        }

        $players = $this->searchPlayers($query, $limit);

        $filtered = array_values(array_filter(
            $players,
            static function (PlayerSofascoreDTO $player) use ($country, $position, $teamId): bool {
                return $player->matchesFilters($country, $position, $teamId);
            }
        ));

        $positions = [];
        $countries = [];
        foreach ($players as $player) {
            if (!empty($player->position)) {
                $positions[] = $player->position;
            }
            if (!empty($player->country)) {
                $countries[] = $player->country;
            }
        }

        $positions = array_values(array_unique($positions));
        sort($positions);

        $countries = array_values(array_unique($countries));
        sort($countries);

        return [
            'players' => $filtered,
            'positions' => $positions,
            'countries' => $countries,
        ];
    }

    /**
     * Безопасно выполняет вызов API и возвращает fallback при исключении.
     *
     * @param callable $callback функция, возвращающая массив данных.
     * @param array<string, mixed> $fallback данные по умолчанию.
     * @return array<string, mixed>
     */
    private function safeFetch(callable $callback, array $fallback = [], string $context = ''): array
    {
        try {
            $result = $callback();

            return is_array($result) ? $result : $fallback;
        } catch (\Throwable $e) {
            Yii::warning(
                'Sofascore request failed'
                . ($context !== '' ? " ({$context})" : '')
                . ': ' . $e->getMessage(),
                __METHOD__
            );

            return $fallback;
        }
    }

    /**
     * Извлекает идентификаторы турнира и сезона для запроса рейтингов.
     *
     * @param array<string, mixed> $allStatistics полный блок статистики.
     * @return array{0:int|null,1:int|null}
     */
    private function extractRatingContext(array $allStatistics): array
    {
        $seasons = $this->extractSeasons($allStatistics);
        foreach ($seasons as $season) {
            $tournamentId = isset($season['uniqueTournament']['id'])
                ? (int) $season['uniqueTournament']['id']
                : null;
            $seasonId = isset($season['season']['id'])
                ? (int) $season['season']['id']
                : null;

            if ($tournamentId !== null && $seasonId !== null) {
                return [$tournamentId, $seasonId];
            }
        }

        return [null, null];
    }

    /**
     * Возвращает список сезонов из блока allStatistics в единообразной форме.
     *
     * @param array<string, mixed> $allStatistics полный блок статистики.
     * @return array<int, array<string, mixed>>
     */
    private function extractSeasons(array $allStatistics): array
    {
        $seasons = $allStatistics['seasons'] ?? [];
        if (!is_array($seasons)) {
            return [];
        }

        return array_values(array_filter($seasons, static function ($row): bool {
            return is_array($row);
        }));
    }

    /**
     * Строит fallback-рейтинги по сезонам, если отдельный endpoint рейтингов пуст.
     *
     * @param array<string, mixed> $allStatistics полный блок статистики.
     * @return array<int, array{name:string,value:float}>
     */
    private function buildRatingsFromAllStatistics(array $allStatistics): array
    {
        $ratings = [];
        foreach ($this->extractSeasons($allStatistics) as $season) {
            $rating = $season['statistics']['rating'] ?? null;
            if (!is_numeric($rating)) {
                continue;
            }

            $tournamentName = (string) ($season['uniqueTournament']['name'] ?? 'Сезон');
            $seasonName = (string) (
                $season['season']['year']
                ?? $season['season']['name']
                ?? $season['year']
                ?? ''
            );

            $label = trim($tournamentName . ' ' . $seasonName);
            $ratings[] = [
                'name' => $label !== '' ? $label : 'Сезон',
                'value' => (float) $rating,
            ];

            if (count($ratings) >= 8) {
                break;
            }
        }

        return $ratings;
    }

}
