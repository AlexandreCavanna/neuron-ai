<?php

namespace NeuronAI\RAG\DataReader;

use Closure;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Requires pdftotext php extension.
 *
 * https://en.wikipedia.org/wiki/Pdftotext
 */
class PdfParser
{
    protected string $pdf;

    protected string $binPath;

    protected array $options = [];

    protected int $timeout = 60;

    protected array $env = [];

    public function __construct(?string $binPath = null)
    {
        $this->binPath = $binPath ?? $this->findPdfToText();
    }

    protected function findPdfToText(): string
    {
        $commonPaths = [
            '/usr/bin/pdftotext',          // Common on Linux
            '/usr/local/bin/pdftotext',    // Common on Linux
            '/opt/homebrew/bin/pdftotext', // Homebrew on macOS (Apple Silicon)
            '/opt/local/bin/pdftotext',    // MacPorts on macOS
            '/usr/local/bin/pdftotext',    // Homebrew on macOS (Intel)
        ];

        foreach ($commonPaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        throw new \LogicException("The pdftotext binary was not found or is not executable.");
    }

    public function setPdf(string $pdf): self
    {
        if (!is_readable($pdf)) {
            throw new \Exception("Could not read `{$pdf}`");
        }

        $this->pdf = $pdf;

        return $this;
    }

    public function setOptions(array $options): self
    {
        $this->options = $this->parseOptions($options);

        return $this;
    }

    public function addOptions(array $options): self
    {
        $this->options = array_merge(
            $this->options,
            $this->parseOptions($options)
        );

        return $this;
    }

    protected function parseOptions(array $options): array
    {
        $mapper = function (string $content): array {
            $content = trim($content);
            if ('-' !== ($content[0] ?? '')) {
                $content = '-' . $content;
            }

            return explode(' ', $content, 2);
        };

        $reducer = fn(array $carry, array $option): array => array_merge($carry, $option);

        return array_reduce(array_map($mapper, $options), $reducer, []);
    }

    public function setTimeout($timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function text(?Closure $callback = null): string
    {
        $process = new Process(array_merge([$this->binPath], $this->options, [$this->pdf, '-']));
        $process->setTimeout($this->timeout);
        $process = $callback ? $callback($process) : $process;
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return trim($process->getOutput(), " \t\n\r\0\x0B\x0C");
    }

    /**
     * @throws \Exception
     */
    public static function getText(
        string $pdf,
        ?string $binPath = null,
        array $options = [],
        $timeout = 60,
        ?Closure $callback = null
    ): string {
        return (new static($binPath))
            ->setOptions($options)
            ->setTimeout($timeout)
            ->setPdf($pdf)
            ->text($callback);
    }
}
