<?php

declare(strict_types=1);

namespace Dock\Ray\Tracing;

final class SamplingContext
{
    /**
     * @var TransactionContext|null
     */
    private $transactionContext = null;

    /**
     * @var bool|null
     */
    private $parentSampled = null;

    /**
     * @var array
     */
    private $additionalContext;

    public static function getDefault(TransactionContext $transactionContext): self
    {
        $context = new self();
        $context->transactionContext = $transactionContext;
        $context->parentSampled = $transactionContext->getParentSampled();

        return $context;
    }

    public function getTransactionContext(): ?TransactionContext
    {
        return $this->transactionContext;
    }

    public function getParentSampled(): ?bool
    {
        return $this->parentSampled;
    }

    public function setParentSampled(?bool $parentSampled): void
    {
        $this->parentSampled = $parentSampled;
    }

    public function setAdditionalContext(?array $additionalContext): void
    {
        $this->additionalContext = $additionalContext;
    }

    public function getAdditionalContext(): ?array
    {
        return $this->additionalContext;
    }
}
