<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        Model $subject,
        array $metadata = [],
        ?int $companyId = null,
        ?int $fuelStationId = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'company_id' => $companyId,
            'fuel_station_id' => $fuelStationId,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
