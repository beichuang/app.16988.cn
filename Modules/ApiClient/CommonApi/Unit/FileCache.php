<?php
namespace ApiClient\CommonApi\Unit;

/**
 * FileCache
 *
 * http://github.com/inouet/file-cache/
 *
 * A simple PHP class for caching data in the filesystem.
 *
 * License
 * This software is released under the MIT License, see LICENSE.txt.
 *
 * @package FileCache
 * @author Taiji Inoue <inudog@gmail.com>
 */
class FileCache 
{

    /**
     * The root cache directory.
     *
     * @var string
     */
    protected $cache_dir = '';

    /**
     * 缓存时间（秒）
     *
     * @var int
     */
    protected $lifetime = 3600;

    /**
     * Creates a FileCache object
     *
     * @param array $options            
     */
    public function __construct($cache_dir='tmp/cache',$lifetime=31536000)
    {
        $this->cache_dir =$cache_dir;
        $this->lifetime=$lifetime;
    }

    /**
     * Fetches an entry from the cache.
     *
     * @param string $id            
     */
    public function get($id)
    {
        $file_name = $this->getFileName($id);
        
        if (! is_file($file_name) || ! is_readable($file_name)) {
            return false;
        }
        
        $lines = file($file_name);
        $lifetime = array_shift($lines);
        $lifetime = (int) trim($lifetime);
        
        if ($lifetime !== 0 && $lifetime < time()) {
            @unlink($file_name);
            return false;
        }
        $serialized = join('', $lines);
        $data = unserialize($serialized);
        return $data;
    }

    /**
     * Puts data into the cache.
     *
     * @param string $id            
     * @param mixed $data            
     * @param int $lifetime            
     *
     * @return bool
     */
    public function save($id, $data, $lifetime = false)
    {
        if ($lifetime === false) {
            $lifetime = $this->lifetime;
        }
        $dir = $this->getDirectory($id);
        $this->createFolder($dir);
        $file_name = $this->getFileName($id);
        $lifetime = time() + $lifetime;
        $serialized = serialize($data);
        $result = file_put_contents($file_name, $lifetime . PHP_EOL . $serialized);
        chmod($file_name, 0777);
        if ($result === false) {
            return false;
        }
        return true;
    }
    
    private function createFolder($path){
        if (!file_exists($path))
        {
            $this->createFolder(dirname($path));
            mkdir($path,0777);
            @chmod($path, 0777);
        }
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $id            
     *
     * @return bool
     */
    public function delete($id)
    {
        $file_name = $this->getFileName($id);
        return unlink($file_name);
    }
    
    // ------------------------------------------------
    // PRIVATE METHODS
    // ------------------------------------------------
    
    /**
     * Fetches a directory to store the cache data
     *
     * @param string $id            
     *
     * @return string
     */
    protected function getDirectory($id)
    {
        $hash = sha1($id, false);
        $dirs = array(
            $this->getCacheDirectory(),
            substr($hash, 0, 2),
            substr($hash, 2, 2)
        );
        return join(DIRECTORY_SEPARATOR, $dirs);
    }

    /**
     * Fetches a base directory to store the cache data
     *
     * @return string
     */
    protected function getCacheDirectory()
    {
        return $this->cache_dir;
    }

    /**
     * Fetches a file path of the cache data
     *
     * @param string $id            
     *
     * @return string
     */
    protected function getFileName($id)
    {
        $directory = $this->getDirectory($id);
        $hash = sha1($id, false);
        $file = $directory . DIRECTORY_SEPARATOR . $hash . '.cache';
        return $file;
    }
}
