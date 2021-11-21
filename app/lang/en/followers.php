<?php

$common = require "includes/list_common.php";

return array_merge([
    'nav_admin' => 'Admin',
    'nav_following' => 'Following',
    'nav_followers' => 'Followers',
    'nav_back' => 'Back',
    'undo_button' => 'Remove',
], $common);

