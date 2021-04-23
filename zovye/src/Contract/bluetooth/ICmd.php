<?php

namespace zovye\Contract\bluetooth;

interface ICmd
{
    function getDeviceID();

    function getID();

    function getData();

    function getRaw();

    function getMessage();

    function getEncoded($fn = null);
}