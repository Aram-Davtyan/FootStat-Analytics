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
