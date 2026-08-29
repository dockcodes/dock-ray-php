<?php

declare(strict_types=1);

namespace Dock\Thor;

use Dock\Thor\Exception\FatalErrorException;
use Dock\Thor\Exception\SilencedErrorException;

final class ErrorHandler
{
    public const DEFAULT_RESERVED_MEMORY_SIZE = 10240;

    private const PHP8_UNSILENCEABLE_FATAL_ERRORS = \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR;

    /**
     * @var self|null
     */
    private static $handlerInstance = null;

    /**
     * @var array
     */
    private $errorListeners = [];

    /**
     * @var array
     */
    private $fatalErrorListeners = [];

    /**
     * @var array
     */
    private $exceptionListeners = [];

    /**
     * @var \ReflectionProperty
     */
    private $exceptionReflection;

    private $previousErrorHandler;

    private $previousExceptionHandler;

    /**
     * @var bool
     */
    private $isErrorHandlerRegistered = false;

    /**
     * @var bool
     */
    private $isExceptionHandlerRegistered = false;

    /**
     * @var bool
     */
    private $isFatalErrorHandlerRegistered = false;

    /**
     * @var string|null
     */
    private static $reservedMemory = null;

    private const ERROR_LEVELS_DESCRIPTION = [
        \E_DEPRECATED => 'Deprecated',
        \E_USER_DEPRECATED => 'User Deprecated',
        \E_NOTICE => 'Notice',
        \E_USER_NOTICE => 'User Notice',
        \E_WARNING => 'Warning',
        \E_USER_WARNING => 'User Warning',
        \E_COMPILE_WARNING => 'Compile Warning',
        \E_CORE_WARNING => 'Core Warning',
        \E_USER_ERROR => 'User Error',
        \E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        \E_COMPILE_ERROR => 'Compile Error',
        \E_PARSE => 'Parse Error',
        \E_ERROR => 'Error',
        \E_CORE_ERROR => 'Core Error',
    ];

    private function __construct()
    {
        $this->exceptionReflection = new \ReflectionProperty(\Exception::class, 'trace');
        $this->exceptionReflection->setAccessible(true);
    }

    public static function registerOnceErrorHandler(): self
    {
        if (null === self::$handlerInstance) {
            self::$handlerInstance = new self();
        }

        if (self::$handlerInstance->isErrorHandlerRegistered) {
            return self::$handlerInstance;
        }

        $errorHandlerCallback = \Closure::fromCallable([self::$handlerInstance, 'handleError']);

        self::$handlerInstance->isErrorHandlerRegistered = true;
        self::$handlerInstance->previousErrorHandler = set_error_handler($errorHandlerCallback);

        if (null === self::$handlerInstance->previousErrorHandler) {
            restore_error_handler();

            set_error_handler($errorHandlerCallback, \E_ALL);
        }

        return self::$handlerInstance;
    }

    public static function registerOnceFatalErrorHandler(int $reservedMemorySize = self::DEFAULT_RESERVED_MEMORY_SIZE): self
    {
        if ($reservedMemorySize <= 0) {
            throw new \InvalidArgumentException('The $reservedMemorySize argument must be greater than 0.');
        }

        if (null === self::$handlerInstance) {
            self::$handlerInstance = new self();
        }

        if (self::$handlerInstance->isFatalErrorHandlerRegistered) {
            return self::$handlerInstance;
        }

        self::$handlerInstance->isFatalErrorHandlerRegistered = true;
        self::$reservedMemory = str_repeat('x', $reservedMemorySize);

        register_shutdown_function(\Closure::fromCallable([self::$handlerInstance, 'handleFatalError']));

        return self::$handlerInstance;
    }

    public static function registerOnceExceptionHandler(): self
    {
        if (null === self::$handlerInstance) {
            self::$handlerInstance = new self();
        }

        if (self::$handlerInstance->isExceptionHandlerRegistered) {
            return self::$handlerInstance;
        }

        self::$handlerInstance->isExceptionHandlerRegistered = true;
        self::$handlerInstance->previousExceptionHandler = set_exception_handler(\Closure::fromCallable([self::$handlerInstance, 'handleException']));

        return self::$handlerInstance;
    }

    public function addErrorHandlerListener(callable $listener): void
    {
        $this->errorListeners[] = $listener;
    }

    public function addFatalErrorHandlerListener(callable $listener): void
    {
        $this->fatalErrorListeners[] = $listener;
    }

    public function addExceptionHandlerListener(callable $listener): void
    {
        $this->exceptionListeners[] = $listener;
    }

    private static function describeLevel(int $level): string
    {
        return self::ERROR_LEVELS_DESCRIPTION[$level] ?? 'Error';
    }

    private function handleError(int $level, string $message, string $file, int $line, ?array $errcontext = []): bool
    {
        $isSilencedError = 0 === error_reporting();

        if (\PHP_MAJOR_VERSION >= 8) {
            $isSilencedError = 0 === (error_reporting() & ~self::PHP8_UNSILENCEABLE_FATAL_ERRORS);

            if ($level === (self::PHP8_UNSILENCEABLE_FATAL_ERRORS & $level)) {
                $isSilencedError = false;
            }
        }

        if ($isSilencedError) {
            $errorAsException = new SilencedErrorException(self::describeLevel($level) . ': ' . $message, 0, $level, $file, $line);
        } else {
            $errorAsException = new \ErrorException(self::describeLevel($level) . ': ' . $message, 0, $level, $file, $line);
        }

        $backtrace = $this->cleanBacktraceFromErrorHandlerFrames($errorAsException->getTrace(), $errorAsException->getFile(), $errorAsException->getLine());

        $this->exceptionReflection->setValue($errorAsException, $backtrace);

        $this->invokeListeners($this->errorListeners, $errorAsException);

        if (null !== $this->previousErrorHandler) {
            return false !== ($this->previousErrorHandler)($level, $message, $file, $line, $errcontext);
        }

        return false;
    }

    private function handleFatalError(): void
    {
        // If there is not enough memory that can be used to handle the error
        // do nothing
        if (null === self::$reservedMemory) {
            return;
        }

        self::$reservedMemory = null;
        $error = error_get_last();

        if (!empty($error) && $error['type'] & (\E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_CORE_WARNING | \E_COMPILE_ERROR | \E_COMPILE_WARNING)) {
            $errorAsException = new FatalErrorException(self::describeLevel($error['type']) . ': ' . $error['message'], 0, $error['type'], $error['file'], $error['line']);

            $this->exceptionReflection->setValue($errorAsException, []);

            $this->invokeListeners($this->fatalErrorListeners, $errorAsException);
        }
    }

    private function handleException(\Throwable $exception): void
    {
        $this->invokeListeners($this->exceptionListeners, $exception);

        $previousExceptionHandlerException = $exception;

        // Unset the previous exception handler to prevent infinite loop in case
        // we need to handle an exception thrown from it
        $previousExceptionHandler = $this->previousExceptionHandler;
        $this->previousExceptionHandler = null;

        try {
            if (null !== $previousExceptionHandler) {
                $previousExceptionHandler($exception);

                return;
            }
        } catch (\Throwable $previousExceptionHandlerException) {
            // This `catch` statement is here to forcefully override the
            // $previousExceptionHandlerException variable with the exception
            // we just caught
        }

        // If the instance of the exception we're handling is the same as the one
        // caught from the previous exception handler then we give it back to the
        // native PHP handler to prevent an infinite loop
        if ($exception === $previousExceptionHandlerException) {
            // Disable the fatal error handler or the error will be reported twice
            self::$reservedMemory = null;

            throw $exception;
        }

        $this->handleException($previousExceptionHandlerException);
    }

    private function cleanBacktraceFromErrorHandlerFrames(array $backtrace, string $file, int $line): array
    {
        $cleanedBacktrace = $backtrace;
        $index = 0;

        while ($index < \count($backtrace)) {
            if (isset($backtrace[$index]['file'], $backtrace[$index]['line']) && $backtrace[$index]['line'] === $line && $backtrace[$index]['file'] === $file) {
                $cleanedBacktrace = \array_slice($cleanedBacktrace, 1 + $index);

                break;
            }

            ++$index;
        }

        return $cleanedBacktrace;
    }

    private function invokeListeners(array $listeners, \Throwable $throwable): void
    {
        foreach ($listeners as $listener) {
            try {
                $listener($throwable);
            } catch (\Throwable $exception) {
                // Do nothing as this should be as transparent as possible
            }
        }
    }
}
