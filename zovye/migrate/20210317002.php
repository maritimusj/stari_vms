<?php

namespace zovye;

$sql = <<<SQL
CREATE OR REPLACE VIEW `ims_zovye_vms_tester_vw` AS
SELECT u.*,p.createtime as `updatetime`
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=5;
SQL;

Migrate::execSQL($sql);