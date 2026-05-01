<?php
// Simple semaphore helper with static methods. Keeps compatibility storing resource in $GLOBALS["SEMAPHORES"].

class SemaphoreManager {
    protected static $semaphores = [];
    protected static $acquired = [];

    protected static function runId(): string {
        return isset($GLOBALS["runid"]) ? (string)$GLOBALS["runid"] : '-';
    }

    protected static function keyFromId(string $id): int {
        return abs(crc32($id));
    }

    public static function get(string $id) {
        $key = self::keyFromId($id);
        if (!isset(self::$semaphores[$key]) || !self::$semaphores[$key]) {
            $sem = sem_get($key);
            if ($sem === false) {
                Logger::warn("*TRACE: [SemaphoreManager] " . self::runId() . " sem_get failed for key {$key} (id={$id})");
                return null;
            }
            self::$semaphores[$key] = $sem;
            if (!isset(self::$acquired[$key])) {
                self::$acquired[$key] = false;
            }
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
        if (!$semaphore) {
            error_log("*TRACE: [SemaphoreManager] " . self::runId() . " Failed to get semaphore for id '{$id}'");
            return false;
        }

        $ix = 0;
        $t0 = time();
        //$tick_us = max(1, intval($tick_ms)) * 1000;
        $tick_us = max(251, intval($tick_ms * 51)); // tick ~20 times for full $tick_ms period, at least 4x per miliSec 
        $i_check = max(3, intval(472000 / $tick_us)); //check ~2x per second
        // for $tick_ms=1s   => $tick_us=51ms  $i_check=9
        //     $tick_ms=50ms => $tick_us=2.5ms $i_check=185
        while (sem_acquire($semaphore, true) !== true) {
            $ix++;
            if (is_callable($callback)) {
                try {
                    $cb = $callback();
                    if ($cb === false) {
                        Logger::warn("*TRACE: [SemaphoreManager] " . self::runId() . " callback returned false, aborting wait for '{$id}'");
                        return false;
                    }
                } catch (\Throwable $e) {
                    Logger::warn("*TRACE: [SemaphoreManager] " . self::runId() . " callback threw: ".$e->getMessage());
                }
            }
            if ($ix > $i_check) {
                $dt = time() - $t0;
                if ($dt > $timeout) {
                    Logger::warn("*TRACE: [SemaphoreManager] " . self::runId() . " wait loop break after {$dt} sec for semaphore '{$id}'");
                    return false;
                } else {
                    $ix = 0;
                }
            }

            //if ($ix % 10 ===  1) {
            //    Logger::warn("*TRACE: [SemaphoreManager] " . self::runId() . " still waiting for $id after {$ix} attempts and " . (time() - $t0) . " seconds");
            //}

            usleep($tick_us);
        }
        $key = self::keyFromId($id);
        self::$acquired[$key] = true;
        Logger::info("*TRACE: [SemaphoreManager] " . self::runId() . " Lock acquired by '{$id}'");
        return true;
    }

    public static function release(string $id): bool {
        $key = self::keyFromId($id);
        $semaphore = self::get($id);
        if (!$semaphore) {
            return false;
        }

        // Prevent sem_release warning when lock was never acquired in this process.
        if (empty(self::$acquired[$key])) {
            return false;
        }

        if (@sem_release($semaphore)) {
            self::$acquired[$key] = false;
            Logger::info("*TRACE: [SemaphoreManager] " . self::runId() . " Lock released for '{$id}'");
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