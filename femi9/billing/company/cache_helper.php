<?php
/**
 * Report Cache Helper
 * Save this as: cache_helper.php in your project root
 */

class ReportCache {
    private $cache_dir;
    private $cache_duration = 300; // 5 minutes
    
    public function __construct($cache_duration = 300) {
        // Cache directory - make sure this path is writable!
        $this->cache_dir = __DIR__ . '/cache/report/';
        $this->cache_duration = $cache_duration;
        
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    public function get($key) {
        $cache_file = $this->cache_dir . md5($key) . '.cache';
        
        if (file_exists($cache_file)) {
            $cache_data = @unserialize(file_get_contents($cache_file));
            
            if ($cache_data && isset($cache_data['timestamp'])) {
                // Check if cache is still valid
                if (time() - $cache_data['timestamp'] < $this->cache_duration) {
                    return $cache_data['data'];
                }
                // Delete expired cache
                @unlink($cache_file);
            }
        }
        
        return null;
    }
    
    public function set($key, $data) {
        $cache_file = $this->cache_dir . md5($key) . '.cache';
        $cache_data = [
            'timestamp' => time(),
            'data' => $data
        ];
        
        return @file_put_contents($cache_file, serialize($cache_data));
    }
    
    public function clear($key = null) {
        if ($key) {
            $cache_file = $this->cache_dir . md5($key) . '.cache';
            if (file_exists($cache_file)) {
                return @unlink($cache_file);
            }
        } else {
            // Clear all cache files
            $files = glob($this->cache_dir . '*.cache');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            return true;
        }
        return false;
    }
    
    public function clearAll() {
        return $this->clear();
    }
}
?>