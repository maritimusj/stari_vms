<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;
use Exception;
use zovye\model\accountModelObj;

class Questionnaire 
{
    public static function log($cond = [])
    {
        return m('account_logs')->where($cond);
    }

    public static function exportLogs(accountModelObj $account, $s_date, $e_date)
    {
        $query = self::log(['level' => $account->getId()]);
        if ($s_date) {
            try {
                $begin = new DateTime($s_date . ' 00:00');
                $query->where(['createtime >=' => $begin->getTimestamp()]);
            } catch(Exception $e) {
                return err('开始时间不正确！');
            }
        }

        if ($e_date) {
            try {
                $end = new DateTime($e_date . ' 00:00');
                $end->modify('next day 00:00');
                $query->where(['createtime <' => $end->getTimestamp()]);
            } catch(Exception $e) {
                return err('结束时间不正确！');
            }
        }

        $uid = REQUEST_ID;
        $short_filename = "export/$uid.xls";
        $filename = ATTACHMENT_ROOT . $short_filename;
        Util::exportExcelFile($filename, ['#', '昵称', 'openid', '设备名称', '设备编号', '订单号', '问卷内容', '创建时间']);

        foreach($query->findAll() as $index => $log)
        {
            $user = $log->getData('user', []);
            $device = $log->getData('device', []);
            $questions = $log->getData('questions', []);
            $answer = $log->getData('answer', []);

            $content = '';
            foreach($questions as $j => $question) {
                if (empty($question['title'])) {
                    continue;
                }
                $i = $j + 1;
                $content .= "【{$i}】 {$question['title']} => ";
                $id = $question['id'] ?? '';
                if ($question['type'] == 'choice') {
                    if (empty($question['options'])) {
                        continue;
                    }
                    $ids = $answer[$id] ?? [];
                    $res = array_intersect_key((array)$question['options'], (array)$ids);
                    $content .= '[' . implode('，', array_column($res, 'text')) . ']';
                } elseif ($question['type'] == 'text') {
                    $text = $answer[$id] ?? '';
                    $content .= "\"$text\"";
                }
                $content .= "  ";
                $content = str_replace(',', '，', $content);
            }

            $data = [
                $index, 
                $user['nickname'] ?? '', 
                $user['openid'] ?? '',
                $device['name'] ?? '',
                $device['imei'] ?? '',
                $log->getData('order.orderNO', ''),
                $content,
                date('Y-m-d H:i:s', $log->getCreatetime()),
            ];
            Util::exportExcelFile($filename, [], [$data]);
        }

        return [
            'redirect' => Util::toMedia($short_filename),
        ];
    }
}