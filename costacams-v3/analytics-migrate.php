<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
$pdo=cc_db();
if(!$pdo){echo "ANALYTICS_MIGRATION_BLOCKED db_unavailable\n";exit(1);}
try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_analytics_daily(
        day DATE NOT NULL,
        kind VARCHAR(10) NOT NULL,
        metric_key VARCHAR(120) NOT NULL,
        events BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY(day,kind,metric_key),
        INDEX idx_cc_analytics_kind_day(kind,day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_analytics_visitors(
        day DATE NOT NULL,
        visitor_hash CHAR(64) NOT NULL,
        PRIMARY KEY(day,visitor_hash),
        INDEX idx_cc_visitors_hash_date(visitor_hash,day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach(['cc_analytics_daily','cc_analytics_visitors'] as $name){
        $st=$pdo->query("SHOW TABLES LIKE ".$pdo->quote($name));
        if(!$st||!$st->fetchColumn())throw new RuntimeException('missing '.$name);
    }
    echo "ANALYTICS_MIGRATION_OK tables=2\n";
}catch(Throwable $e){
    error_log('CostaCams analytics schema: '.get_class($e).' '.$e->getMessage());
    echo "ANALYTICS_MIGRATION_BLOCKED ".get_class($e)."\n";exit(1);
}
