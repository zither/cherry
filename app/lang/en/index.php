<?php

$common = require 'includes/list_common.php';

return array_merge([
    'index_admin' => 'Admin',
    'activity_count' => '%d Posts',
    'following_count' => '%d Following',
    'follower_count' => '%d Followers',
], $common);
