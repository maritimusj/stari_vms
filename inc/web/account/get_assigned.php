<?php

namespace zovye;

$result = [
    'types' => [],
    'cities' => [],
    'agents' => [],
    'devices' => [],
    'tags' => [],
];

$id = request::int('id');
$account = Account::get($id);
if ($account) {
    $data = $account->get('assigned', []);

    if ($data['types'] && is_array($data['types'])) {
        $result['types'] = $data['types'];
    }
    if ($data['cities'] && is_array($data['cities'])) {
        $result['cities'] = $data['cities'];
    }

    if ($data['agents'] && is_array($data['agents'])) {
        foreach ($data['agents'] as $id) {
            $agent = Agent::get($id);
            if ($agent) {
                $result['agents'][] = [
                    'id' => $agent->getId(),
                    'nickname' => $agent->getName(),
                    'avatar' => $agent->getAvatar(),
                    'total' => $agent->getDeviceCount(),
                ];
            }
        }
    }

    if ($data['devices'] && is_array($data['devices'])) {
        foreach ($data['devices'] as $id) {
            $device = Device::get($id);
            if ($device) {
                $result['devices'][] = [
                    'id' => $device->getId(),
                    'name' => $device->getName(),
                ];
            }
        }
    }
    if ($data['tags'] && is_array($data['tags'])) {
        $result['tags'] = $data['tags'];
    }
}

JSON::success($result);