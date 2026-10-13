<?php
namespace lib;

/** Publish exactly the snapshot that was verified, never a mutable cloud-side copy. */
class VerifiedUpload
{
    /**
     * Whether a checksum reported by a storage is exactly this MD5. Storages hand it back as a
     * quoted or bare hex ETag (any case) or as a base64 Content-MD5; anything else (multipart
     * ETags such as "abc-3", empty values) is not a match.
     */
    public static function sameMd5($reported, $hash)
    {
        $reported = trim((string)$reported, " \t\r\n\"");
        $hash = strtolower((string)$hash);
        if (!preg_match('/^[a-f0-9]{32}$/D', $hash) || $reported === '') return false;
        if (preg_match('/^[a-f0-9]{32}$/iD', $reported)) return hash_equals($hash, strtolower($reported));
        $raw = base64_decode($reported, true);
        return $raw !== false && strlen($raw) === 16 && hash_equals($hash, bin2hex($raw));
    }

    public static function publish($storage, array $state)
    {
        $key = isset($state['staging_key']) ? $state['staging_key'] : '';
        $hash = isset($state['hash']) ? $state['hash'] : '';
        $size = isset($state['size']) ? (int)$state['size'] : 0;
        if (!preg_match('#^pending/[a-f0-9]{48}$#D', $key)
            || !preg_match('/^[a-f0-9]{32}$/iD', $hash) || $size < 1
            || !method_exists($storage, 'downloadTo')) return false;

        // A storage that can prove the staged object's content on its own side publishes it with a
        // server-side copy (S3: publishStaged), so a browser direct upload is not pulled through this
        // server and pushed back again. Anything short of a positive match falls through to the
        // download-and-compare path below, which stays the authority on what gets rejected.
        if (method_exists($storage, 'publishStaged')) {
            try {
                if ($storage->publishStaged($key, $hash, $size, \minetype($state['ext'])) === true) {
                    try { $storage->delete($key); } catch (\Throwable $ignored) {}
                    return true;
                }
            } catch (\Throwable $e) {}
        }

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
