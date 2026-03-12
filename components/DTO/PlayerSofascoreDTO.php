<?php

namespace app\components\DTO;

/**
 * DTO для нормализованного представления игрока из поисковой выдачи Sofascore.
 */
class PlayerSofascoreDTO
{
    /**
     * Идентификатор игрока.
     */
    public ?int $id = null;

    /**
     * Имя игрока.
     */
    public ?string $name = null;

    /**
     * Позиция игрока.
     */
    public ?string $position = null;

    /**
     * Идентификатор команды.
     */
    public ?int $teamId = null;

    /**
     * Название команды.
     */
    public ?string $teamName = null;

    /**
     * Страна игрока.
     */
    public ?string $country = null;

    /**
     * Исходные данные API без изменений.
     *
     * @var array<string, mixed>
     */
    public array $raw = [];

    /**
     * Приватный конструктор для принудительного использования фабрики.
     */
    private function __construct()
    {
    }

    /**
     * Создает DTO из элемента API-ответа.
     *
     * @param array<string, mixed> $item сырой элемент поиска.
     */
    public static function fromApi(array $item): self
    {
        $dto = new self();

        $entity = $item['entity'] ?? [];
        $team = $item['team'] ?? ($entity['team'] ?? ($entity['currentTeam'] ?? []));
        $country = $entity['country'] ?? ($team['country'] ?? null);

        $dto->id = isset($entity['id']) ? (int) $entity['id'] : null;
        $dto->name = $entity['name'] ?? ($entity['shortName'] ?? null);
        $dto->position = $entity['position'] ?? null;
        $dto->teamId = isset($team['id']) ? (int) $team['id'] : null;
        $dto->teamName = $team['name'] ?? ($team['shortName'] ?? null);
        $dto->country = is_array($country) ? ($country['name'] ?? null) : ($country ?? null);
        $dto->raw = $item;

        return $dto;
    }

    /**
     * Проверяет соответствие DTO заданным фильтрам.
     *
     * @param string $country фильтр по стране.
     * @param string $position фильтр по позиции.
     * @param int $teamId фильтр по идентификатору команды.
     */
    public function matchesFilters(string $country = '', string $position = '', int $teamId = 0): bool
    {
        if ($country !== '' && stripos((string) $this->country, $country) === false) {
            return false;
        }

        if ($position !== '' && ($this->position === null || stripos($this->position, $position) === false)) {
            return false;
        }

        if ($teamId > 0 && $this->teamId !== $teamId) {
            return false;
        }

        return true;
    }
}
