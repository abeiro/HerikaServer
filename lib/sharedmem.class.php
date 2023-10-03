<?php

class CacheManager {
    private $cacheFilePath;

    public function __construct() {
        $this->cacheFilePath = __DIR__ . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.".cache.txt"; // Adjust the cache file path as per your needs
    }

    public function save_cache($data) {
        // Serialize the data
        $serializedData = serialize($data);

        // Write the serialized data to the cache file
        if (file_put_contents($this->cacheFilePath, $serializedData) !== false) {
            // Set the modification timestamp as the expiration time
            $timeout = time() + (9 * 60); // 9 minutes
            @touch($this->cacheFilePath, $timeout);

            return true;
        } else {
            return false;
        }
    }

    public function get_cache() {
        if ($this->is_cache_expired()) {
            return false;
        }

        // Read the cached data from the file
        $serializedData = file_get_contents($this->cacheFilePath);

        if ($serializedData !== false) {
            // Unserialize the data
            $data = unserialize($serializedData);

            return $data;
        }

        return false;
    }

    private function is_cache_expired() {
        if (!file_exists($this->cacheFilePath)) {
            return true;
        }

        // Get the current time and the file's modification timestamp
        $currentTime = time();
        $fileModifiedTime = filemtime($this->cacheFilePath);

        // Check if the file's modification timestamp is greater than the current time
        return $fileModifiedTime < $currentTime;
    }
}


?>
