<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\Contract;

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
