<?php
namespace zovye;

$op = request::op('default');

if ($op == 'ticket') {
    $res = AliTicket::cb();
    if (is_error($res)) {
        Util::logToFile('ali_ticket', [
            'request' => request::json(),
            'result' => $res,
        ]);
    }
    exit(AliTicket::RESPONSE);
}