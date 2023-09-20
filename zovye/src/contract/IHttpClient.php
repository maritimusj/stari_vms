<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\contract;

interface IHttpClient
{
    /**
     * @return mixed
     */
    public function request(string $url, string $method = 'GET', $headers = '', $data = '', int $timeout = 60);
}
