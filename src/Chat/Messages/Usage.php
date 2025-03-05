<?php

namespace NeuronAI\Chat\Messages;

class Usage
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens
    ) {}

    public function getTotal(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
