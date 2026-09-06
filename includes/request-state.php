<?php
declare(strict_types=1);

final class CloudsysRequestBusy extends RuntimeException {}

/** Hold all related counter locks from the first read through the final write.
 * Nonblocking acquisition prevents slow upstream requests tying up PHP workers.
 * Lock files must not be unlinked while requests may be running.
 */
final class CloudsysRequestLocks
{
    private array $handles = [];

    public function __construct(array $statePaths)
    {
        $statePaths = array_unique($statePaths);
        sort($statePaths, SORT_STRING);
        try {
            foreach ($statePaths as $path) {
                $oldMask = umask(0077);
                try { $handle = @fopen($path . '.lock', 'c'); }
                finally { umask($oldMask); }
                if ($handle === false) throw new RuntimeException('Cannot open request lock.');
                if (!@chmod($path . '.lock', 0600)) { fclose($handle); throw new RuntimeException('Cannot secure request lock.'); }
                if (!flock($handle, LOCK_EX | LOCK_NB)) {
                    fclose($handle);
                    throw new CloudsysRequestBusy('A request is already in progress. Please wait a moment and try again.');
                }
                $this->handles[] = $handle;
            }
        } catch (Throwable $error) { $this->release(); throw $error; }
        // PHP exit() does not execute finally blocks; shutdown still releases locks.
        register_shutdown_function([$this, 'release']);
    }

    public function release(): void
    {
        foreach (array_reverse($this->handles) as $handle) {
            if (is_resource($handle)) { flock($handle, LOCK_UN); fclose($handle); }
        }
        $this->handles = [];
    }
}

function cloudsys_read_state(string $path): array
{
    if (!is_file($path)) return [];
    $json = @file_get_contents($path);
    if ($json === false) throw new RuntimeException('Cannot read request state.');
    try { $state = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $error) { throw new RuntimeException('Invalid request state.', 0, $error); }
    if (!is_array($state)) throw new RuntimeException('Invalid request state.');
    return $state;
}

function cloudsys_write_state(string $path, array $state): void
{
    $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $oldMask = umask(0077);
    try { $bytes = @file_put_contents($path, $json, LOCK_EX); }
    finally { umask($oldMask); }
    if ($bytes !== strlen($json) || !@chmod($path, 0600)) throw new RuntimeException('Cannot store request state.');
}
