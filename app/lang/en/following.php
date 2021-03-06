<?php

$common = require "includes/list_common.php";

return array_merge([
    'nav_admin' => 'Admin',
    'nav_following' => 'Following',
    'nav_followers' => 'Followers',
    'nav_back' => 'Back',
    'flash_error' => 'Error:',
    'flash_message' => 'message:',
    'form_label' => 'Follow account:',
    'form_button' => 'Send',
    'undo_button' => 'Unfollow',
], $common);

