<?php
namespace lib;

/** Publish exactly the snapshot that was verified, never a mutable cloud-side copy. */
class VerifiedUpload
{
    public static function publish($storage, array $state)
    {
        $key = isset($state['staging_key']) ? $state['staging_key'] : '';
        $hash = isset($state['hash']) ? $state['hash'] : '';
        $size = isset($state['size']) ? (int)$state['size'] : 0;
        if (!preg_match('#^pending/[a-f0-9]{48}$#D', $key)
            || !preg_match('/^[a-f0-9]{32}$/iD', $hash) || $size < 1
            || !method_exists($storage, 'downloadTo')) return false;

        $tmp = tempnam(sys_get_temp_dir(), 'verified_');
        if ($tmp === false) return false;
        try {
            $info = $storage->getinfo($key);
            if (!$info || !isset($info['length']) || (int)$info['length'] !== $size) return false;
            if (!$storage->downloadTo($key, $tmp)) return false;
            clearstatcache(true, $tmp);
            if (filesize($tmp) !== $size || !hash_equals(strtolower($hash), md5_file($tmp))) return false;
            // The browser only has permission to write pending/<random>, never this key.
            if (!$storage->savefile($hash, $tmp, \minetype($state['ext']))) return false;
            return true;
        } catch (\Throwable $e) {
            return false;
        } finally {
            if (is_file($tmp)) @unlink($tmp);
            // Best effort: a valid old policy may recreate pending data, but cannot affect published data.
            try { $storage->delete($key); } catch (\Throwable $ignored) {}
        }
    }
}
