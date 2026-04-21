<?php

namespace Modules\Tenders\Actions;

use App\Models\Tender;
use App\Models\TenderSection;

class UpdateTenderSectionAction
{
    public function execute(Tender $tender, int $sectionId, string $content): TenderSection
    {
        $section = $tender->sections()->findOrFail($sectionId);

        $section->update([
            'content'   => $content,
            'is_edited' => true,
        ]);

        return $section;
    }
}
