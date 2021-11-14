<?php


$common = require 'includes/list_common.php';

return array_merge([
    'editor_btn' => 'TOOT',
    'editor_scope_1' => 'Public',
    'editor_scope_2' => 'Unlisted',
    'editor_scope_3' => 'Followers-only',
    'editor_scope_4' => 'Direct',
    'warning_placeholder' => 'Content warning',
    'content_placeholder' => 'What\'s on your mind?',
], $common);
