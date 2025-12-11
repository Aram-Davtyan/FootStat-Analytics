<?php

namespace app\components\DTO;

class PlayerSofascoreDTO
{
    /** @var int|null */
    public $id;

    /** @var string|null */
    public $name;

    /** @var string|null */
    public $position;

    /** @var int|null */
    public $teamId;

    /** @var string|null */
    public $teamName;

    /** @var string|null */
    public $country;

    /** @var array */
    public $raw;

    private function __construct()
    {
    }

    public static function fromApi(array $item): self
    {
        $dto = new self();

        $entity = $item['entity'] ?? [];
        $team = $item['team'] ?? ($entity['team'] ?? ($entity['currentTeam'] ?? []));
        $country = $entity['country'] ?? ($team['country'] ?? null);

        $dto->id = isset($entity['id']) ? (int)$entity['id'] : null;
        $dto->name = $entity['name'] ?? ($entity['shortName'] ?? null);
        $dto->position = $entity['position'] ?? null;
        $dto->teamId = isset($team['id']) ? (int)$team['id'] : null;
        $dto->teamName = $team['name'] ?? ($team['shortName'] ?? null);
        $dto->country = is_array($country) ? ($country['name'] ?? null) : ($country ?? null);
        $dto->raw = $item;

        return $dto;
    }

    public function matchesFilters(string $country = '', string $position = '', int $teamId = 0): bool
    {
        if ($country !== '' && (stripos((string)$this->country, $country) === false)) {
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
