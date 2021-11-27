<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

define('ZOVYE', 'v2');

define('ZOVYE_ROOT', str_replace(DIRECTORY_SEPARATOR . 'zovye' . DIRECTORY_SEPARATOR . 'include', '', __DIR__) . DIRECTORY_SEPARATOR);
define('ZOVYE_CORE_ROOT', ZOVYE_ROOT . 'zovye' . DIRECTORY_SEPARATOR);
define('DATA_DIR', ZOVYE_ROOT . 'data' . DIRECTORY_SEPARATOR);
define('LOG_DIR', DATA_DIR . 'logs' . DIRECTORY_SEPARATOR);
define('MOD_CACHE_DIR', DATA_DIR . 'mod_cache' . DIRECTORY_SEPARATOR);
define('PEM_DIR', DATA_DIR . 'pem' . DIRECTORY_SEPARATOR); //微信支付企业密钥文件生成目录，最好设置到web目录以外的目录，需要可写权限
define('LIB_DIR', ZOVYE_ROOT . 'lib' . DIRECTORY_SEPARATOR);

define('L_ALL', 0);
define('L_INFO', 1);
define('L_DEBUG', 2);
define('L_WARN', 3);
define('L_ERROR', 4);
define('L_FATAL', 5);

define('APP_NAME', basename(ZOVYE_ROOT));
define('DEBUG', true);

define('LOG_LEVEL', L_ERROR);

define('ZOVYE_STATIC_URL', $GLOBALS['_W']['sitescheme'] . $_SERVER['HTTP_HOST'] . '/addons/' . APP_NAME . '/');

define('LEVEL_HIGH', 'order');
define('LEVEL_NORMAL', 'normal');
define('LEVEL_LOW', 'lower');



define('LOG_PAY_RESULT', 60);
define('LOG_PAY', 101);

define('LOG_GOODS_TEST', 110);
define('LOG_GOODS_PAY', 111);
define('LOG_GOODS_CB', 112);
define('LOG_GOODS_GETX', 113);
define('LOG_GOODS_VOUCHER', 114);
define('LOG_GOODS_ADVS', 115);
define('LOG_GOODS_RETRY', 116);
define('LOG_GOODS_BALANCE', 117);

define('DEFAULT_PAGESIZE', 20);
define('DEFAULT_DEVICE_CAPACITY', 10);
define('DEFAULT_DEVICE_WAIT_TIMEOUT', 15);
define('DEFAULT_LOCK_TIMEOUT', 15);

define('VISIT_DATA_TIMEOUT', 300);
define('PAY_TIMEOUT', 180); //支付超时，秒

define('DEFAULT_ACCOUNT_DESC', '长按识别公众号，免费领取');

define('DEFAULT_BALANCE_TITLE', '卓币');
define('DEFAULT_BALANCE_UNIT_NAME', '个');
define('DEFAULT_SITE_TITLE', '新零售SaaS平台');
define('DEFAULT_COPYRIGHTS', '© 版权所有，侵权必究');

define('WITHDRAW_ADMIN', 0); //手动打款
define('WITHDRAW_SYS', 1); //自动打款
define('MCH_PAY_MIN_MONEY', 100); //微信提现最低金额（分）

define('DEFAULT_IMAGE_DURATION', 10); //图片广告默认停留时间（秒）

define('OBJ_LOCKED_UID', 'locked_uid');
define('UNLOCKED', 'n/a');

define('MAX_ORDER_NO_LEN', 32);

define('DEVICE_FORWARDER_URL', 'https://z.ph6618.cn/?id={imei}');
define('FLUSH_DEVICE_FORWARDER_URL', 'https://z.ph6618.cn/cache/flush?id={imei}');

define('DEFAULT_LBS_KEY', 'FNBBZ-DK2C4-3LCUM-DXXIO-GVYQ7-OTBEA');

define('JS_WE7UTIL_URL', $GLOBALS['_W']['siteroot'] . "app/resource/js/app/util.js");
define('JS_JQUERY_URL', 'https://cdn.staticfile.org/jquery/1.11.1/jquery.min.js');
define('JS_AXIOS_URL', 'https://cdn.staticfile.org/axios/0.19.2/axios.min.js');
define('JS_VUE_URL', 'https://cdn.staticfile.org/vue/2.6.9/vue.min.js');

define('JS_MUI_URL', 'https://cdn.staticfile.org/mui/3.7.1/js/mui.min.js');
define('CSS_MUI_URL', 'https://cdn.staticfile.org/mui/3.7.1/css/mui.min.css');

define('JS_XLSX_URL', 'https://cdn.staticfile.org/xlsx/0.16.6/xlsx.full.min.js');
define('JS_XLSX_SHIM_URL', 'https://cdn.staticfile.org/xlsx/0.16.6/shim.min.js');
define('JS_ECHARTS_URL', 'https://cdn.staticfile.org/echarts/5.0.2/echarts.common.min.js');

define('JS_SWIPER_URL', 'https://cdn.staticfile.org/Swiper/4.5.1/js/swiper.min.js');
define('CSS_SWIPER_URL', 'https://cdn.staticfile.org/Swiper/4.5.1/css/swiper.min.css');
define('CSS_ANIMATE_URL', 'https://cdn.staticfile.org/animate.css/4.1.1/animate.min.css');

define('UPGRADE_URL', 'http://127.0.0.1:9012');

defined('DEVELOPMENT') or define('DEVELOPMENT', DEBUG);
defined('TIMESTAMP') or define('TIMESTAMP', time());
defined('CLIENT_IP') or define('CLIENT_IP', Util::getClientIp());
defined('MATERIAL_WEXIN') or define('MATERIAL_WEXIN', 'perm'); //微信素材类型
defined('REGULAR_MOBILE') or define('REGULAR_MOBILE', '/^\d{6,15}$/');
defined('MODULE_URL') or define('MODULE_URL', ZOVYE_STATIC_URL);
defined('ATTACHMENT_ROOT') or define('ATTACHMENT_ROOT', ZOVYE_ROOT . '/attachment/');
defined('MODULE_ROOT') or define('MODULE_ROOT', ZOVYE_ROOT);