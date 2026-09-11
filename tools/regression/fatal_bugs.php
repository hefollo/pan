<?php
// Offline regression: production functions, fake database/storage, no application bootstrap.
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__, 2).'/includes/functions.php';
require dirname(__DIR__, 2).'/includes/lib/StorHelper.php';
require dirname(__DIR__, 2).'/includes/lib/VerifiedUpload.php';
require dirname(__DIR__, 2).'/includes/lib/IStorage.php';
require dirname(__DIR__, 2).'/includes/lib/Storage/S3.php';
require dirname(__DIR__, 2).'/includes/lib/Storage/Oss.php';
require dirname(__DIR__, 2).'/includes/lib/Storage/Qcloud.php';

$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
    echo "PASS $message\n";
}

class MemoryStore {
    public $objects = [];
    public $deleted = [];
    public $failRead = false;
    public $failSave = false;
    public $afterRead;
    public function getinfo($key) { return isset($this->objects[$key]) ? ['length'=>strlen($this->objects[$key])] : false; }
    public function downloadTo($key, $path) {
        if ($this->failRead) return false;
        file_put_contents($path, $this->objects[$key]);
        if ($this->afterRead) ($this->afterRead)($this, $key);
        return true;
    }
    public function savefile($key, $path, $type) {
        if ($this->failSave) return false;
        $this->objects[$key] = file_get_contents($path);
        return true;
    }
    public function delete($key) { $this->deleted[]=$key; unset($this->objects[$key]); return true; }
}
class MemoryDB {
    public $count = 0;
    public $orders;
    public $user;
    public $failGrant = false;
    public $failOrder = false;
    public $failCommit = false;
    public $failBegin = false;
    public $snapshot;
    public $grants = 0;
    public $lockedUser = false;
    public $failLock = false;
    public $existing = false;
    public function __construct() {
        $this->orders = [1=>['id'=>1,'uid'=>10,'status'=>0,'days'=>3,'upload_limit'=>2,'upload_size'=>2,'limit_mode'=>'add']];
        $this->user = ['uid'=>10,'level'=>0,'expiretime'=>date('Y-m-d H:i:s',time()+86400), 'upload_limit'=>5,'upload_size'=>1,'bonus_limit'=>0];
    }
    public function getColumn($sql, $params) {
        if(strpos($sql,'CONCAT')!==false)return 'pan:blob:'.$params[':hash'];
        if(strpos($sql,'GET_LOCK')!==false)return $this->failLock ? 0 : 1;
        if(strpos($sql,'RELEASE_LOCK')!==false)return 1;
        check(strpos($sql, 'COUNT(*)') !== false, 'orphan check uses explicit count');
        return $this->count;
    }
    public function beginTransaction() {
        if ($this->failBegin) return false;
        $this->snapshot=[$this->orders,$this->user]; return true;
    }
    public function commit() { if ($this->failCommit) return false; $this->snapshot=null; return true; }
    public function rollBack() { list($this->orders,$this->user)=$this->snapshot; $this->snapshot=null; return true; }
    public function getRow($sql, $params) {
        if(strpos($sql,'pre_file')!==false)return $this->existing;
        if (strpos($sql,'pre_order')!==false) {
            check(strpos($sql,'FOR UPDATE')!==false,'order row locked');
            return isset($this->orders[$params[':id']]) ? $this->orders[$params[':id']] : false;
        }
        $this->lockedUser=strpos($sql,'FOR UPDATE')!==false;
        return $this->user;
    }
    public function update($table, $data, $where) {
        $this->grants++;
        if ($this->failGrant) return false;
        $this->user=array_merge($this->user,$data); return 1;
    }
    public function query($sql, $params) {
        if ($this->failOrder) return false;
        $this->orders[$params[':id']]['status']=1;
        return new class { public function rowCount() { return 1; } };
    }
}

$conf=['storage'=>'local','upload_limit'=>5,'upload_size'=>1];
$stor = new MemoryStore;
$DB = new MemoryDB;
foreach ([false,null,1,'2'] as $count) {
    $DB->count=$count;
    delete_file_blob_if_orphaned(md5('shared'),1,'local');
    check(!$stor->deleted,'failed/unknown/nonzero reference count preserves blob');
}
foreach ([0,'0'] as $count) {
    $DB->count=$count;
    check(delete_file_blob_if_orphaned(md5('unused'),1,'local')===true,'confirmed zero permits cleanup');
}
$DB->failLock=true;
$before=count($stor->deleted);
check(delete_file_blob_if_orphaned(md5('lock-timeout'),1,'local')===false,'lock timeout refuses cleanup');
check(count($stor->deleted)===$before,'lock failure does not delete');
$DB->failLock=false;
$old=['id'=>42,'hash'=>md5('stale'),'storage'=>'local'];
check(create_file_record_from_existing($old,'a.txt',1,'txt',0,null,10,'127.0.0.1')===false,'deleted source cannot create dangling instant-upload alias');
$DB->existing=$old; $DB->existing['storage']='s3';
check(create_file_record_from_existing($old,'a.txt',1,'txt',0,null,10,'127.0.0.1')===false,'moved source cannot create alias on stale storage');

// Model persisted rows so cleanup observes the state after DELETE, including shared references.
class DeleteMemoryDB extends MemoryDB {
    public $rows = [];
    public $deleteResult = 1;
    public $countFailure = false;
    public $countThrows = false;
    public $events = [];
    public function getRow($sql, $params) { return $this->rows[$params[':id']] ?? false; }
    public function query($sql, $params) {
        $this->events[] = 'delete';
        if ($this->deleteResult === false) return false;
        if ($this->deleteResult === 1) unset($this->rows[$params[':id']]);
        return new class($this->deleteResult) {
            private $affected;
            public function __construct($affected) { $this->affected = $affected; }
            public function rowCount() { return $this->affected; }
        };
    }
    public function getColumn($sql, $params) {
        if (strpos($sql, 'COUNT(*)') === false) return parent::getColumn($sql, $params);
        $this->events[] = 'count';
        check(!isset($params[':id']), 'post-delete count excludes no surviving record');
        if ($this->countThrows) throw new RuntimeException('simulated post-delete interruption');
        if ($this->countFailure) return false;
        return count(array_filter($this->rows, function($row) use ($params) {
            return $row['hash'] === $params[':hash'] && ($row['storage'] === $params[':stor'] || $row['storage'] === '');
        }));
    }
}
class DeleteMemoryStore extends MemoryStore {
    public $failure = '';
    public function delete($key) {
        if ($this->failure === 'throw') throw new RuntimeException('simulated storage failure');
        if ($this->failure === 'false') return false;
        return parent::delete($key);
    }
}
foreach (['sql-false','sql-zero','shared','last','legacy-storage','count-failure','count-throws','storage-false','storage-throws','stale-hash','stale-storage','stale-owner','stale-block','missing','lock-failure'] as $scenario) {
    $DB = new DeleteMemoryDB;
    $stor = new DeleteMemoryStore;
    $hash = md5('delete-'.$scenario);
    $row = ['id'=>91, 'hash'=>$hash, 'storage'=>'local', 'uid'=>10, 'block'=>0];
    if ($scenario === 'legacy-storage') $row['storage'] = '';
    $DB->rows[91] = $row;
    $stor->objects[$hash] = 'original';
    if ($scenario === 'sql-false') $DB->deleteResult = false;
    if ($scenario === 'sql-zero') $DB->deleteResult = 0;
    if ($scenario === 'shared') $DB->rows[92] = array_merge($row, ['id'=>92]);
    if ($scenario === 'count-failure') $DB->countFailure = true;
    if ($scenario === 'count-throws') $DB->countThrows = true;
    if ($scenario === 'storage-false') $stor->failure = 'false';
    if ($scenario === 'storage-throws') $stor->failure = 'throw';
    if ($scenario === 'stale-hash') $DB->rows[91]['hash'] = md5('replacement');
    if ($scenario === 'stale-storage') $DB->rows[91]['storage'] = 's3';
    if ($scenario === 'stale-owner') $DB->rows[91]['uid'] = 11;
    if ($scenario === 'stale-block') $DB->rows[91]['block'] = 1;
    if ($scenario === 'missing') unset($DB->rows[91]);
    if ($scenario === 'lock-failure') $DB->failLock = true;
    $rowsBefore = $DB->rows;
    $success = in_array($scenario, ['shared','last','legacy-storage','count-failure','count-throws','storage-false','storage-throws'], true);
    check(delete_file_record($row) === $success, "$scenario reports actual row deletion");
    check($success ? !isset($DB->rows[91]) : $DB->rows === $rowsBefore, "$scenario preserves expected database state");
    check(isset($stor->objects[$hash]) === !in_array($scenario, ['last','legacy-storage'], true), "$scenario preserves referenced or uncertain blob");
    check($success ? $DB->events === ['delete','count'] : !in_array('count', $DB->events, true), "$scenario cleanup only follows successful delete");
    if ($success && isset($stor->objects[$hash]) && $scenario !== 'shared') {
        $DB->countFailure = false; $DB->countThrows = false; $stor->failure = '';
        check(delete_file_blob_if_orphaned($hash, null, 'local') === true, "$scenario orphan cleanup remains retryable");
        check(!isset($stor->objects[$hash]), "$scenario retry removes orphan");
    }
}

foreach (['failGrant','failOrder','failCommit','failBegin'] as $failure) {
    $DB=new MemoryDB;
    $original=$DB->user;
    $DB->$failure=true;
    check(finish_order($DB->orders[1],'test-payment')===false,"$failure propagates failure");
    check($DB->orders[1]['status']===0 && $DB->user===$original,"$failure preserves order and entitlement");
    $DB->$failure=false;
    check(finish_order($DB->orders[1],'test-payment')===true,"$failure can retry");
    check($DB->lockedUser,'entitlement read locks user');
    $after=$DB->user; $grants=$DB->grants;
    check(finish_order(['id'=>1,'uid'=>999],'test-payment')===true,'duplicate callback succeeds using persisted order');
    check($DB->user===$after && $DB->grants===$grants,'duplicate callback never grants twice');
}

$content='verified original';
$state=['staging_key'=>'pending/'.str_repeat('a',48),'hash'=>md5($content),'size'=>strlen($content),'ext'=>'txt'];
foreach (['valid','wrong-size','wrong-hash','read-failure','save-failure','race'] as $scenario) {
    $store = new MemoryStore;
    $store->objects[$state['staging_key']]=$content;
    if ($scenario==='wrong-size') $store->objects[$state['staging_key']].='extra';
    if ($scenario==='wrong-hash') $store->objects[$state['staging_key']]=str_repeat('x',strlen($content));
    $store->failRead=$scenario==='read-failure';
    $store->failSave=$scenario==='save-failure';
    if ($scenario==='race') $store->afterRead=function($s,$key) { $s->objects[$key]='changed after snapshot'; };
    $ok=\lib\VerifiedUpload::publish($store,$state);
    $expected=in_array($scenario,['valid','race'],true);
    check($ok===$expected,"$scenario publish result");
    if ($expected) {
        $store->objects[$state['staging_key']]='replay after completion';
        check($store->objects[$state['hash']]===$content,"$scenario replay leaves verified final content unchanged");
    } else check(!isset($store->objects[$state['hash']]),"$scenario never publishes invalid content");
}
$legacy=$state; unset($legacy['staging_key']);
check(!\lib\VerifiedUpload::publish(new MemoryStore,$legacy),'legacy final-key state rejected');

// Production policy builders, with fake signing configuration and no cloud client construction.
$pending='pending/'.str_repeat('b',48);
$config=['accessKey'=>'fake','secretKey'=>'fake','endpoint'=>'https://storage.invalid','bucket'=>'test','region'=>'test','prefix'=>'file/'];
$s3=new \lib\Storage\S3($config);
$policies=[$s3->getUploadParam($pending,'a.txt',17)];
foreach (['Oss','Qcloud'] as $driver) {
    $ref=new ReflectionClass('lib\\Storage\\'.$driver);
    $instance=$ref->newInstanceWithoutConstructor();
    foreach (['bucket'=>'test','config'=>['endpoint'=>'storage.invalid','accessKeyId'=>'fake','accessKeySecret'=>'fake','region'=>'test','secretId'=>'fake','secretKey'=>'fake']] as $property=>$value) {
        $p=$ref->getProperty($property); $p->setAccessible(true); $p->setValue($instance,$value);
    }
    $policies[]=$instance->getUploadParam($pending,'a.txt',17);
}
foreach($policies as $param) {
    $policy=json_decode(base64_decode($param['post']['policy']),true);
    check($param['post']['key']==='file/'.$pending,'policy writes only pending key');
    check(in_array(['eq','$key','file/'.$pending],$policy['conditions'],true),'policy enforces exact pending key');
    check(in_array(['content-length-range',1,17],$policy['conditions'],true),'policy limits temporary object size');
}
echo "OK $checks checks\n";
