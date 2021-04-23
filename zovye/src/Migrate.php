<?php

namespace zovye;

use Exception;
use zovye\base\modelObj;
use zovye\model\migrationModelObj;

class Migrate
{
    public static function execSQL($sql)
    {
        $prefix = Util::config('db.master.tablepre');
        $tb_name = APP_NAME;

        $sql = preg_replace('/ims_/', $prefix, $sql);
        $sql = preg_replace('/zovye_vms/', $tb_name, $sql);

        Util::logToFile('migrate', [
            'sql' => $sql,
            'result' => We7::pdo_query($sql),
        ]);
    }

    public static function all(): array
    {
        static $task = [];
        if (empty($task)) {
            foreach (glob(ZOVYE_CORE_ROOT . 'migrate/*.php') as $filename) {
                $task[basename($filename, '.php')] = $filename;
            }
        }

        return $task;
    }

    public static function getNewTask($debug = true): array
    {
        if (!$debug) {
            return [];
        }
        $history = app()->get('migrate', []);

        if (We7::pdo_tableexists(APP_NAME . '_migration')) {
            $query = self::query(['result' => 0]);
            foreach ($query->findAll() as $entry) {
                $history[$entry->getName()] = $entry->getCreatetime();
            }            
        }

        $task = array_diff_key(self::all(), $history);

        ksort($task);

        return $task;
    }

    public static function detect($auto_redirect = false): bool
    {
        $task = self::getNewTask();
        if (!empty($task)) {
            if ($auto_redirect) {
                if (defined('IN_MOBILE')) {
                    $url = Util::murl('migrate');
                } else {
                    $url = Util::url('migrate', [], false);
                    app()->forceUnlock();
                }

                Util::redirect($url);
                exit(); 
            }
            return true;
        }
        return false;
    }

    public static function step(): bool
    {
        $task = self::getNewTask();
        if (empty($task)) {
            return false;
        }

        $name = array_key_first($task);
        $filename = $task[$name];

        set_time_limit(0);

        $data = [
            'name' => $name,
            'filename' => $filename,
            'begin' => time(),
        ];

        $result = Util::transactionDo(function () use ($name, $filename) {
            try {
                //加载文件
                include_once $filename;     

            } catch (Exception $e) {
                Util::logToFile('migrate', [
                    'name' => $name,
                    'filename' => $filename,
                    'err' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ]);
                return err($e->getMessage());
            }
            return true;
        });

        $data['end'] = time();

        if (is_error($result)) {
            $data['result'] = $result['errno'];
            $data['error'] = $result['message'];
        }

        if (We7::pdo_tableexists(APP_NAME . '_migration')) {
            if(!self::create($data)) {
                Util::logToFile('migrate', [
                    'error' => '无法保存migrate记录！',
                ]);
            }
        } else {
            $history = app()->get('migrate', []);
            $history[$name] = time();
            app()->set('migrate', $history);
        }

        return !is_error($result);
    }

    /**
     * 数据库及程序升级操作
     */
    public static function start()
    {
        while (self::step()) {
            sleep(1);
        }
    }

    public static function reset()
    {
        app()->remove('migrate');
        We7::pdo_delete(migrationModelObj::getTableName(modelObj::OP_WRITE), We7::uniacid([]));
    }

    public static function query($condition = []): base\modelObjFinder
    {
        return m('migration')->query(We7::uniacid([]))->where($condition);
    }

    public static function create($data = [])
    {
        return m('migration')->create(We7::uniacid($data));
    }
}
