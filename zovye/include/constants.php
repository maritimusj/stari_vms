<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

define(
    'ZOVYE_ROOT',
    str_replace(DIRECTORY_SEPARATOR.'zovye'.DIRECTORY_SEPARATOR.'include', '', __DIR__).DIRECTORY_SEPARATOR
);
define('ZOVYE_CORE_ROOT', ZOVYE_ROOT.'zovye'.DIRECTORY_SEPARATOR);
define('ZOVYE_SRC', ZOVYE_ROOT.'zovye'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR);
define('DATA_DIR', ZOVYE_ROOT.'data'.DIRECTORY_SEPARATOR);
define('RUNTIME_DIR', DATA_DIR.'runtime'.DIRECTORY_SEPARATOR);
define('LOG_DIR', DATA_DIR.'logs'.DIRECTORY_SEPARATOR);
define('MOD_CACHE_DIR', DATA_DIR.'mod_cache'.DIRECTORY_SEPARATOR);
define('PEM_DIR', DATA_DIR.'pem'.DIRECTORY_SEPARATOR); //微信支付企业密钥文件生成目录，最好设置到web目录以外的目录，需要可写权限
define('LIB_DIR', ZOVYE_ROOT.'lib'.DIRECTORY_SEPARATOR);

define('APP_NAME', basename(ZOVYE_ROOT));

define('SYS_MAX_LOAD_AVERAGE_VALUE', 10);

define('DEBUG', true);

define('L_DEBUG', 1);
define('L_INFO', 2);
define('L_WARN', 3);
define('L_ERROR', 4);
define('L_FATAL', 5);

//日志等级
define('LOG_LEVEL', L_DEBUG);

//日志最多保留天数
define('LOG_MAX_RETAIN_DAYS', 7);

//按主题过滤日志
define('LOG_TOPIC_INCLUDES', []);

define('ZOVYE_STATIC_URL', $GLOBALS['_W']['sitescheme'].$_SERVER['HTTP_HOST'].'/addons/'.APP_NAME.'/');

define('JOB_LEVEL_HIGH', 'order');
define('JOB_LEVEL_NORMAL', 'normal');
define('JOB_LEVEL_LOW', 'lower');

define('LOG_SMS', 30);

define('LOG_PAY', 101);
define('LOG_GOODS_TEST', 110);
define('LOG_GOODS_PAY', 111);
define('LOG_GOODS_CB', 112);
define('LOG_GOODS_FREE', 113);
define('LOG_GOODS_VOUCHER', 114);
define('LOG_GOODS_ADV', 115);
define('LOG_GOODS_RETRY', 116);
define('LOG_GOODS_BALANCE', 117);
define('LOG_CHARGING_PAY', 120);
define('LOG_RECHARGE', 121);
define('LOG_FUELING_PAY', 122);
define('LOG_DEVICE_RENEWAL_PAY', 123);

define('EVENT_BEFORE_LOCK', 'device.beforeLock');
define('EVENT_LOCKED', 'device.locked');
define('EVENT_OPEN_SUCCESS', 'device.openSuccess');
define('EVENT_OPEN_FAIL', 'device.openFail');
define('EVENT_ORDER_CREATED', 'device.orderCreated');

define('DEFAULT_PAGE_SIZE', 20);
define('DEFAULT_DEVICE_CAPACITY', 10);
define('DEFAULT_DEVICE_WAIT_TIMEOUT', 15);

define('VISIT_DATA_TIMEOUT', 300);
define('PAY_TIMEOUT', 180); //支付超时，秒

define('DEFAULT_ACCOUNT_DESC', '长按识别公众号，免费领取');

define('DEFAULT_BALANCE_TITLE', '币');
define('DEFAULT_BALANCE_UNIT_NAME', '个');
define('DEFAULT_SITE_TITLE', '新零售SaaS平台');
define('DEFAULT_COPYRIGHTS', '© 版权所有，侵权必究');

define('WITHDRAW_ADMIN', 0); //手动打款
define('WITHDRAW_AUTO', 1); //自动打款

define('DEFAULT_IMAGE_DURATION', 10); //图片广告默认停留时间（秒）

define('OBJ_LOCKED_UID', 'locked_uid');
define('UNLOCKED', 'n/a');

define('MAX_ORDER_NO_LEN', 32);

define('FLUSH_DEVICE_FORWARDER_URL', 'https://z.ph6618.cn/cache/flush?id={imei}');

define('UPGRADE_URL', 'http://127.0.0.1:9012');

define('DEFAULT_LBS_KEY', '');

define('JS_WE7UTIL_URL', $GLOBALS['_W']['siteroot']."app/resource/js/app/util.js");
define('JS_JQUERY_URL', ZOVYE_STATIC_URL . 'static/js/jquery.min.js');//jquery/1.11.1
define('JS_AXIOS_URL', ZOVYE_STATIC_URL . 'static/js/axios.min.js');//axios/0.19.2
define('JS_VUE_URL', ZOVYE_STATIC_URL . 'static/js/vue.min.js');//2.6.9
define('JS_MUI_URL', ZOVYE_STATIC_URL . 'static/js/mui.min.js');//3.7.1
define('CSS_MUI_URL', ZOVYE_STATIC_URL . 'static/js/mui.min.css');
define('JS_XLSX_URL', ZOVYE_STATIC_URL . 'static/js/xlsx.full.min.js');//0.16.6
define('JS_XLSX_SHIM_URL', ZOVYE_STATIC_URL . 'static/js/shim.min.js');//0.16.6
define('JS_ECHARTS_URL', ZOVYE_STATIC_URL . 'static/js/echarts.common.min.js');//5.0.2
define('JS_SWIPER_URL', ZOVYE_STATIC_URL . 'static/js/swiper.min.js');//4.5.1
define('JS_SWIPER_BUNDLE_URL', ZOVYE_STATIC_URL . 'static/js/swiper-bundle.min.js');//7.1.0
define('CSS_SWIPER_BUNDLE_URL', ZOVYE_STATIC_URL . 'static/js/swiper-bundle.min.css');//7.1.0
define('CSS_SWIPER_URL', ZOVYE_STATIC_URL . 'static/js/swiper.min.css');
define('CSS_ANIMATE_URL', ZOVYE_STATIC_URL . 'static/js/animate.min.css');//4.1.1
define('JS_VIDEO_URL', ZOVYE_STATIC_URL . 'static/js/video.js');//4.1.1
define('CSS_VIDEO_URL', ZOVYE_STATIC_URL . 'static/js/video-js.min.css');//4.1.1
define('JS_VUE_CLIPBOARD_URL', ZOVYE_STATIC_URL . 'static/js/vue-clipboard.min.js');//0.3.1

defined('DEVELOPMENT') or define('DEVELOPMENT', DEBUG);
defined('TIMESTAMP') or define('TIMESTAMP', time());
defined('CLIENT_IP') or define('CLIENT_IP', Session::getClientIp());
defined('MATERIAL_WEXIN') or define('MATERIAL_WEXIN', 'perm'); //微信素材类型

define('REGULAR_EMAIL', '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/i');
define('REGULAR_TEL', '/^(?:\+|\d)[\d-]{6,14}\d$/');

defined('MODULE_URL') or define('MODULE_URL', ZOVYE_STATIC_URL);
defined('ATTACHMENT_ROOT') or define('ATTACHMENT_ROOT', ZOVYE_ROOT.'/attachment/');
defined('MODULE_ROOT') or define('MODULE_ROOT', ZOVYE_ROOT);