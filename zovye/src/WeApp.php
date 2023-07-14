<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use we7\template;
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

    public function createWebUrl($do, $params = []): string
    {
        return Util::url($do, $params, false);
    }

    public function createMobileUrl($do, $params = []): string
    {
        return Util::murl($do, $params);
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

    public static function template($filename)
    {
        global $_W;
        if (defined('IN_SYS')) {
            $source = ZOVYE_ROOT."template/$filename.html";
            $compile = ZOVYE_ROOT."data/tpl/$filename.tpl.php";
        } else {
            $source = ZOVYE_ROOT."template/mobile/$filename.html";
            $compile = ZOVYE_ROOT."data/tpl/mobile/$filename.tpl.php";
        }

        if (!is_file($source)) {
            exit("Error: template source '$filename' is not exist!");
        }

        $paths = pathinfo($compile);
        $compile = str_replace($paths['filename'], $_W['uniacid'].'_'.$paths['filename'], $compile);
        if (DEVELOPMENT || !is_file($compile) || filemtime($source) > filemtime($compile)) {
            template::compile($source, $compile, true);
        }

        return $compile;
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

    /**
     * @return $this
     * @throws Exception
     */
    public function run(): WeApp
    {
        class_alias(__NAMESPACE__.'\Site', lcfirst(APP_NAME).'ModuleSite');
        return $this;
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
                $filename = ZOVYE_CORE_ROOT.'include/settings_default.php';
                if (file_exists($filename)) {
                    self::$app_settings = include $filename;
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

    public function snapshotJs($params = []): string
    {
        $gif_url = MODULE_URL . "static/img/here.gif";
        $html = <<<HTML
        <div style="position: fixed;width: 100vw;height:100vh;z-index: 1000;background: rgba(0,0,0,0.7);left: 0;top: 0;bottom:0">
        <div style="flex-direction: column;display: flex;align-items: center;justify-content: center;width: 100%;height: 100%;color: #fff;font-size: large;">
            <div style="width: 80%;text-align: center;padding: 20px 20px;background: rgba(0,0,0,.5);">
            需要用户授权才能使用该功能，请点击右下角 <b style="color:#fc6;">“使用完整服务”</b>！</span>
            </div>
            <img src="$gif_url" style="width:60px;bottom: 10px;right: 40px;position: absolute;">
        </div>
        </div>
HTML;

        if (empty($params['op'])) {
            $params['op'] = 'snapshot';
        }
        if (empty($params['entry'])) {
            $params['entry'] = 'entry';
        }

        $snapshot_url = Util::murl('util', $params);

        return <<<JSCODE
\r\n
        <script>
            zovye_fn.snapshot = function() {
                $.get("$snapshot_url").then(res => {
                    if (res.status && res.data && res.data.redirect) {
                        window.location.href = res.data.redirect;
                    }
                });
            }
            $(`$html`).appendTo('body').click(function(){
                zovye_fn.snapshot();
            });        
    </script>
JSCODE;
    }
}

