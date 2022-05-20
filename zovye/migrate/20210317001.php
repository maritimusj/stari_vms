<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_migration')) {
    $sql = <<<SQL
CREATE TABLE `ims_zy_saas_migration` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` int(11) NOT NULL DEFAULT '0',
    `name` VARCHAR(64) NOT NULL , 
    `filename` VARCHAR(256) NOT NULL , 
    `result` TINYINT NOT NULL  DEFAULT '0', 
    `error` TEXT, 
    `begin` INT NOT NULL , 
    `end` INT NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    UNIQUE (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);

    $history = app()->get('migrate', []);
    ksort($history);

    foreach ($history as $name => $time) {
        Migrate::create([
            'name' => $name,
            'filename' => 'unknown',
        ]);
    }

    app()->remove('migrate');
}