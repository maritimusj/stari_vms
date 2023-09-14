<?php

namespace zovye\contract;

interface ILogWriter
{
    function write($level, $topic, $data);
}