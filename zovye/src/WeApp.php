<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use zovye\base\modelObj;
use zovye\model\weapp_configModelObj;

class WeApp extends Settings
{
    private static $app_settings = null;
    private $logger;

    public function __construct()
    {
        parent::__construct('weapp', 'config', true);
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function run(): WeApp
    {
        //输出模块类
        class_alias(__NAMESPACE__.'\Site', lcfirst(APP_NAME).'ModuleSite');

        //默认加载缓存
        We7::load()->func('cache');

        Request::extraAjaxJsonData();

        //设置request数据源
        Request::setData($GLOBALS['_GPC']);

        //初始化日志
        Log::init(new FileLogWriter(), settings('app.log.level', LOG_LEVEL));

        //捕获错误和异常
        Util::setErrorHandler();

        //初始化事件驱动
        EventBus::init();

        $http_client = new we7HttpClient();

        //设置CtrlServ
        CtrlServ::init($http_client, settings('ctrl', []));

        //初始化充电设备服务
        if (App::isChargingDeviceEnabled()) {
            ChargingServ::setHttpClient($http_client, Config::charging('server', []));
        }

        return $this;
    }

    public function forceUnlock(): bool
    {
        return We7::pdo_update(
            weapp_configModelObj::getTableName(modelObj::OP_WRITE),
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
            return DBUtil::lockObject($global, [OBJ_LOCKED_UID => UNLOCKED], true);
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
        return class_exists(__NAMESPACE__.'\Site');
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
                if (DEBUG) {
                    $filename = ZOVYE_CORE_ROOT.'include/settings_default.php';
                    if (file_exists($filename)) {
                        self::$app_settings = include $filename;
                    }
                } else {
                    trigger_error('global settings is empty!', E_USER_ERROR);
                }
            }
        }

        return getArray(self::$app_settings, $key, $default);
    }

    public function log($level = null, $title = null, $data = null)
    {
        if (!isset($this->logger)) {
            $this->logger = new LogObj('app');
        }

        if ($this->logger && isset($level) && isset($title) && isset($data)) {
            $this->logger->create(intval($level), strval($title), $data);
        }
    }

    public function createWebUrl($do, $params = []): string
    {
        return Util::url($do, $params, false);
    }

    public function createMobileUrl($do, $params = []): string
    {
        return Util::murl($do, $params);
    }

    public static function template($filename)
    {
        return TemplateUtil::compile($filename);
    }

    /**
     * @param $filename
     * @param array $tpl_data
     */
    public function showTemplate($filename, array $tpl_data = [])
    {
        $tpl_data['_GPC'] = $GLOBALS['_GPC'];
        $tpl_data['_W'] = $GLOBALS['_W'];

        extract($tpl_data);

        include self::template($filename);

        exit();
    }

    /**
     * 加载并返回页面模板字符串.
     *
     * @param string $name 模板名称
     * @param array $tpl_data
     * @return string
     */
    public function fetchTemplate(string $name, array $tpl_data = []): string
    {
        $tpl_data['_GPC'] = $GLOBALS['_GPC'];
        $tpl_data['_W'] = $GLOBALS['_W'];

        extract($tpl_data);

        ob_start();

        include self::template($name);

        return ob_get_clean();
    }
}

