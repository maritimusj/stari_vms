<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\util\Util;

return [
    //设备设置
    'device' => [
        'autoJoin' => 0,
        'clearErrorCode' => 0,
        'remainWarning' => 10,
        'waitTimeout' => DEFAULT_DEVICE_WAIT_TIMEOUT,
        'lockRetries' => 10,
        'lockRetryDelay' => 1,
        'lockTimeout' => 30,
        'get' => [
            'theme' => 'rewatch',
        ],
        'lost' => 5,
        'issuing' => 10,
    ],
    'goods' => [
        'agent' => [
            'edit' => 1,
        ],
    ],
    //会员设置
    'user' => [
        'location' => [
            'validate' => [
                'enabled' => 0,
                'distance' => 1,
            ],
        ],
    ],
    //代理商设置
    'agent' => [
        'advs' => [
            'video' => [
                'title' => '视频',
                'max' => 0,
            ],
            'image' => [
                'title' => '图片',
                'max' => 0,
            ],
            'audio' => [
                'title' => '音频',
                'max' => 0,
            ],
        ],
        'msg_tplid' => '',
        'levels' => [
            'level0' => ['clr' => '#673ab7', 'title' => '经销商'],
            'level1' => ['clr' => '#8bc34a', 'title' => '种子代理'],
            'level2' => ['clr' => '#4CAF50', 'title' => '天使代理'],
            'level3' => ['clr' => '#009688', 'title' => '区域代理'],
            'level4' => ['clr' => '#2196F3', 'title' => '城市代理'],
            'level5' => ['clr' => '#FF9800', 'title' => '省级代理'],
        ],
    ],
    //代理商管理小程序设置
    'agentWxapp' => [
        'key' => '',
        'secret' => '',
    ],
    //佣金设置
    'commission' => [
        'enabled' => 0,
        'withdraw' => [
            'min' => 0,
            'max' => 0,
            'count' => [
                'month' => 0,
            ],
            'pay_type' => 0,
        ],
    ],
    //通知设置
    'notice' => [
        'sms' => [
            'url' => 'https://v.juhe.cn/sms/send?',
            'appkey' => '',
        ],
        'reload_smstplid' => '',
        'reload_tplid' => '',
        'agentReq_tplid' => '',
        'deviceerr_tplid' => '',
        'agentresult_tplid' => '',
        'advReviewTplid' => '',
        'advReviewResultTplid' => '',
        'delay' => [
            'remainWarning' => 1,
            'deviceerr' => 1,
        ],
        'reviewAdminUser' => ['id' => 0, 'nickname' => ''],
        'authorizedAdminUser' => ['id' => 0, 'nickname' => ''],
    ],
    //控制中心授权设置
    'ctrl' => [
        'addr' => '',
        'url' => '',
        'appKey' => '',
        'appSecret' => '',
        'signature' => Util::random(32),
    ],
    //其它设置
    'misc' => [
        'siteTitle' => DEFAULT_SITE_TITLE,
        'siteCopyrights' => DEFAULT_COPYRIGHTS,
        'banner' => '',
        'pushAccountMsg_type' => '',
        'pushAccountMsg_val' => '',
        'pushAccountMsg_delay' => '',
        'maxAccounts' => 0,
        'maxFree' => 0,
        'redirect' => [
            'success' => [
                'url' => '',
            ],
            'fail' => [
                'url' => '',
            ],
        ],
    ],
    //系统会员设置
    'we7credit' => [
        'enabled' => 0,
        'type' => 0,
        'val' => 0,
        'require' => 0,
    ],
    //文档
    'doc' => [
        'types' => [
            'word' => [
                'text' => 'WORD文件',
                'icon' => MODULE_URL.'static/img/word.png',
            ],
            'excel' => [
                'text' => 'EXCEL文件',
                'icon' => MODULE_URL.'static/img/excel.png',
            ],
            'ppt' => [
                'text' => 'PPT文件',
                'icon' => MODULE_URL.'static/img/ppt.png',
            ],
            'pdf' => [
                'text' => 'PDF文件',
                'icon' => MODULE_URL.'static/img/pdf.png',
            ],
            'zip' => [
                'text' => 'ZIP文件',
                'icon' => MODULE_URL.'static/img/zip.png',
            ],
            'unknown' => [
                'id' => 6,
                'text' => '未知文件',
                'icon' => MODULE_URL.'static/img/unknown.png',
            ],
        ],
    ],
];
