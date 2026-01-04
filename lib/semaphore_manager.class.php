<?php
// Simple semaphore helper with static methods. Keeps compatibility storing resource in $GLOBALS["SEMAPHORES"].

class SemaphoreManager {
    protected static $semaphores = [];

    protected static function keyFromId(string $id): int {
        return abs(crc32($id));
    }

    public static function get(string $id) {
        $key = self::keyFromId($id);
        if (!isset(self::$semaphores[$key]) || !self::$semaphores[$key]) {
            $sem = sem_get($key);
            if ($sem === false) {
                Logger::warn("[SemaphoreManager] sem_get failed for key {$key} (id={$id})");
                return null;
            }
            self::$semaphores[$key] = $sem;
            // expose under given id to keep backward compatibility
            $GLOBALS["SEMAPHORES"][$id] = self::$semaphores[$key];
        }
        return self::$semaphores[$key];
    }

    /**
     * Wait for semaphore acquire loop.
     * @param string $id logical id (will be crc32->key for sem_get)
     * @param int $timeout seconds to give up
     * @param int $tick_ms sleep between attempts in milliseconds
     * @param callable|null $callback optional callback executed each loop; if it returns false, wait aborts
     * @return bool true if lock acquired, false on timeout/failure
     */
    public static function wait(string $id, int $timeout = 300, int $tick_ms = 1003, $callback = null): bool {
        $semaphore = self::get($id);
        if (!$semaphore) return false;

        $ix = 0;
        $t0 = time();
        $tick_us = max(1, intval($tick_ms)) * 1000;

        while (sem_acquire($semaphore, true) !== true) {
            $ix++;
            if (is_callable($callback)) {
                try {
                    $cb = $callback();
                    if ($cb === false) {
                        return false;
                    }
                } catch (\Throwable $e) {
                    Logger::warn("[SemaphoreManager] callback threw: ".$e->getMessage());
                }
            }
            if ($ix > 2000) {
                $dt = time() - $t0;
                if ($dt > $timeout) {
                    Logger::warn("[SemaphoreManager] wait loop break after {$dt} sec for semaphore '{$id}'");
                    return false;
                } else {
                    $ix = 0;
                }
            }
            usleep($tick_us);
        }
        Logger::info("[SemaphoreManager] Lock acquired by '{$id}'");
        return true;
    }

    public static function release(string $id): bool {
        $semaphore = self::get($id);
        if ($semaphore) {
            @sem_release($semaphore);
            Logger::info("[SemaphoreManager] Lock released for '{$id}'");
            return true;
        }
        return false;
    }
}

// convenience global function matching requested call style
function SemaphoreWait(string $semaphore_id, int $timeout = 300, int $tick_time = 1003, $callback = null): bool {
    return SemaphoreManager::wait($semaphore_id, $timeout, $tick_time, $callback);
}

?>