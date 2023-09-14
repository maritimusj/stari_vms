<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\contract;

interface IHttpClient
{
    /**
     * @param string $url
     * @param string $method
     * @param string|array $headers
     * @param string|array $data
     * @param int $timeout
     * @return mixed
     */
    public function request(string $url, string $method = 'GET', $headers = '', $data = '', int $timeout = 60);
}
