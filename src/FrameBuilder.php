<?php

declare(strict_types=1);

namespace Dock\Ray;

use Dock\Ray\Serializer\RepresentationSerializerInterface;
use Dock\Ray\Util\Compat;

final class FrameBuilder
{
    /**
     * @var Options
     */
    private $options;

    /**
     * @var RepresentationSerializerInterface
     */
    private $representationSerializer;
    
    public function __construct(Options $options, RepresentationSerializerInterface $representationSerializer)
    {
        $this->options = $options;
        $this->representationSerializer = $representationSerializer;
    }

    public function buildFromBacktraceFrame(string $file, int $line, array $backtraceFrame): Frame
    {
        // The filename can be in any of these formats:
        //   - </path/to/filename>
        //   - </path/to/filename>(<line number>) : eval()'d code
        //   - </path/to/filename>(<line number>) : runtime-created function
        if (preg_match('/^(.*)\((\d+)\) : (?:eval\(\)\'d code|runtime-created function)$/', $file, $matches)) {
            $file = $matches[1];
            $line = (int) $matches[2];
        }

        $functionName = null;
        $rawFunctionName = null;
        $strippedFilePath = $this->stripPrefixFromFilePath($file);

        if (isset($backtraceFrame['class']) && isset($backtraceFrame['function'])) {
            $functionName = $backtraceFrame['class'];

            if (Compat::startsWith($functionName, Frame::ANONYMOUS_CLASS_PREFIX)) {
                $functionName = Frame::ANONYMOUS_CLASS_PREFIX . $this->stripPrefixFromFilePath(substr($backtraceFrame['class'], \strlen(Frame::ANONYMOUS_CLASS_PREFIX)));
            }

            $rawFunctionName = sprintf('%s::%s', $backtraceFrame['class'], $backtraceFrame['function']);
            $functionName = sprintf('%s::%s', preg_replace('/0x[a-fA-F0-9]+$/', '', $functionName), $backtraceFrame['function']);
        } elseif (isset($backtraceFrame['function'])) {
            $functionName = $backtraceFrame['function'];
        }

        return new Frame(
            $functionName,
            $strippedFilePath,
            $line,
            $rawFunctionName,
            Frame::INTERNAL_FRAME_FILENAME !== $file ? $file : null,
            $this->getFunctionArguments($backtraceFrame),
            $this->isFrameInApp($file, $functionName)
        );
    }

    private function stripPrefixFromFilePath(string $filePath): string
    {
        foreach ($this->options->getPrefixes() as $prefix) {
            if (Compat::startsWith($filePath, $prefix)) {
                return mb_substr($filePath, mb_strlen($prefix));
            }
        }

        return $filePath;
    }

    private function isFrameInApp(string $file, ?string $functionName): bool
    {
        if (Frame::INTERNAL_FRAME_FILENAME === $file) {
            return false;
        }

        if (null !== $functionName && Compat::startsWith($functionName, 'Dock\Ray\\')) {
            return false;
        }

        $excludedAppPaths = $this->options->getInAppExcludedPaths();
        $includedAppPaths = $this->options->getInAppIncludedPaths();
        $absoluteFilePath = @realpath($file) ?: $file;
        $isInApp = true;

        foreach ($excludedAppPaths as $excludedAppPath) {
            if (Compat::startsWith($absoluteFilePath, $excludedAppPath)) {
                $isInApp = false;

                break;
            }
        }

        foreach ($includedAppPaths as $includedAppPath) {
            if (Compat::startsWith($absoluteFilePath, $includedAppPath)) {
                $isInApp = true;

                break;
            }
        }

        return $isInApp;
    }

    private function getFunctionArguments(array $backtraceFrame): array
    {
        if (!isset($backtraceFrame['function'], $backtraceFrame['args'])) {
            return [];
        }

        $reflectionFunction = null;

        try {
            if (isset($backtraceFrame['class'], $backtraceFrame['function'])) {
                if (method_exists($backtraceFrame['class'], $backtraceFrame['function'])) {
                    $reflectionFunction = new \ReflectionMethod($backtraceFrame['class'], $backtraceFrame['function']);
                } elseif (isset($backtraceFrame['type']) && '::' === $backtraceFrame['type']) {
                    $reflectionFunction = new \ReflectionMethod($backtraceFrame['class'], '__callStatic');
                } else {
                    $reflectionFunction = new \ReflectionMethod($backtraceFrame['class'], '__call');
                }
            } elseif (!\in_array($backtraceFrame['function'], ['{closure}', '__lambda_func'], true) && \function_exists($backtraceFrame['function'])) {
                $reflectionFunction = new \ReflectionFunction($backtraceFrame['function']);
            }
        } catch (\ReflectionException $e) {
            // Reflection failed, we do nothing instead
        }

        $argumentValues = [];

        if (null !== $reflectionFunction) {
            $argumentValues = $this->getFunctionArgumentValues($reflectionFunction, $backtraceFrame['args']);
        } else {
            foreach ($backtraceFrame['args'] as $parameterPosition => $parameterValue) {
                $argumentValues['param' . $parameterPosition] = $parameterValue;
            }
        }

        foreach ($argumentValues as $argumentName => $argumentValue) {
            $argumentValues[$argumentName] = $this->representationSerializer->representationSerialize($argumentValue);
        }

        return $argumentValues;
    }

    private function getFunctionArgumentValues(\ReflectionFunctionAbstract $reflectionFunction, array $backtraceFrameArgs): array
    {
        $argumentValues = [];

        foreach ($reflectionFunction->getParameters() as $reflectionParameter) {
            $parameterPosition = $reflectionParameter->getPosition();

            if (!isset($backtraceFrameArgs[$parameterPosition])) {
                continue;
            }

            $argumentValues[$reflectionParameter->getName()] = $backtraceFrameArgs[$parameterPosition];
        }

        return $argumentValues;
    }
}
