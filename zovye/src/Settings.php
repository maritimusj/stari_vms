<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\Contract\ISettings;
use function unserialize;

class Settings implements ISettings
{
    private $app;
    private $use_cache;
    private $tb_name;
    private $title;

    public function __construct(weApp $app, $classname = null, $title = null, $use_cache = false)
    {
        $this->use_cache = (bool)$use_cache;
        if ($this->use_cache) {
            We7::load()->func('cache');
        }

        $this->app = $app;

        $app_name = APP_NAME;

        if (empty($classname)) {
            $classname = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
        }

        $this->title = $title ?: 'settings';
        $this->tb_name = strtolower("{$app_name}_{$classname}_{$this->title}");

        if (DEBUG) {
            self::createTable($this->getTableName());
        }
    }

    protected static function createTable($tb_name): bool
    {
        $tb_name = strtolower($tb_name);

        if (!We7::pdo_tableexists($tb_name)) {
            $we7tb_name = We7::tablename($tb_name);

            $sql = <<<CODE
CREATE TABLE IF NOT EXISTS {$we7tb_name} (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `uniacid` int(11) DEFAULT NULL,
    `name` varchar(128) NOT NULL,
    `data` text,
    `createtime` int(11) DEFAULT NULL,
    `locked_uid` VARCHAR( 64 ) NULL DEFAULT  'n/a',
    PRIMARY KEY (`id`),
    KEY `name` (`name`(16),`uniacid`),
    KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CODE;
            We7::pdo_run($sql);
        }

        return We7::pdo_tableexists($tb_name) != false;
    }

    protected function getTableName(): string
    {
        return $this->tb_name;
    }

    public function cleanCache()
    {
        We7::cache_clean($this->cacheKey('')); //?
    }

    protected function cacheKey($name): string
    {
        return APP_NAME . ":settings:" . We7::uniacid() . ":{$this->title}:{$name}";
    }

    /**
     * 是否使用缓存
     * @return bool
     */
    public function useCache(): bool
    {
        return $this->use_cache;
    }

    /**
     * 同步数据库数据到cache
     * @param string|array $key
     */
    public function sync($key)
    {
        if ($key && $this->use_cache) {
            $keys = is_array($key) ? $key : [$key];
            foreach ($keys as $name) {
                $res = We7::pdo_get($this->getTableName(), ['uniacid' => We7::uniacid(), 'name' => $name]);

                if ($res) {
                    $data = unserialize($res['data']);
                    We7::cache_write($this->cacheKey($name), $data);
                }
            }
        }
    }

    /**
     * 设置并保存一个值到指定名称下
     * @param $key
     * @param $val
     * @return bool
     */
    public function set($key, $val): bool
    {
        if ($key) {
            $data = serialize($val);
            $id = $this->name2id($key);

            if ($id) {
                $res = We7::pdo_update($this->getTableName(), ['data' => $data], ['id' => $id]);
            } else {
                $res = We7::pdo_insert(
                    $this->getTableName(),
                    [
                        'uniacid' => We7::uniacid(),
                        'name' => (string)$key,
                        'data' => $data,
                        'createtime' => time(),
                    ]
                );
            }

            if ($res !== false && $this->use_cache) {
                We7::cache_write($this->cacheKey($key), $val);
            }

            return $res !== false;
        }

        return false;
    }

    protected function name2id($key): int
    {
        return (int)We7::pdo_getcolumn(
            $this->getTableName(),
            [
                'uniacid' => We7::uniacid(),
                'name' => (string)$key,
            ],
            'id'
        );
    }

    /**
     * 获取并删除指定键名称的值
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public function pop($key, $default = null)
    {
        $val = $this->get($key, $default);
        $this->remove($key);

        return $val;
    }

    /**
     * 获取指定键名称的值
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $ret = [];
        $keys = is_array($key) ? $key : [$key];
        

        foreach ($keys as $entry) {
            if ($this->use_cache) {
                $val = We7::cache_read($this->cacheKey($entry));
                if (!is_error($val) && $val) {
                    $ret[$entry] = $val;
                    continue;
                }
            }

            $res = We7::pdo_get($this->getTableName(), ['uniacid' => We7::uniacid(), 'name' => $entry]);

            if ($res) {
                $ret[$entry] = unserialize($res['data']);
                if ($this->use_cache) {
                    We7::cache_write($this->cacheKey($entry), $ret[$entry]);
                }
            }
        }

        return is_array($key) ? $ret : ($ret[$key] ?? $default);
    }

    /**
     * 删除指定键值
     * @param mixed $key
     * @return bool
     */
    public function remove($key): bool
    {
        $keys = is_array($key) ? $key : [$key];
        foreach ($keys as $key) {
            if (false !== We7::pdo_delete(
                    $this->getTableName(),
                    [
                        'uniacid' => We7::uniacid(),
                        'name' => (string)$key,
                    ]
                )) {
                if ($this->use_cache) {
                    We7::cache_delete($this->cacheKey($key));
                }
            }
        }

        return true;
    }

    /**
     * 判断指定键名称是否存在
     * @param mixed $key
     * @return bool
     */
    public function has($key): bool
    {
        if ($this->use_cache && We7::cache_read($this->cacheKey($key))) {
            return true;
        }

        return $this->name2id($key) > 0;
    }
}
