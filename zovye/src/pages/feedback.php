<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\util\TemplateUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$api_url1 = Util::murl('util', ['op' => 'upload_pic']);
$api_url2 = Util::murl('device', ['op' => 'feed_back']);

$axios_url = JS_AXIOS_URL;
$js_code = <<<CODE
<script src="$axios_url"></script>
<script>
const zovye_fn = {};
zovye_fn.upload = function(data) {
    const param = new FormData();
    param.append('pic', data);
    
    const config = {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }
    return new Promise((resolve, reject) => {
         axios.post('$api_url1',param, config).then((res) => {
            return res.data;
         }).then((res) => {
             if (res.status && res.data) {
                 resolve(res.data.data);
             } else {
                reject(res.msg || '上传失败！');
             }
         }).catch(() => {
           reject("上传失败！");
         });
    })
}

zovye_fn.feedback = function(device, text, pics) {
    const data = new FormData();
    data.append('device', device);
    data.append('text', text);
    
    for (let i = 0; i < (pics || []).length; i++) {
        data.append(('pics[' + i + ']'), pics[i]);
    }
    
    return new Promise((resolve, reject) => {
        axios.post("$api_url2", data).then((res) => {
            return res.data;
        }).then((res) => {
            if (res.status) {
                resolve(res.data.msg || '反馈成功！');
            } else {
                reject(res.data.msg || '上传失败！');
            }
        }).catch(() =>{
            reject("上传失败！");
        });        
    })
}

</script>
CODE;

$tpl_data = TemplateUtil::getTplData();
$tpl_data['js']['code'] = $js_code;
Response::showTemplate('feedback', ['tpl' => $tpl_data], true);