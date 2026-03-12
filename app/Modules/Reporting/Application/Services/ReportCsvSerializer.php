<?php

namespace App\Modules\Reporting\Application\Services;

final class ReportCsvSerializer
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function serialize(array $rows): string
    {
        $flattenedRows = array_map(fn (array $row): array => $this->flatten($row), $rows);
        $headers = $this->headers($flattenedRows);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to allocate the CSV stream for report generation.');
        }

        if ($headers !== []) {
            fputcsv($stream, $headers);

            foreach ($flattenedRows as $row) {
                fputcsv($stream, array_map(
                    static fn (string $header): string => $row[$header] ?? '',
                    $headers,
                ));
            }
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return is_string($contents) ? $contents : '';
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<string>
     */
    private function headers(array $rows): array
    {
        $headers = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $header) {
                if (! in_array($header, $headers, true)) {
                    $headers[] = $header;
                }
            }
        }

        return $headers;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, string>
     */
    private function flatten(array $payload, string $prefix = ''): array
    {
        /** @var array<string, string> $result */
        $result = [];

        array_walk($payload, function (mixed $value, int|string $key) use (&$result, $prefix): void {
            $segment = (string) $key;
            $path = $prefix === '' ? $segment : $prefix.'.'.$segment;

            if (is_array($value)) {
                if ($value === []) {
                    $result[$path] = '[]';

                    return;
                }

                if ($this->isAssoc($value)) {
                    $result = array_merge($result, $this->flatten($value, $path));

                    return;
                }

                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $result[$path] = is_string($encoded) ? $encoded : '[]';

                return;
            }

            $result[$path] = $this->stringify($value);
        });

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            is_scalar($value) => (string) $value,
            default => $this->jsonString($value),
        };
    }

    private function jsonString(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }
}
