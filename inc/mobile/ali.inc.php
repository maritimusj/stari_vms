<?php
namespace zovye;

$op = request::op('default');
if ($op == 'ticket') {
    if (request::has('appResult')) {
        if (request::str('appResult') == 'success') {
            Util::resultAlert('成功，谢谢参与活动！');
        } else {
            Util::resultAlert('失败，请扫码重试，谢谢！', 'error');
        }
    } else {
        $res = AliTicket::cb();
        if (is_error($res)) {
            Util::logToFile('ali_ticket', [
                'request' => request::raw(),
                'result' => $res,
            ]);
        }
        exit(AliTicket::RESPONSE);        
    }    

}