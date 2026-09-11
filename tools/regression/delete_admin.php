<?php
// Execute only the real admin batch-delete branch with memory data; never bootstrap the site.
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__, 2).'/includes/functions.php';
require dirname(__DIR__, 2).'/includes/lib/StorHelper.php';
$scenario = $argv[1] ?? 'mixed';
if (!in_array($scenario, ['mixed','all-failed','all-success'], true)) exit(2);
$conf = ['storage'=>'local'];
class BatchDeleteDB {
    public $rows;
    public function __construct() {
        foreach ([1,2] as $id) $this->rows[$id] = ['id'=>$id,'hash'=>md5('batch-'.$id),'storage'=>'local','uid'=>10,'block'=>0];
    }
    public function getRow($sql, $params) { return $this->rows[$params[':id']] ?? false; }
    public function getColumn($sql, $params) {
        if (strpos($sql, 'CONCAT') !== false) return 'pan:blob:'.$params[':hash'];
        if (strpos($sql, 'GET_LOCK') !== false || strpos($sql, 'RELEASE_LOCK') !== false) return 1;
        return count(array_filter($this->rows, function($row) use ($params) { return $row['hash'] === $params[':hash']; }));
    }
    public function query($sql, $params) {
        if ($params[':id'] === 2) return false;
        unset($this->rows[$params[':id']]);
        return new class { public function rowCount() { return 1; } };
    }
}
class BatchDeleteStore {
    public $deleted = [];
    public function delete($hash) { $this->deleted[] = $hash; return true; }
}
$DB = new BatchDeleteDB;
$stor = new BatchDeleteStore;
$_POST = ['status'=>0, 'checkbox'=>$scenario === 'mixed' ? [1,2,999,0] : ($scenario === 'all-failed' ? [2,999,0] : [1])];
$expectedOk = $scenario === 'all-failed' ? 0 : 1;
$expectedFail = $scenario === 'all-success' ? 0 : 3;
ob_start();
register_shutdown_function(function() use ($expectedOk, $expectedFail, $scenario) {
    global $DB, $stor;
    $result = json_decode(ob_get_clean(), true);
    if (!$result || $result['ok'] !== $expectedOk || $result['fail'] !== $expectedFail
        || $result['code'] !== ($expectedOk ? 0 : -1)
        || !isset($DB->rows[2]) || count($stor->deleted) !== $expectedOk
        || in_array(md5('batch-2'), $stor->deleted, true)) {
        fwrite(STDERR, "FAIL admin batch $scenario\n");
        exit(1);
    }
    echo "PASS admin batch $scenario: ok=$expectedOk fail=$expectedFail\n";
});
$source = file_get_contents(dirname(__DIR__, 2).'/admin/ajax_file.php');
$start = strpos($source, "case 'operation':");
$end = strpos($source, "case 'getFileInfo':", $start);
if ($start === false || $end === false) throw new RuntimeException('Admin branch not found');
eval('switch ("operation") {'.substr($source, $start, $end - $start).'}');
