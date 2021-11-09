<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <?php $this->insert('includes/nav')?>

    <?php if (!empty($errors) || !empty($messages)):?>
        <div class="mt-2">
            <?php foreach ($errors as $error):?>
                <span class="error"><?=$this->lang('flash_error')?><?=$error?></span>
            <?php endforeach;?>
            <?php foreach ($messages as $message):?>
                <span class="message"><?=$this->lang('flash_message')?><?=$message?></span>
            <?php endforeach;?>
        </div>
    <?php endif;?>

    <div class="container mt">
        <div class="bold">
            <span><?=$this->lang('profile_title')?></span>
        </div>
        <form class="mt" action="/profiles/<?=$profile['id']?>/update" method="POST">
            <div class="mt label">
                <label for="name"><?=$this->lang('form_name_label')?></label>
                <input id="name" type="text" name="name" placeholder="<?=$this->lang('form_name_placeholder')?>" value="<?=$profile['name']?>" />
            </div>
            <div class="mt label">
                <label for="avatar"><?=$this->lang('form_avatar_label')?></label>
                <input id="avatar" type="text" name="avatar"  placeholder="<?=$this->lang('form_avatar_placeholder')?>" value="<?=$profile['avatar']?>"/>
            </div>
            <div class="mt label">
                <label for="summary"><?=$this->lang('form_summary_label')?></label>
                <textarea id="summary" name="summary" rows="5"><?=$profile['summary']?></textarea>
            </div>
            <button class="mt"><?=$this->lang('form_button')?></button>
        </form>
    </div>

    <div class="container mt">
        <div class="bold">
            <span><?=$this->lang('preference_title')?></span>
        </div>
        <form class="mt" action="/web/preferences/update" method="POST">
            <div class="mt label">
                <label for="lock_site"><?=$this->lang('form_lock_site_label')?></label>
                <input id="lock_site" type="checkbox" name="lock_site"  value="1" <?=($settings['lock_site'] ?? false) ? 'checked' :''?> />
            </div>
            <div class="mt label">
                <label for="language"><?=$this->lang('form_language_label')?></label>
                <select id="language" name="language">
                    <?php foreach ($languages as $language): ?>
                        <option value="<?=$language?>" <?=$language === $settings['language'] ? 'selected' : ''?>><?=$language?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="mt label">
                <label for="theme"><?=$this->lang('form_theme_label')?></label>
                <select id="theme" name="theme">
                    <?php foreach ($themes as $theme): ?>
                        <option value="<?=$theme?>" <?=$theme === $settings['theme'] ? 'selected' : ''?>><?=$theme?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="mt label">
                <label for="group_activities"><?=$this->lang('form_group_activities_label')?></label>
                <input id="group_activities" type="checkbox" name="group_activities" value="1" <?=($settings['group_activities'] ?? false) ? 'checked' :''?> />
            </div>
            <button class="mt"><?=$this->lang('form_button')?></button>
        </form>
    </div>
</div>