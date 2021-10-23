<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <div class="header">
        <div class="title">
            <h1 class="mr"><?=$this->lang('header_title')?></h1>
        </div>
    </div>
    <div class="mt-2 container">
        <div>
            <?php foreach ($errors as $error):?>
                <span class="color-red"><?=$this->lang('flash_error')?><?=$error?></span>
            <?php endforeach;?>
            <?php foreach ($messages as $message):?>
                <span class="color-green"><?=$this->lang('flash_message')?><?=$message?></span>
            <?php endforeach;?>
        </div>
        <form class="mt" action="/init" method="POST">
            <div class="label">
                <label for="domain"><?=$this->lang('form_domain_label')?></label>
                <input id="domain" type="text" name="domain" placeholder="<?=$this->lang('form_domain_placeholder')?>" />
            </div>
            <div class="mt label">
                <label for="name"><?=$this->lang('form_name_label')?></label>
                <input id="name" type="text" name="name" placeholder="<?=$this->lang('form_name_placeholder')?>" />
            </div>
            <div class="mt label">
                <label for="preferred_name"><?=$this->lang('form_preferred_name_label')?></label>
                <input id="preferred_name" type="text" name="preferred_name"  placeholder="<?=$this->lang('form_preferred_name_placeholder')?>"/>
            </div>
            <div class="mt label">
                <label for="avatar"><?=$this->lang('form_avatar_label')?></label>
                <input id="avatar" type="text" name="avatar"  placeholder="<?=$this->lang('form_avatar_placeholder')?>"/>
            </div>
            <div class="mt label">
                <label for="summary"><?=$this->lang('form_summary_label')?></label>
                <input id="summary" type="text" name="summary"  placeholder="<?=$this->lang('form_summary_placeholder')?>"/>
            </div>
            <div class="mt label">
                <label for="password"><?=$this->lang('form_password_label')?></label>
                <input id="password" type="password" name="password" placeholder="********"/>
            </div>
            <div class="mt label">
                <label for="confirm_password"><?=$this->lang('form_confirm_password_label')?></label>
                <input id="confirm_password" type="password" name="confirm_password" placeholder="********" />
            </div>
            <button class="mt"><?=$this->lang('form_button')?></button>
        </form>
    </div>
</div>