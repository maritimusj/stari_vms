<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

class LogObj
{
    private $tb_name;

    public function __construct($name)
    {
        $app_name = APP_NAME;
        $this->tb_name = strtolower("{$app_name}_{$name}_logs");

        static::createDbTable($this->getTableName());
    }

    protected static function createDbTable($tb_name): bool
    {
        if (!We7::pdo_tableexists($tb_name)) {
            $we7tb_name = We7::tablename($tb_name);
            $sql = <<<SQL_STATEMENT
CREATE TABLE IF NOT EXISTS {$we7tb_name} (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `uniacid` int(11) NULL,
    `level` tinyint(4) NOT NULL,
    `title` varchar(255) DEFAULT NULL,
    `data` text,
    `createtime` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `title` (`title`(16)),
    KEY `createtime` (`createtime`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL_STATEMENT;
            We7::pdo_run($sql);
        }

        return We7::pdo_tableexists($tb_name) != false;
    }

    public function getTableName(): string
    {
        return $this->tb_name;
    }

    public function create($level, $title, $data): bool
    {
        $res = We7::pdo_insert(
            $this->getTableName(),
            [
                'uniacid' => We7::uniacid(),
                'level' => intval($level),
                'title' => strval($title),
                'data' => serialize($data),
                'createtime' => time(),
            ]
        );

        return false !== $res && !is_error($res);
    }
}
