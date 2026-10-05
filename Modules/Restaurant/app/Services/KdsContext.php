<?php

namespace Modules\Restaurant\Services;

use LogicException;
use Modules\Restaurant\Models\KitchenStation;

/**
 * The station a kitchen display request is about, set by EnsureKdsStation: the station of the signed-in
 * display device, or the one a signed-in person chose. userId is that person (null for a device).
 * Scoped to the request.
 */
class KdsContext
{
    private ?KitchenStation $station = null;

    private ?int $userId = null;

    public function set(KitchenStation $station, ?int $userId): void
    {
        $this->station = $station;
        $this->userId = $userId;
    }

    public function station(): KitchenStation
    {
        return $this->station ?? throw new LogicException('No kitchen station for this request.');
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function isDevice(): bool
    {
        return $this->userId === null;
    }
}
