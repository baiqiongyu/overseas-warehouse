<?php
// sql/run.php —— 极简 SQL 迁移执行器
// 用法: php sql/run.php sql/001_init_auth.sql
// 读取 SQL 文件（支持 -- 注释），按分号拆分逐条执行，并记录已执行的版本到 hw_migration

$config = [
    'host' => '127.0.0.1',
    'port' => 4406,
    'dbname' => 'hw_system',
    'user' => 'root',
    'pass' => getenv('HW_DB_PASS') ?: 'l19285w',
];

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    exit("用法: php sql/run.php <sql文件>\n");
}

// 迁移记录表（首次自动创建）
$pdo = new PDO(
    "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4",
    $config['user'],
    $config['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("CREATE TABLE IF NOT EXISTS hw_migration (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    file VARCHAR(128) NOT NULL,
    create_time INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_file (file)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='迁移执行记录'");

// 已执行过则跳过（允许传 --force 强制重复执行）
$force = in_array('--force', $argv, true);
$exist = $pdo->prepare('SELECT id FROM hw_migration WHERE file = ?');
$exist->execute([$file]);
if (!$force && $exist->fetch()) {
    echo "[SKIP] {$file} 已执行过（--force 可强制重跑）\n";
    exit(0);
}

// 去掉 -- 注释行，按分号拆分
$sql = file_get_contents($file);
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$stmts = array_filter(array_map('trim', explode(';', $sql)), function ($s) {
    return $s !== '';
});

foreach ($stmts as $stmt) {
    try {
        $pdo->exec($stmt);
        echo "[OK] " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 60) . "...\n";
    } catch (PDOException $e) {
        echo "[FAIL] " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 60) . "...\n";
        echo "       " . $e->getMessage() . "\n";
        exit(1);
    }
}

$pdo->prepare('INSERT INTO hw_migration (file, create_time) VALUES (?, ?)')
    ->execute([$file, time()]);
echo "[DONE] {$file}\n";
