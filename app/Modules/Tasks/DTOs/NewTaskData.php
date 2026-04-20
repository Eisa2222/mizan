<?php

namespace Modules\Tasks\DTOs;

final class NewTaskData
{
    public function __construct(
        public readonly int $orgId,
        public readonly int $createdById,
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $priority,
        public readonly ?string $dueDate,
        public readonly ?int $documentId,
    ) {
    }
}
