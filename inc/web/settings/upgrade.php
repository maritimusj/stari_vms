<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;

$tpl_data['navs'] = Util::getSettingsNavs();
$tpl_data['upgrade'] = [];
$back_url = $this->createWebUrl('settings', ['op' => 'upgrade']);

$data = Util::get(UPGRADE_URL);
if (empty($data)) {
    $tpl_data['upgrade']['error'] = '检查更新失败！';
} else {
    $res = json_decode($data, true);
    if ($res) {
        if ($res['status']) {
            if (request::str('fn') == 'exec') {
                if (empty($res['data']['download'])) {
                    Util::itoast('暂时没有任何文件需要更新！', $back_url, 'success');
                } else {
                    $data = Util::get(UPGRADE_URL.'/?op=exec');
                    $res = json_decode($data, true);
                    if ($res && $res['status']) {
                        if (!Migrate::detect(true)) {
                            Util::itoast('更新成功！', $back_url, 'success');
                        }
                    }
                }
            } else {
                $tpl_data['upgrade']['settings'] = $res['data']['settings'];
                $processFile = function ($arr) {
                    $result = [];
                    foreach ($arr as $filename) {
                        $fi = [
                            'filename' => $filename,
                            'dest' => $filename,
                        ];
                        $local_file = MODULE_ROOT.$filename;
                        if (file_exists($local_file)) {
                            $stats = stat($local_file);
                            if ($stats) {
                                $fi['size'] = is_dir($local_file) ? '<文件夹>' : $stats[7];
                                $fi['createtime'] = (new DateTime("@$stats[9]"))->format('Y-m-d H:i:s');
                            }
                        }
                        $result[] = $fi;
                    }

                    return $result;
                };
                $tpl_data['upgrade']['download'] = $processFile($res['data']['download']);
                $tpl_data['upgrade']['copy'] = $processFile($res['data']['copy']);
                $tpl_data['upgrade']['move'] = $processFile($res['data']['move']);
                $tpl_data['upgrade']['remove'] = $processFile($res['data']['remove']);
            }
        } else {
            $tpl_data['upgrade']['error'] = empty($res['data']['message']) ? '暂无无法检查升级！' : strval(
                $res['data']['message']
            );
        }
    } else {
        $tpl_data['upgrade']['error'] = '检查更新失败！';
    }
}

app()->showTemplate('web/settings/upgrade', $tpl_data);