<?php

$common = require 'includes/list_common.php';

return array_merge([
    'editor_button' => 'TOOT',
    'editor_scope_1' => 'Public',
    'editor_scope_2' => 'Unlisted',
    'editor_scope_3' => 'followers-only',
    'editor_scope_4' => 'Direct',
], $common);