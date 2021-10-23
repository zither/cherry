<?php

$common = require 'includes/list_common.php';

return array_merge([
    'index_admin' => '管理',
    'activity_count' => '%d 条嘟文',
    'following_count' => '%d 个关注',
    'follower_count' => '%d 个关注者',
], $common);
