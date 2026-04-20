<?php

namespace Modules\Tenders\Actions;

use App\Models\Tender;

/**
 * Copies sections from a matched tender into the current tender.
 * When `sectionKeys` is empty, all sections are copied. Existing sections
 * with the same key are updated in place; new ones are appended to the end.
 */
class ReuseTenderSectionsAction
{
    /**
     * @param  array<string>  $sectionKeys
     *
     * @return int  number of sections copied
     */
    public function execute(Tender $tender, Tender $source, array $sectionKeys): int
    {
        $copied = 0;

        foreach ($source->sections as $section) {
            if (! empty($sectionKeys) && ! in_array($section->section_key, $sectionKeys, true)) {
                continue;
            }

            $existing = $tender->sections()->where('section_key', $section->section_key)->first();

            if ($existing !== null) {
                $existing->update(['content' => $section->content, 'is_edited' => true]);
            } else {
                $tender->sections()->create([
                    'section_key' => $section->section_key,
                    'title'       => $section->title,
                    'content'     => $section->content,
                    'order'       => $tender->sections()->max('order') + 1,
                    'is_edited'   => true,
                ]);
            }

            $copied++;
        }

        return $copied;
    }
}
