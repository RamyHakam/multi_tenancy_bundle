<?php

require dirname(__DIR__).'/vendor/autoload.php';
DG\BypassFinals::enable();
DG\BypassFinals::setWhitelist([dirname(__DIR__) . '/src/*']);

if (!empty($_SERVER['APP_DEBUG'])) {
    umask(0000);
}
