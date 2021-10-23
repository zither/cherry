<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <?php $this->insert('includes/nav')?>
    <div class="mt-2 container">
        <div>
            <?php foreach ($errors as $error):?>
                <span class="color-red"><?=$this->lang('flash_error')?><?=$error?></span>
            <?php endforeach;?>
            <?php foreach ($messages as $message):?>
                <span class="color-green"><?=$this->lang('flash_message')?><?=$message?></span>
            <?php endforeach;?>
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
</div>