<?php

declare(strict_types=1);

namespace Dock\Ray\Transport;

use Dock\Ray\Event;
use Dock\Ray\Framework\ResponseFlusher;
use Dock\Ray\Response;
use Dock\Ray\ResponseStatus;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Kolejkuje zdarzenia i wysyła je dopiero po oddaniu odpowiedzi.
 *
 * Zdarzenie trafia do kolejki w czasie stałym, a cały ruch do panelu odbywa
 * się w `register_shutdown_function()`, już po zamknięciu połączenia
 * z przeglądarką. Aplikacja nie czeka na monitoring nawet wtedy, gdy panel
 * odpowiada wolno albo wcale.
 */
final class DeferredTransport implements TransportInterface
{
    /**
     * Żądanie, które sypie błędami setkami, nie może wyczerpać pamięci przez
     * kolejkę raportów. Powyżej progu nowe zdarzenia są pomijane — licznik
     * w panelu i tak podbija je po odcisku.
     */
    private const MAX_QUEUED_EVENTS = 50;

    /** @var Event[] */
    private array $queue = [];

    private bool $registered = false;

    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function send(Event $event): PromiseInterface
    {
        if (\count($this->queue) >= self::MAX_QUEUED_EVENTS) {
            return new FulfilledPromise(new Response(ResponseStatus::skipped(), $event));
        }

        $this->queue[] = $event;
        $this->register();

        return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
    }

    public function close(?int $timeout = null): PromiseInterface
    {
        $this->flush();

        return $this->transport->close($timeout);
    }

    public function flush(): void
    {
        $queue = $this->queue;
        $this->queue = [];

        foreach ($queue as $event) {
            try {
                $this->transport->send($event)->wait(false);
            } catch (\Throwable $exception) {
                // Wysyłka po odpowiedzi nie ma już komu zgłosić awarii.
            }
        }
    }

    /**
     * Rejestracja jest leniwa nie dla oszczędności, tylko dla kolejności:
     * `register_shutdown_function()` wykonuje wpisy w kolejności dodania,
     * a obsługa błędu krytycznego rejestruje się przy budowie klienta. Handler
     * dopisany dopiero przy pierwszym zdarzeniu wypada za nią, więc błąd
     * krytyczny zdąży trafić do kolejki, zanim ją opróżnimy.
     */
    private function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        register_shutdown_function(function (): void {
            ResponseFlusher::flush();
            $this->flush();
        });
    }
}
