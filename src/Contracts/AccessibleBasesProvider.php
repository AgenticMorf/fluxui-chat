<?php

namespace AgenticMorf\FluxUIChat\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AccessibleBasesProvider
{
    /**
     * Get bases the user can access for RAG attachments.
     *
     * @return array<string, string> Map of base_id => base_name
     */
    public function getAccessibleBases(Authenticatable $user): array;

    /**
     * Get the default base ID for the user (e.g. personal base).
     */
    public function getDefaultBaseId(Authenticatable $user): ?string;
}
