<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <div class="header">
        <div class="title">
            <h1 class="mr"><?=$this->lang('header_title')?></h1>
            <span class="mr">@<?=$profile['account']?></span>
        </div>
    </div>
    <div class="mt-2 container">
        <div>
            <?php foreach ($errors as $error):?>
                <span class="error mb"><?=$this->lang('flash_error')?><?=$error?></span>
            <?php endforeach;?>
        </div>
        <form action="/login" method="POST">
            <label><?=$this->lang('form_password_label')?>
            <input type="password" name="password" />
            </label>
            <button><?=$this->lang('form_button')?></button>
        </form>
    </div>
</div>