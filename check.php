<?php

// For version 7.54 and below.
require_once './bootstrap.php';

$manifestDir = '/Library/WebServer/Documents/templates/manifests';

$check = new \BaseKit\Group($manifestDir);
$check ->run();