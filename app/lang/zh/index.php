<?php

$common = require 'includes/list_common.php';

return array_merge([
    'index_admin' => '管理',
    'activity_count' => '%d 条嘟文',
    'following_count' => '关注 %d 人',
    'follower_count' => '被 %d 人关注',
], $common);
