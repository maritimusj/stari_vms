<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use Exception;
use zovye\base\modelObj;
use zovye\model\weapp_configModelObj;

class WeApp extends Settings
{
    private static $app_settings = null;
    private $logger;
    private $web = [];
    private $mobile = [];

    public function __construct()
    {
        parent::__construct($this, 'weapp', 'config', true);
    }

    public function forceUnlock(): bool
    {
        return We7::pdo_update(weapp_configModelObj::getTableName(modelObj::OP_WRITE),
            [
                OBJ_LOCKED_UID => UNLOCKED,
            ],
            [
                'name' => 'settings',
            ]
        );
    }

    public function lock(): ?RowLocker
    {
        //避免首次安装时，webapp_config没有任何数据时出错
        $this->updateSettings('app.locker', time());

        $global = m('weapp_config')->findOne(['name' => 'settings']);
        if ($global) {
            return Util::lockObject($global, [OBJ_LOCKED_UID => UNLOCKED], true);
        }

        return null;        
    }

    public function isLocked(): bool
    {
        /** @var weapp_configModelObj $global */
        $global = m('weapp_config')->findOne(['name' => 'settings']);
        return $global && $global->getLockedUid() != UNLOCKED;
    }

    public function resetLock()
    {
        /** @var weapp_configModelObj $global */
        $global = m('weapp_config')->findOne(['name' => 'settings']);
        if ($global) {
            $global->setLockedUid(UNLOCKED);
            $global->save();
        }
    }

    public function isSite(): bool
    {
        return class_exists(__NAMESPACE__ . '\Site');
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function run(): WeApp
    {
        global $do;

        if ($do != 'migrate') {
            Util::cachedCall(function() {
                Migrate::detect(true);
            }, 10);
        }

        class_alias(__NAMESPACE__ . '\Site', lcfirst(APP_NAME) . 'ModuleSite');
        return $this;
    }


    public function web($do, $act)
    {
        if ($do && $act) {
            $this->web[strtolower($do)] = $act;
        }
    }

    public function mobile($do, $act)
    {
        if ($do && $act) {
            $this->mobile[strtolower($do)] = $act;
        }
    }

    public function saveSettings($settings): bool
    {
        if ($this->set('settings', $settings)) {
            self::$app_settings = $settings;

            return true;
        }

        return false;
    }

    public function updateSettings($key, $val): bool
    {
        if (is_null(self::$app_settings)) {
            self::$app_settings = $this->get('settings', []);
        }

        setArray(self::$app_settings, $key, $val);

        return $this->set('settings', self::$app_settings);
    }

    public function settings($key = null, $default = null)
    {
        if (is_null(self::$app_settings)) {
            self::$app_settings = $this->get('settings', []);
            if (empty(self::$app_settings)) {
                $filename = ZOVYE_CORE_ROOT . 'include/settings_default.php';
                if (file_exists($filename)) {
                    self::$app_settings = include $filename;
                }
            }
        }

        return getArray(self::$app_settings, $key, $default);
    }

    public function op($op, $cb)
    {
    }

    public function doWeb($do)
    {
        if (isset($this->web[$do])) {
            $act = $this->web[$do];
            if (is_callable($act)) {
                $act();
            }
        }

        if (DEBUG) {
            trigger_error("[{$do}] not implemented!");
        }
    }

    public function doMobile($do)
    {
        if (DEBUG) {
            trigger_error("[{$do}] not implemented!");
        }
    }

    public function log($level = null, $title = null, $data = null)
    {
        if (!isset($this->logger)) {
            $this->logger = new Log('app');
        }

        if ($this->logger && isset($level) && isset($title) && isset($data)) {
            $this->logger->log($level, $title, $data);
        }
    }

}
