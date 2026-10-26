<?php
namespace Grav\Plugin\FormRateLimit;

/**
 * Standalone sliding-window rate limiter without Grav dependencies.
 *
 * Storage: one JSON file per bucket (accepted unix timestamps as floats),
 * guarded by an exclusive flock during check-and-record. Only accepted
 * submissions write a timestamp; rejected attempts change nothing.
 */
class RateLimiter
{
    private string $dataDir;
    private int $minInterval;
    private int $hourlyMax;
    private int $window;
    private int $maxFiles;
    private $onStorageError;

    public function __construct(
        string $dataDir,
        int $minInterval = 60,
        int $hourlyMax = 3,
        int $window = 3600,
        int $maxFiles = 5000,
        ?callable $onStorageError = null
    ) {
        $this->dataDir = rtrim($dataDir, '/');
        $this->minInterval = max(1, $minInterval);
        $this->hourlyMax = max(1, $hourlyMax);
        $this->window = max(1, $window);
        $this->maxFiles = max(1, $maxFiles);
        $this->onStorageError = $onStorageError;
    }

    /**
     * Per-IP bucket is scoped to the form (form name + IP). A target value
     * (e.g. applicant email address) gets a GLOBAL bucket keyed only by
     * sha256 of the normalized value, so it protects the recipient across
     * all forms. The plaintext value is never stored.
     *
     * @return string|null null = accepted, otherwise the German rejection message
     */
    public function check(string $formName, string $ip, ?string $target, ?float $now = null): ?string
    {
        $now = $now ?? microtime(true);

        $keys = [$this->hashKey('ip', $formName . '|' . $ip)];
        if ($target !== null) {
            $target = mb_strtolower(trim($target));
            if ($target !== '') {
                $keys[] = $this->hashKey('target', $target);
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys); // fixed lock order across buckets: no deadlocks

        $handles = [];
        try {
            if (!$this->ensureDir()) {
                $this->storageError('storage dir not writable: ' . $this->dataDir);
                return null; // storage broken: don't block the visitor, don't crash
            }
            foreach ($keys as $key) {
                $file = $this->dataDir . '/' . $key . '.json';
                $handle = @fopen($file, 'c+');
                if ($handle === false || !flock($handle, LOCK_EX)) {
                    $this->storageError('cannot lock bucket file: ' . $file);
                    if (is_resource($handle ?? null)) {
                        fclose($handle);
                    }
                    return null;
                }
                $handles[] = $handle;
            }

            $timestamps = [];
            foreach ($keys as $i => $key) {
                $timestamps[$key] = $this->readBucket($handles[$i]);
            }

            $message = null;
            foreach ($keys as $key) {
                $message = $this->evaluate($timestamps[$key], $now);
                if ($message !== null) {
                    break;
                }
            }

            if ($message === null) {
                foreach ($keys as $i => $key) {
                    $this->writeBucket($handles[$i], $timestamps[$key], $now);
                }
                $this->pruneStorage();
            }

            return $message;
        } finally {
            foreach ($handles as $handle) {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }
        }
    }

    private function evaluate(array $timestamps, float $now): ?string
    {
        if ($timestamps === []) {
            return null;
        }
        sort($timestamps);

        $last = $timestamps[count($timestamps) - 1];
        $since = $now - $last;
        if ($since < $this->minInterval) {
            $seconds = (int) floor($this->minInterval - $since);
            return $seconds < 1
                ? 'Zu viele Anträge. Bitte warte noch einen Moment und versuche es erneut.'
                : sprintf('Zu viele Anträge. Bitte warte noch %d Sekunden und versuche es erneut.', $seconds);
        }

        $recent = array_values(array_filter($timestamps, fn ($ts) => ($now - $ts) < $this->window));
        if (count($recent) >= $this->hourlyMax) {
            $minutes = (int) ceil(($recent[0] + $this->window - $now) / 60);
            return sprintf(
                'Zu viele Anträge. Bitte warte noch %d Minuten und versuche es erneut.',
                max(1, $minutes)
            );
        }

        return null;
    }

    /**
     * @return float[] valid timestamps, [] on missing/broken files
     */
    private function readBucket($handle): array
    {
        rewind($handle);
        $data = json_decode((string) stream_get_contents($handle), true);
        if (!is_array($data)) {
            return []; // broken or empty file: start over, never crash
        }
        $timestamps = [];
        foreach ($data as $ts) {
            if (is_int($ts) || is_float($ts)) {
                $timestamps[] = (float) $ts;
            }
        }
        return $timestamps;
    }

    private function writeBucket($handle, array $timestamps, float $now): void
    {
        $timestamps[] = $now;
        $timestamps = array_values(array_filter($timestamps, fn ($ts) => ($now - $ts) < $this->window));
        sort($timestamps);

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($timestamps) ?: '[]');
        fflush($handle);
    }

    private function pruneStorage(): void
    {
        $files = @scandir($this->dataDir);
        if ($files === false) {
            return;
        }
        $files = array_values(array_filter($files, fn ($f) => str_ends_with($f, '.json')));
        if (count($files) <= $this->maxFiles) {
            return;
        }
        $mtimes = [];
        foreach ($files as $file) {
            $mtimes[$file] = @filemtime($this->dataDir . '/' . $file) ?: 0;
        }
        asort($mtimes); // least recently used first
        foreach (array_slice(array_keys($mtimes), 0, 1000) as $file) {
            @unlink($this->dataDir . '/' . $file);
        }
    }

    private function ensureDir(): bool
    {
        if (is_dir($this->dataDir)) {
            return true;
        }
        return @mkdir($this->dataDir, 0775, true) || is_dir($this->dataDir);
    }

    private function storageError(string $message): void
    {
        if ($this->onStorageError !== null) {
            ($this->onStorageError)($message);
        }
    }

    private function hashKey(string $prefix, string $value): string
    {
        return hash('sha256', $prefix . '|' . $value);
    }
}
