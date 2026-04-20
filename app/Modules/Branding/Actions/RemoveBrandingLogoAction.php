<?php

namespace Modules\Branding\Actions;

use App\Models\Organization;
use Illuminate\Support\Facades\Storage;

class RemoveBrandingLogoAction
{
    public function execute(Organization $org): void
    {
        if (! $org->logo_path) {
            return;
        }

        Storage::disk('public')->delete($org->logo_path);
        $org->update(['logo_path' => null]);
    }
}
