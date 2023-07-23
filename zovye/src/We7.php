<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use we7\db;

/**
 * Class We7
 * @method static string tomedia(string $src, bool $local_path = false)
 * @method static string murl($segment, $params = array(), $noredirect = true, $addhost = false)
 * @method static string wurl($segment, $params = array())
 * @method static mixed load()
 * @method static mixed mc_oauth_userinfo($acid = 0)
 * @method static void message($msg, $redirect = '', $type = '', $tips = false, $extend = array())
 * @method static void itoast($message, $redirect = '', $type = '', $extend = array())
 * @method static array file_upload($file, $type = 'image', $name = '', $compress = false)
 * @method static bool file_remote_delete(string $file)
 * @method static bool|array file_remote_upload($filename, $auto_delete_local = true)
 * @method static string url($segment, $params = array())
 * @method static string mc_openid2uid(string $openid)
 * @method static array mc_credit_fetch($uid, $types = array())
 * @method static bool mc_credit_update($uid, $credittype, $creditval = 0, $log = array())
 * @method static string referer()
 * @method static string attachment_set_attach_url()
 * @method static material_list(string $string, string $MATERIAL_WEXIN, array $array)
 * @method static material_news_list(string $MATERIAL_WEXIN)
 * @method static refund_create_order($order_no, $APP_NAME)
 * @method static refund($refund_id)
 *
 * @method static cache_read($getCacheKey)
 * @method static cache_write($getCacheKey, $data)
 * @method static cache_delete($getCacheKey)
 * @method static cache_clean(string $cacheKey)
 * @method static cutstr($name, int $int, bool $true)
 * @method static mc_credit_types()
 * @method static isimplexml_load_string($result, string $string, int $LIBXML_NOCDATA)
 * @method static string pagination(int $total, mixed $page, int $page_size)
 *

 */
class We7
{
    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        if (function_exists("\\$name")) {
            return call_user_func("\\$name", ...$arguments);
        } else {
            Log::error(
                'we7',
                [
                    'name' => $name,
                    'args' => $arguments,
                ]
            );

            return false;
        }
    }

    public static function register_jssdk($debug)
    {
        global $_W;

        if (defined('HEADER')) {
            echo '';

            return;
        }

        $sysinfo = array(
            'uniacid' => $_W['uniacid'],
            'acid' => $_W['acid'],
            'siteroot' => $_W['siteroot'],
            'siteurl' => $_W['siteurl'],
            'attachurl' => $_W['attachurl'],
            'cookie' => array('pre' => $_W['config']['cookie']['pre']),
        );
        if (!empty($_W['acid'])) {
            $sysinfo['acid'] = $_W['acid'];
        }
        if (!empty($_W['openid'])) {
            $sysinfo['openid'] = $_W['openid'];
        }
        if (defined('MODULE_URL')) {
            $sysinfo['MODULE_URL'] = MODULE_URL;
        }
        $sysinfo = json_encode($sysinfo);
        $jssdkconfig = json_encode($_W['account']['jssdkconfig']);
        $debug = $debug ? 'true' : 'false';

        $script = <<<EOF
<script src="https://res.wx.qq.com/open/js/jweixin-1.6.0.js"></script>
<script type="text/javascript">
	window.sysinfo = window.sysinfo || $sysinfo || {};
	
	// jssdk config 对象
	jssdkconfig = $jssdkconfig || {};
	
	// 是否启用调试
	jssdkconfig.debug = $debug;
	jssdkconfig.openTagList = [
        'wx-open-launch-weapp',
    ];
	jssdkconfig.jsApiList = [
		'checkJsApi',
		'onMenuShareTimeline',
		'onMenuShareAppMessage',
		'onMenuShareQQ',
		'onMenuShareWeibo',
		'hideMenuItems',
		'showMenuItems',
		'hideAllNonBaseMenuItem',
		'showAllNonBaseMenuItem',
		'translateVoice',
		'startRecord',
		'stopRecord',
		'onRecordEnd',
		'playVoice',
		'pauseVoice',
		'stopVoice',
		'uploadVoice',
		'downloadVoice',
		'chooseImage',
		'previewImage',
		'uploadImage',
		'downloadImage',
		'getNetworkType',
		'openLocation',
		'getLocation',
		'hideOptionMenu',
		'showOptionMenu',
		'closeWindow',
		'scanQRCode',
		'chooseWXPay',
		'openProductSpecificView',
		'addCard',
		'chooseCard',
		'openCard'
	];
	
	wx.config(jssdkconfig);
	
</script>
EOF;
        echo $script;
    }

    /**
     * 递归创建目录.
     *
     * @param string $path
     *                     目录
     *
     * @return bool
     */
    public static function make_dirs(string $path): bool
    {
        if (!is_dir($path)) {
            self::make_dirs(dirname($path));
            mkdir($path);
        }

        return is_dir($path);
    }


    /**
     * @param null $data
     * @return mixed
     */
    public static function uniacid($data = null)
    {
        if (is_array($data)) {
            $data['uniacid'] = _W('uniacid');

            return $data;
        }

        return _W('uniacid');
    }

    /**
     * 获取数组的XML结构.
     *
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root
     *
     * @return string
     */
    public static function array2xml(array $arr, int $level = 1): string
    {
        $s = 1 == $level ? '<xml>' : '';
        foreach ($arr as $tag_name => $value) {
            if (is_numeric($tag_name)) {
                $tag_name = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                if (is_null($value)) {
                    $s .= "<$tag_name></$tag_name>";
                } else {
                    $s .= "<$tag_name>".(!is_numeric($value) ? '<![CDATA[' : '').$value.(!is_numeric(
                            $value
                        ) ? ']]>' : '')."</$tag_name>";
                }

            } else {
                $s .= "<$tag_name>".We7::array2xml($value, $level + 1)."</$tag_name>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);

        return 1 == $level ? $s.'</xml>' : $s;
    }

    public static function xml2array($xml)
    {
        if (empty($xml)) {
            return [];
        }

        //禁止引用外部xml实体
        libxml_disable_entity_loader();

        $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (empty($obj)) {
            return [];
        }

        return json_decode(json_encode($obj), true);
    }


    /**
     * 获取随机字符串.
     *
     * @param int $length 字符串长度
     * @param bool $numeric 是否为纯数字
     *
     * @return string
     */
    public static function random(int $length, bool $numeric = false): string
    {
        $seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
        if ($numeric) {
            $hash = '';
        } else {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            --$length;
        }
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $hash .= $seed[mt_rand(0, $max)];
        }

        return $hash;
    }

    /**
     * 获取客户端IP.
     *
     * @return string
     */
    public static function get_ip(): string
    {
        static $ip = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (isset($_SERVER['HTTP_CDN_SRC_IP'])) {
            $ip = $_SERVER['HTTP_CDN_SRC_IP'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all(
                '#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#',
                $_SERVER['HTTP_X_FORWARDED_FOR'],
                $matches
            )) {
            foreach ($matches[0] as $xip) {
                if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                    $ip = $xip;
                    break;
                }
            }
        }
        if (preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $ip)) {
            return $ip;
        } else {
            return '127.0.0.1';
        }
    }

    public static function starts_with($haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ('' != $needle && substr($haystack, 0, strlen($needle)) === (string)$needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断字符串是否包含子串.
     *
     * @param string $string 在该字符串中进行查找
     * @param string $find 需要查找的字符串
     *
     * @return boolean
     */
    public static function str_exists(string $string, string $find): bool
    {
        return !(false === strpos($string, $find));
    }

    /**
     * 判断是否为序列化字符串.
     *
     * @param mixed $data
     * @param bool $strict
     *
     * @return boolean
     */
    public static function is_serialized($data, bool $strict = true): bool
    {
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' == $data) {
            return true;
        }
        if (strlen($data) < 4) {
            return false;
        }
        if (':' !== $data[1]) {
            return false;
        }
        if ($strict) {
            $last_char = substr($data, -1);
            if (';' !== $last_char && '}' !== $last_char) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace = strpos($data, '}');
            // Either ; or } must exist.
            if (false === $semicolon && false === $brace) {
                return false;
            }
            // But neither must be in the first X characters.
            if (false !== $semicolon && $semicolon < 3) {
                return false;
            }
            if (false !== $brace && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];
        switch ($token) {
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (false === strpos($data, '"')) {
                    return false;
                }
            // or else fall through
            // no break
            case 'a':
                return (bool)preg_match("/^$token:[0-9]+:/s", $data);
            case 'O':
                return false;
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';

                return (bool)preg_match("/^$token:[0-9.E-]+;$end/", $data);
        }

        return false;
    }


    /**
     * 获取字符串序列化结果.
     *
     * @param mixed $value
     *
     * @return string
     */
    public static function serialize($value): string
    {
        return serialize($value);
    }

    /**
     * 获取序列化字符的反序列化结果.
     *
     * @param string $value
     *
     * @return mixed
     */
    public static function deserialize(string $value)
    {
        if (empty($value)) {
            return array();
        }
        if (!We7::is_serialized($value)) {
            return $value;
        }
        if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
            $result = unserialize($value, array('allowed_classes' => false));
        } else {
            if (preg_match('/[oc]:[^:]*\d+:/i', $value)) {
                return array();
            }
            $result = unserialize($value);
        }
        if (false === $result) {
            $temp = preg_replace_callback('!s:(\d+):"(.*?)";!s', function ($matchs) {
                return 's:'.strlen($matchs[2]).':"'.$matchs[2].'";';
            }, $value);

            return unserialize($temp);
        } else {
            return $result;
        }
    }

    //修正微擎没有记录支付类型的BUG
    public static function fixPayLog($order_no)
    {
        $core_pay_log = We7::pdo_get('core_paylog', We7::uniacid(['tid' => $order_no]), ['plid', 'type']);
        if (empty($core_pay_log['type'])) {
            We7::pdo_update('core_paylog', ['type' => 'wechat'], ['plid' => $core_pay_log['plid']]);
        }
    }

    /**
     * 获取完整数据表名.
     *
     * @param string $table 数据表名
     *
     * @return string
     */
    public static function tb(string $table): string
    {
        if (empty(Util::config('db.master'))) {
            return "`".Util::config('db.tablepre').$table."`";
        }

        return "`".Util::config('db.master.tablepre').$table."`";
    }

    /**
     * @return mixed
     */
    public static function pdo()
    {
        static $db = null;
        if (!isset($db)) {
            $config = Util::config('db');
            if (empty($config['master']['host']) && empty($config['master']['username'])) {
                $db = call_user_func('pdo');
            } else {
                $db = new db($config);
            }
        }

        return $db;
    }

    public static function pdo_begin()
    {
        self::pdo()->begin();
    }

    public static function pdo_commit()
    {
        self::pdo()->commit();
    }

    public static function pdo_rollback()
    {
        self::pdo()->rollback();
    }

    public static function pdo_table_exists($tb): bool
    {
        return self::pdo()->tableexists($tb);
    }

    public static function pdo_query($sql, array $params = array())
    {
        return self::pdo()->query($sql, $params);
    }

    public static function pdo_get($tb, $condition = array(), $fields = array())
    {
        return self::pdo()->get($tb, $condition, $fields);
    }

    public static function pdo_get_all(
        $tb,
        $condition = array(),
        $fields = array(),
        $key_field = '',
        $order_by = array(),
        $limit = array()
    ) {
        return self::pdo()->getall($tb, $condition, $fields, $key_field, $order_by, $limit);
    }

    public static function pdo_fetch($sql, $params = array())
    {
        return self::pdo()->fetch($sql, $params);
    }

    public static function pdo_update($tb, $data = array(), $params = array(), $glue = 'AND')
    {
        return self::pdo()->update($tb, $data, $params, $glue);
    }

    public static function pdo_insert($tb, $data = array(), $replace = false)
    {
        return self::pdo()->insert($tb, $data, $replace);
    }

    public static function pdo_run($sql): ?bool
    {
        return self::pdo()->run($sql);
    }

    public static function pdo_delete($tb, $params = array(), $glue = 'AND')
    {
        return self::pdo()->delete($tb, $params, $glue);
    }

    public static function pdo_insert_id()
    {
        return self::pdo()->insertid();
    }

    public static function pdo_fetch_column($sql, array $params, $column = '')
    {
        return self::pdo()->fetchcolumn($sql, $params, $column);
    }

    public static function pdo_fetchAll($sql, array $params)
    {
        return self::pdo()->fetchall($sql, $params);
    }

    public static function pdo_get_column($tb, array $array, $string)
    {
        return self::pdo()->getcolumn($tb, $array, $string);
    }

    public static function pdo_field_exists($tb, $field): bool
    {
        return self::pdo()->fieldexists($tb, $field);
    }

    public static function pdo_index_exists($tb, $index_name): bool
    {
        return self::pdo()->indexexists($tb, $index_name);
    }
}
