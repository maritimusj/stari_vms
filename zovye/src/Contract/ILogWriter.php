<?php

namespace zovye\Contract;

interface ILogWriter
{
    function write($level, $topic, $data);
}