<?php

declare(strict_types=1);

namespace Dock\Ray;

final class Response
{
    /**
     * @var ResponseStatus
     */
    private $status;

    /**
     * @var Event|null
     */
    private $event;

    public function __construct(ResponseStatus $status, ?Event $event = null)
    {
        $this->status = $status;
        $this->event = $event;
    }

    public function getStatus(): ResponseStatus
    {
        return $this->status;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }
}
