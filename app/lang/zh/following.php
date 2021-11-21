<?php

$common = require "includes/list_common.php";

return array_merge([
    'nav_admin' => '管理',
    'nav_following' => '我关注的',
    'nav_followers' => '关注我的',
    'nav_back' => '返回',
    'flash_error' => '错误：',
    'flash_message' => '消息：',
    'form_label' => '关注帐号：',
    'form_button' => '发送请求',
    'undo_button' => '取消关注',
], $common);

