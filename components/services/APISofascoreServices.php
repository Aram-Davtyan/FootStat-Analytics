<?php

namespace app\components\services;

use app\components\repository\APISofascoreRepository;
use app\components\DTO\PlayerSofascoreDTO;

class APISofascoreServices
{
    private APISofascoreRepository $repository;

    public function __construct(?APISofascoreRepository $repository = null)
    {
        $this->repository = $repository ?: new APISofascoreRepository();
    }

    /**
     * Метод для получения деталей игрока по его ID.
     */
    public function getPlayerDetails($playerId): array
    {
        return $this->repository->get('players/detail', ['playerId' => $playerId]);
    }

    public function getPlayerImage($playerId): array
    {
        return $this->repository->get('players/get-image', ['playerId' => $playerId]);
    }

    public function getPlayerCharacteristics($playerId): array
    {
        return $this->repository->get('players/get-characteristics', ['playerId' => $playerId]);
    }

    /**
     * Рейтинги игрока. Для корректного ответа нужны tournamentId и seasonId.
     * Если не переданы, возвращаем пустой массив, чтобы не бить 400 от API.
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

    public function getPlayerAllStatistics($playerId): array
    {
        return $this->repository->get('players/get-all-statistics', ['playerId' => $playerId]);
    }

    public function getPlayerStatistics($playerId): array
    {
        return $this->repository->get('players/get-statistics', ['playerId' => $playerId]);
    }

    public function getPlayerStatisticsSeasons($playerId): array
    {
        return $this->repository->get('players/get-statistics-seasons', ['playerId' => $playerId]);
    }

    public function getPlayerTransferHistory($playerId): array
    {
        return $this->repository->get('players/get-transfer-history', ['playerId' => $playerId]);
    }

    public function getPlayerLastMatches($playerId, int $limit = 5): array
    {
        return $this->repository->get('players/get-last-matches', ['playerId' => $playerId, 'n' => $limit]);
    }

    /**
     * Собирает профиль игрока из нескольких эндпоинтов Sofascore.
     */
    public function getPlayerProfile(int $playerId): array
    {
        $profile = [
            'detail' => [],
            'image' => null,
            'characteristics' => [],
            'ratings' => [],
            'statisticsSeasons' => [],
            'allStatistics' => [],
            'statistics' => [],
            'transferHistory' => [],
            'lastMatches' => [],
            'imageUrl' => null,
        ];

        $profile['detail'] = $this->getPlayerDetails($playerId);

        try {
            $profile['image'] = $this->getPlayerImage($playerId);
        } catch (\Throwable $e) {
            $profile['image'] = null;
        }

        try {
            $profile['characteristics'] = $this->getPlayerCharacteristics($playerId);
        } catch (\Throwable $e) {
            $profile['characteristics'] = [];
        }

        try {
            $profile['ratings'] = $this->getPlayerRatings($playerId);
        } catch (\Throwable $e) {
            $profile['ratings'] = [];
        }

        try {
            $profile['statisticsSeasons'] = $this->getPlayerStatisticsSeasons($playerId);
        } catch (\Throwable $e) {
            $profile['statisticsSeasons'] = [];
        }

        try {
            $profile['allStatistics'] = $this->getPlayerAllStatistics($playerId);
        } catch (\Throwable $e) {
            $profile['allStatistics'] = [];
        }

        try {
            $profile['statistics'] = $this->getPlayerStatistics($playerId);
        } catch (\Throwable $e) {
            $profile['statistics'] = [];
        }

        try {
            $profile['transferHistory'] = $this->getPlayerTransferHistory($playerId);
        } catch (\Throwable $e) {
            $profile['transferHistory'] = [];
        }

        try {
            $profile['lastMatches'] = $this->getPlayerLastMatches($playerId);
        } catch (\Throwable $e) {
            $profile['lastMatches'] = [];
        }

        // Фолбэк для изображения: Sofascore обычно отдаёт по URL /player/{id}/image
        $profile['imageUrl'] = $profile['image']['image'] ?? $profile['image']['url'] ?? null;
        if ($profile['imageUrl'] && strpos($profile['imageUrl'], 'http') !== 0) {
            $profile['imageUrl'] = 'https://api.sofascore.com/api/v1' . $profile['imageUrl'];
        }
        if (!$profile['imageUrl'] && $playerId) {
            // публичный эндпоинт без RapidAPI-хедеров
            $profile['imageUrl'] = "https://api.sofascore.com/api/v1/player/{$playerId}/image";
        }

        return $profile;
    }

    /**
     * Детали команды по ID.
     */
    public function getTeamDetails(int $teamId): array
    {
        return $this->repository->get('teams/detail', ['teamId' => $teamId]);
    }

    /**
     * Поиск игроков по строке (имя/фамилия).
     * Возвращает массив игроков из общего поиска.
     */
    public function searchPlayers(string $query, int $limit = 20): array
    {
        if (trim($query) === '') {
            return [];
        }

        $data = $this->repository->get('search', [
            'q' => $query,
            'type' => 'player-team-persons',
            'page' => 0,
        ]);

        $results = $data['results'] ?? [];

        return array_map(static function ($item) {
            return PlayerSofascoreDTO::fromApi($item);
        }, $results);
    }

    /**
     * Возвращает игроков, применяя локальные фильтры и подготавливая списки позиций/стран.
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

        $filtered = array_values(array_filter($players, static function (PlayerSofascoreDTO $player) use ($country, $position, $teamId) {
            return $player->matchesFilters($country, $position, $teamId);
        }));

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
     * Универсальный метод для новых эндпоинтов Sofascore.
     * Пример: $service->fetch('api/v1/team/123/players', ['page' => 1]);
     */
    public function fetch(string $path, array $query = []): array
    {
        return $this->repository->get($path, $query);
    }
}
