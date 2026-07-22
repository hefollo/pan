<?php
require_once dirname(__DIR__) . '/includes/lib/IStorage.php';
require_once dirname(__DIR__) . '/includes/lib/Storage/S3.php';

use lib\Storage\S3;

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . $expected . "\nActual:   " . $actual . "\n");
        exit(1);
    }
}

function assertTrueValue($value, $message)
{
    if (!$value) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function callPrivate($object, $method, array $arguments)
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($object, $arguments);
}

$baseConfig = [
    'accessKey' => 'test-access-key',
    'secretKey' => 'test-secret-key',
    'endpoint' => 'https://s3.example.com',
    'region' => 'us-east-1',
    'bucket' => 'my-bucket',
    'pathStyle' => false,
    'prefix' => 'file/'
];

$virtualHost = new S3($baseConfig);
assertSameValue(
    'https://my-bucket.s3.example.com/file/folder/a%20b.txt',
    callPrivate($virtualHost, 'objectUrl', ['file/folder/a b.txt']),
    'Virtual-host S3 URL is incorrect.'
);

$pathConfig = $baseConfig;
$pathConfig['endpoint'] = 'http://localhost:9000';
$pathConfig['pathStyle'] = true;
$pathStyle = new S3($pathConfig);
assertSameValue(
    'http://localhost:9000/my-bucket/file/test.txt',
    callPrivate($pathStyle, 'objectUrl', ['file/test.txt']),
    'Path-style S3 URL is incorrect.'
);

$upload = $pathStyle->getUploadParam('abc123', 'demo.txt', 1024);
assertSameValue('http://localhost:9000/my-bucket/', $upload['url'], 'Direct-upload URL is incorrect.');
assertSameValue('file/abc123', $upload['post']['key'], 'Direct-upload object key is incorrect.');
assertSameValue('AWS4-HMAC-SHA256', $upload['post']['x-amz-algorithm'], 'Direct-upload algorithm is incorrect.');
assertTrueValue((bool) preg_match('/^[a-f0-9]{64}$/', $upload['post']['x-amz-signature']), 'Direct-upload signature is malformed.');
$policy = json_decode(base64_decode($upload['post']['policy']), true);
assertTrueValue(in_array(['success_action_status' => '200'], $policy['conditions'], true), 'Direct-upload status field is not covered by the policy.');

$conf = ['downfile_domain' => '', 'downfile_protocol' => 1];
$downloadUrl = $pathStyle->getDownUrl('abc123', 'demo file.txt');
assertTrueValue(strpos($downloadUrl, 'X-Amz-Algorithm=AWS4-HMAC-SHA256') !== false, 'Presigned URL has no Signature V4 algorithm.');
assertTrueValue(strpos($downloadUrl, 'X-Amz-Expires=604800') !== false, 'Presigned URL expiry exceeds or differs from the S3 maximum.');
assertTrueValue((bool) preg_match('/X-Amz-Signature=[a-f0-9]{64}/', $downloadUrl), 'Presigned URL signature is malformed.');

echo "S3 storage tests passed.\n";
