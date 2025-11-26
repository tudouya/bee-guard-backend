<?php

namespace App\Filament\Admin\Resources\DetectionCodeResource\Pages;

use App\Filament\Admin\Resources\DetectionCodeResource;
use Filament\Resources\Pages\EditRecord;

class EditDetectionCode extends EditRecord
{
    protected static string $resource = DetectionCodeResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }

    /**
     * Normalize status/assignee fields on edit to maintain invariants.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === 'available') {
            $data['assigned_user_id'] = null;
            $data['assigned_at'] = null;
        } elseif (! empty($data['assigned_user_id'])) {
            if (empty($data['assigned_at'])) {
                $data['assigned_at'] = now();
            }
            // Only coerce to assigned when status is blank/available; respect explicit statuses.
            if (in_array($data['status'] ?? null, [null, '', 'available'], true)) {
                $data['status'] = 'assigned';
            }
        }

        if (($data['status'] ?? null) === 'used' && empty($data['used_at'])) {
            $data['used_at'] = now();
        }

        return $data;
    }
}
