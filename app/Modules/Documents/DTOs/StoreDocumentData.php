<?php

namespace Modules\Documents\DTOs;

use Illuminate\Http\UploadedFile;

final class StoreDocumentData
{
    public function __construct(
        public readonly int $orgId,
        public readonly int $uploadedBy,
        public readonly string $title,
        public readonly ?string $titleEn,
        public readonly int $type,
        public readonly string $kind,
        public readonly ?string $summary,
        public readonly ?string $content,
        public readonly ?string $issuedAt,
        public readonly ?string $referenceNumber,
        public readonly ?string $sourceEntity,
        public readonly ?UploadedFile $file,
    ) {
    }
}
