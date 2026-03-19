<?php

namespace AgenticMorf\FluxUIChat\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;

interface ChatUploadService
{
    /**
     * Process uploaded files and return pending attachment metadata.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{base_id: string, document_id: string}>
     */
    public function processUploads(Authenticatable $user, string $conversationId, array $files, string $baseId): array;

    /**
     * Create message_attachments records linking a message to documents.
     *
     * @param  array<int, array{base_id: string, document_id: string}>  $pendingAttachments
     */
    public function createMessageAttachments(string $messageId, array $pendingAttachments): void;
}
