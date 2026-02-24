<?php

namespace BookStack\Backup;

class SqlStreamFilter
{
    protected $input;
    protected $output;
    protected bool $inMultiLine = false;
    protected int $filteredCount = 0;

    /**
     * Allowed SQL statement prefixes (case-insensitive).
     */
    protected array $allowedPrefixes = [
        'DROP TABLE',
        'CREATE TABLE',
        'INSERT INTO',
        'LOCK TABLES',
        'UNLOCK TABLES',
        'SET ',
        'ALTER TABLE',
    ];

    public function __construct($inputStream, $outputStream)
    {
        $this->input = $inputStream;
        $this->output = $outputStream;
    }

    /**
     * Read from the input stream line-by-line, filter SQL statements,
     * and write allowed lines to the output stream.
     *
     * Returns the number of filtered (blocked) statements.
     */
    public function pipe(): int
    {
        while (($line = fgets($this->input, 1024 * 1024)) !== false) {
            if ($this->inMultiLine) {
                fwrite($this->output, $line);
                if ($this->isEndOfMultiLine($line)) {
                    $this->inMultiLine = false;
                }
                continue;
            }

            $trimmed = ltrim($line);

            // Pass through empty lines and whitespace
            if ($trimmed === '' || $trimmed === "\n" || $trimmed === "\r\n") {
                fwrite($this->output, $line);
                continue;
            }

            // Pass through SQL comments
            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                fwrite($this->output, $line);
                continue;
            }

            if ($this->isAllowedStatement($trimmed)) {
                fwrite($this->output, $line);

                // Track multi-line CREATE TABLE statements
                if ($this->isStartOfMultiLine($trimmed) && !$this->isEndOfMultiLine($trimmed)) {
                    $this->inMultiLine = true;
                }
            } else {
                $this->filteredCount++;
            }
        }

        return $this->filteredCount;
    }

    /**
     * Check if a line starts with an allowed SQL statement prefix.
     */
    protected function isAllowedStatement(string $line): bool
    {
        $upper = strtoupper($line);

        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($upper, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a line begins a multi-line statement (e.g. CREATE TABLE with no closing semicolon).
     */
    protected function isStartOfMultiLine(string $line): bool
    {
        $upper = strtoupper($line);

        return str_starts_with($upper, 'CREATE TABLE');
    }

    /**
     * Check if a line ends a multi-line statement (ends with ";").
     */
    protected function isEndOfMultiLine(string $line): bool
    {
        $trimmed = rtrim($line);

        return str_ends_with($trimmed, ';');
    }
}
