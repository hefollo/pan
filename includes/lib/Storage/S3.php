<?php
namespace lib\Storage;

use \lib\IStorage;

/**
 * Lightweight Amazon S3 API compatible storage driver (Signature V4).
 * Works with AWS S3, MinIO, Cloudflare R2, Backblaze B2 and similar services.
 */
class S3 implements IStorage
{
    private $config;
    private $endpoint;
    private $bucket;
    private $region;
    private $accessKey;
    private $secretKey;
    private $pathStyle;
    private $prefix;
    private $errmsg;

    public function __construct($config)
    {
        $this->config = $config;
        $this->endpoint = rtrim($config['endpoint'], '/');
        if (!preg_match('#^https?://#i', $this->endpoint)) {
            $this->endpoint = 'https://' . $this->endpoint;
        }
        $this->bucket = $config['bucket'];
        $this->region = $config['region'];
        $this->accessKey = $config['accessKey'];
        $this->secretKey = $config['secretKey'];
        $this->pathStyle = !empty($config['pathStyle']);
        $this->prefix = trim($config['prefix'], '/');
        if ($this->prefix !== '') {
            $this->prefix .= '/';
        }
    }

    public function getClient()
    {
        return $this;
    }

    public function errmsg()
    {
        return $this->errmsg;
    }

    public function exists($name)
    {
        $response = $this->request('HEAD', $this->objectKey($name));
        return $response !== false && $response['status'] >= 200 && $response['status'] < 300;
    }

    public function get($name)
    {
        $response = $this->request('GET', $this->objectKey($name));
        return $response === false ? false : $response['body'];
    }

    public function downloadTo($name, $path)
    {
        $handle = fopen($path, 'wb');
        if (!$handle) return false;
        try {
            return $this->request('GET', $this->objectKey($name), [], null, $handle) !== false;
        } finally {
            fclose($handle);
        }
    }

    public function downfile($name, $range = false)
    {
        $headers = [];
        if ($range) {
            $headers['range'] = 'bytes=' . intval($range[0]) . '-' . intval($range[1]);
        }
        // Stream the object to the visitor as it arrives. Reading it into memory first
        // breaks on anything larger than PHP's memory limit, and the visitor gets no
        // byte until this server has fetched the whole file.
        return $this->request('GET', $this->objectKey($name), $headers, null, null, true) !== false;
    }

    public function upload($name, $tmpfile, $content_type = null)
    {
        $headers = [];
        if ($content_type) {
            $headers['content-type'] = $content_type;
        }
        $response = $this->request('PUT', $this->objectKey($name), $headers, $tmpfile);
        return $response !== false;
    }

    public function savefile($name, $tmpfile, $content_type = null)
    {
        $result = $this->upload($name, $tmpfile, $content_type);
        if ($result) {
            @unlink($tmpfile);
        }
        return $result;
    }

    public function getinfo($name)
    {
        $response = $this->request('HEAD', $this->objectKey($name));
        if ($response === false) {
            return false;
        }
        return [
            'length' => isset($response['headers']['content-length']) ? intval($response['headers']['content-length']) : 0,
            'content_type' => isset($response['headers']['content-type']) ? $response['headers']['content-type'] : null
        ];
    }

    public function delete($name)
    {
        return $this->request('DELETE', $this->objectKey($name)) !== false;
    }

    /**
     * Publish a browser-uploaded staging object as $hash without routing it through this server.
     *
     * For an object stored in a single PUT/POST, S3 and compatible services report the MD5 of
     * its content as the ETag. The staging object is first copied, on the storage side, to a key
     * the browser has no permission to write (its upload policy names the staging key only), so
     * what gets checked can no longer be swapped. If that snapshot's ETag and length match what
     * the browser declared, it is copied to the final key; the snapshot is removed either way.
     *
     * Returns true only when the object was published this way. false means "not decided here"
     * (the ETag is not a plain MD5, e.g. multipart or KMS-encrypted objects, the values differ,
     * or the service does not support the copy): the caller must fall back to downloading and
     * comparing the content itself.
     */
    public function publishStaged($stagingName, $hash, $size, $content_type = null)
    {
        $hash = strtolower($hash);
        // Cheap early exit: no point copying something that already does not match.
        $staged = $this->request('HEAD', $this->objectKey($stagingName));
        if ($staged === false || !$this->headMatches($staged, $hash, $size)) {
            return false;
        }
        $snapshot = 'pending/' . bin2hex(random_bytes(24));
        if (!$this->copyObject($stagingName, $snapshot)) {
            return false;
        }
        $published = false;
        $head = $this->request('HEAD', $this->objectKey($snapshot));
        if ($head !== false && $this->headMatches($head, $hash, $size)) {
            $published = $this->copyObject($snapshot, $hash, $content_type);
        }
        $this->request('DELETE', $this->objectKey($snapshot));
        return $published;
    }

    private function headMatches($response, $hash, $size)
    {
        $etag = isset($response['headers']['etag']) ? strtolower(trim($response['headers']['etag'], " \t\"")) : '';
        $length = isset($response['headers']['content-length']) ? intval($response['headers']['content-length']) : -1;
        return preg_match('/^[a-f0-9]{32}$/', $etag) === 1 && hash_equals($hash, $etag) && $length === intval($size);
    }

    // Server-side copy inside the bucket; nothing is transferred through this server.
    private function copyObject($from, $to, $content_type = null)
    {
        $headers = [
            'x-amz-copy-source' => '/' . rawurlencode($this->bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $this->objectKey($from))))
        ];
        if ($content_type) {
            // The browser's form upload carries no usable type; give the final object the same one a relayed upload gets.
            $headers['x-amz-metadata-directive'] = 'REPLACE';
            $headers['content-type'] = $content_type;
        }
        $response = $this->request('PUT', $this->objectKey($to), $headers);
        // A failed copy can still answer 200 with an <Error> document in the body.
        return $response !== false && stripos($response['body'], 'CopyObjectResult') !== false;
    }

    public function getUploadParam($name, $filename, $max_file_size = 0)
    {
        $now = time();
        $date = gmdate('Ymd', $now);
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $credential = $this->accessKey . '/' . $date . '/' . $this->region . '/s3/aws4_request';
        $key = $this->objectKey($name);
        $conditions = [
            ['bucket' => $this->bucket],
            ['eq', '$key', $key],
            ['x-amz-algorithm' => 'AWS4-HMAC-SHA256'],
            ['x-amz-credential' => $credential],
            ['x-amz-date' => $amzDate],
            ['success_action_status' => '200']
        ];
        if ($max_file_size > 0) {
            $conditions[] = ['content-length-range', 1, intval($max_file_size)];
        }
        $policy = base64_encode(json_encode([
            'expiration' => gmdate('Y-m-d\TH:i:s.000\Z', $now + 3600),
            'conditions' => $conditions
        ], JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $policy, $this->signingKey($date));
        return [
            'url' => $this->bucketUrl(),
            'post' => [
                'key' => $key,
                'policy' => $policy,
                'x-amz-algorithm' => 'AWS4-HMAC-SHA256',
                'x-amz-credential' => $credential,
                'x-amz-date' => $amzDate,
                'x-amz-signature' => $signature,
                'success_action_status' => '200'
            ]
        ];
    }

    public function getDownUrl($name, $filename, $content_type = null)
    {
        $params = [
            'response-content-disposition' => ($content_type ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($filename),
            'response-content-type' => $content_type ? $content_type : 'application/force-download'
        ];
        // The host is part of Signature V4. Replacing it after signing would
        // invalidate the URL, so S3 downloads always use the configured endpoint.
        return $this->presign('GET', $this->objectKey($name), $params, 604800);
    }

    private function objectKey($name)
    {
        return $this->prefix . ltrim($name, '/');
    }

    private function encodedPath($key)
    {
        $parts = explode('/', $key);
        $parts = array_map('rawurlencode', $parts);
        $path = '/' . implode('/', $parts);
        return $this->pathStyle ? '/' . rawurlencode($this->bucket) . $path : $path;
    }

    private function endpointParts()
    {
        $parts = parse_url($this->endpoint);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $host = $parts['host'];
        if (isset($parts['port'])) {
            $host .= ':' . $parts['port'];
        }
        if (!$this->pathStyle) {
            $host = $this->bucket . '.' . $host;
        }
        $basePath = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        return [$scheme, $host, $basePath];
    }

    private function objectUrl($key)
    {
        list($scheme, $host, $basePath) = $this->endpointParts();
        return $scheme . '://' . $host . $basePath . $this->encodedPath($key);
    }

    private function bucketUrl()
    {
        list($scheme, $host, $basePath) = $this->endpointParts();
        return $scheme . '://' . $host . $basePath . ($this->pathStyle ? '/' . rawurlencode($this->bucket) : '') . '/';
    }

    private function request($method, $key, $headers = [], $file = null, $sink = null, $stream = false)
    {
        if (!function_exists('curl_init')) {
            $this->errmsg = 'S3 storage requires the PHP cURL extension.';
            return false;
        }
        $url = $this->objectUrl($key);
        $parts = parse_url($url);
        $payloadHash = $file ? hash_file('sha256', $file) : hash('sha256', '');
        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);
        $headers['host'] = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $headers['x-amz-content-sha256'] = $payloadHash;
        $headers['x-amz-date'] = $amzDate;
        // Sign only what Signature V4 requires (host, x-amz-*) plus content-type. Anything
        // else, notably Range, is sent but left out of the signature: a reverse proxy or
        // cache in front of the storage may rewrite it, and a signed Range was rejected
        // with SignatureDoesNotMatch on a real self-hosted endpoint, which broke every
        // ranged download (resume, media seeking).
        $signed = [];
        foreach ($headers as $header => $value) {
            $lower = strtolower($header);
            if ($lower === 'host' || $lower === 'content-type' || strpos($lower, 'x-amz-') === 0) {
                $signed[$lower] = $value;
            }
        }
        ksort($signed);
        $canonicalHeaders = '';
        foreach ($signed as $header => $value) {
            $canonicalHeaders .= $header . ':' . trim(preg_replace('/\s+/', ' ', $value)) . "\n";
        }
        $signedHeaders = implode(';', array_keys($signed));
        $canonicalRequest = $method . "\n" . $parts['path'] . "\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
        $scope = $date . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $scope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . hash_hmac('sha256', $stringToSign, $this->signingKey($date));

        $responseHeaders = [];
        $curl = curl_init($url);
        $curlHeaders = [];
        foreach ($headers as $header => $value) {
            $curlHeaders[] = $header . ': ' . $value;
        }
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        // CUSTOMREQUEST only renames the verb: without NOBODY libcurl still waits for the
        // Content-Length bytes a HEAD response never sends, so the call fails on every
        // non-empty object (exists() and getinfo() could never succeed).
        if ($method === 'HEAD') curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if ($sink) curl_setopt($curl, CURLOPT_FILE, $sink);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        $errorBody = '';
        if ($stream) {
            // A rate-limited download of a big file can take hours, so there is no
            // overall time limit; give up only when nothing has moved for 60 seconds.
            curl_setopt($curl, CURLOPT_TIMEOUT, 0);
            curl_setopt($curl, CURLOPT_LOW_SPEED_LIMIT, 1);
            curl_setopt($curl, CURLOPT_LOW_SPEED_TIME, 60);
            curl_setopt($curl, CURLOPT_WRITEFUNCTION, function ($curl, $data) use (&$errorBody) {
                $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if ($code >= 200 && $code < 300) {
                    \download_output_chunk($data);
                } elseif (strlen($errorBody) < 2048) {
                    // An error response is the storage's XML message, not file content:
                    // keep it for errmsg instead of sending it to the visitor.
                    $errorBody .= $data;
                }
                return strlen($data);
            });
        }
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curl, $line) use (&$responseHeaders) {
            $length = strlen($line);
            $pos = strpos($line, ':');
            if ($pos !== false) {
                $responseHeaders[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
            }
            return $length;
        });
        $handle = null;
        if ($file) {
            $handle = fopen($file, 'rb');
            curl_setopt($curl, CURLOPT_UPLOAD, true);
            curl_setopt($curl, CURLOPT_INFILE, $handle);
            curl_setopt($curl, CURLOPT_INFILESIZE, filesize($file));
        } elseif ($method === 'PUT') {
            // A PUT without a file (server-side copy) still has to announce an empty body.
            curl_setopt($curl, CURLOPT_POSTFIELDS, '');
        }
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($handle) {
            fclose($handle);
        }
        if ($body === false || $status < 200 || $status >= 300) {
            $this->errmsg = 'S3 ' . $method . ' failed (' . $status . '): ' . ($error ? $error : ($stream ? $errorBody : $body));
            trigger_error($this->errmsg);
            return false;
        }
        // With a sink or a stream the body has already gone elsewhere and curl_exec() returns true.
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => is_string($body) ? $body : ''];
    }

    private function presign($method, $key, $params, $expires)
    {
        $now = time();
        $date = gmdate('Ymd', $now);
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $scope = $date . '/' . $this->region . '/s3/aws4_request';
        $url = $this->objectUrl($key);
        $parts = parse_url($url);
        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $params['X-Amz-Algorithm'] = 'AWS4-HMAC-SHA256';
        $params['X-Amz-Credential'] = $this->accessKey . '/' . $scope;
        $params['X-Amz-Date'] = $amzDate;
        $params['X-Amz-Expires'] = min(intval($expires), 604800);
        $params['X-Amz-SignedHeaders'] = 'host';
        ksort($params);
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $canonicalRequest = $method . "\n" . $parts['path'] . "\n" . $query . "\nhost:" . $host . "\n\nhost\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($date));
        return $url . '?' . $query . '&X-Amz-Signature=' . $signature;
    }

    private function signingKey($date)
    {
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $regionKey = hash_hmac('sha256', $this->region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    }
}
