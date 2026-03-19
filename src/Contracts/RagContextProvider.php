<?php

namespace AgenticMorf\FluxUIChat\Contracts;

interface RagContextProvider
{
    /**
     * Get RAG context for the given message.
     */
    public function getContext(string $message, int $topK = 10): string;
}
