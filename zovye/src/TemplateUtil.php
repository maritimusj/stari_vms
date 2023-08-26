<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use InvalidArgumentException;
use we7\template;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class TemplateUtil
{
    public static function compile($filename)
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

    /**
     * 获取页面参数
     * @param $name string 如果指定参数是数组，则取回数组中指定键值
     * @param $throw_error bool
     * @return mixed
     */
    public static function getTemplateVar(string $name = '', bool $throw_error = false)
    {
        $var = $GLOBALS['_tpl_var_'][0];
        if ($name) {
            $var = getArray($var, $name);
        }

        if ($throw_error && is_null($var)) {
            throw new InvalidArgumentException('缺少必须的模块参数！');
        }

        return $var;
    }

    /**
     * 获取页面模板变量.
     *
     * @param mixed $objs
     *
     * @return array
     */
    public static function getTplData(array $objs = []): array
    {
        $data = [
            'module' => APP_NAME,
            'site' => [
                'title' => settings('misc.siteTitle', DEFAULT_SITE_TITLE),
                'copyrights' => settings('misc.siteCopyrights', DEFAULT_COPYRIGHTS),
                'warning' => settings('misc.siteWarning', ''),
            ],
            'page' => [
                'title' => DEFAULT_SITE_TITLE,
            ],
            'theme' => settings('device.get.theme', 'default'),
            'exclude' => [],
        ];

        foreach ($objs as $index => $entry) {
            if (is_string($index)) {
                setArray($data, $index, $entry);
                continue;
            }

            if ($entry instanceof userModelObj) {
                $data['user'] = [
                    'id' => $entry->getId(),
                    'openid' => $entry->getOpenid(),
                    'nickname' => $entry->getNickname(),
                    'avatar' => $entry->getAvatar(),
                    '_obj' => $entry,
                ];
            } elseif ($entry instanceof deviceModelObj) {
                $data['device'] = [
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                    'imei' => $entry->getImei(),
                    'shadowId' => $entry->getShadowId(),
                    '_obj' => $entry,
                ];

                $agent = $entry->getAgent();
                //获取代理商页面设置
                if ($agent) {
                    $agent_data = $agent->getAgentData();
                    if ($agent_data) {
                        if ($agent_data['misc']['siteTitle']) {
                            $data['site']['title'] = $agent_data['misc']['siteTitle'];
                        }
                        if ($agent_data['misc']['copyrights']) {
                            $data['site']['copyrights'] = $agent_data['misc']['copyrights'];
                        }
                    }
                }
            } elseif ($entry instanceof accountModelObj) {
                $data['account'] = [
                    'uid' => $entry->getUid(),
                    'bonus_type' => $entry->getBonusType(),
                    'title' => $entry->getTitle(),
                    'descr' => $entry->getDescription(),
                    'img' => $entry->getImg(),
                    'qrcode' => $entry->getQrcode(),
                    'clr' => $entry->getClr(),
                    '_obj' => $entry,
                ];
            } elseif (is_array($entry)) {
                foreach ($entry as $key => $val) {
                    setArray($data, $key, $val);
                }
            }
        }

        return $data;
    }

}