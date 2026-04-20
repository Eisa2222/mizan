<?php

namespace Modules\Branding\Actions;

use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Updates the organization's branding. Replaces the logo on disk when a new
 * file is uploaded and deletes the previous one so we don't leak storage.
 */
class UpdateBrandingAction
{
    /**
     * @param  array<string,mixed>  $data  validated fields from UpdateBrandingRequest
     */
    public function execute(Organization $org, array $data, ?UploadedFile $logo): Organization
    {
        unset($data['logo']);

        if ($logo !== null) {
            if ($org->logo_path) {
                Storage::disk('public')->delete($org->logo_path);
            }
            $data['logo_path'] = $logo->store('branding', 'public');
        }

        $org->update($data);

        return $org;
    }
}
