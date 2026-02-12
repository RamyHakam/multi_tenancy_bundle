<?php

require dirname(__DIR__).'/vendor/autoload.php';
DG\BypassFinals::enable();

if (!empty($_SERVER['APP_DEBUG'])) {
    umask(0000);
}
