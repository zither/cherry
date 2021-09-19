<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <?php $this->insert('includes/nav')?>
    <div class="mt-2 container">
        <div>
            <?php foreach ($errors as $error):?>
                <span class="color-red">错误：<?=$error?></span>
            <?php endforeach;?>
            <?php foreach ($messages as $message):?>
                <span class="color-green">消息：<?=$message?></span>
            <?php endforeach;?>
        </div>
        <form class="mt" action="/profiles/<?=$profile['id']?>/update" method="POST">
            <div class="mt label">
                <label for="name">昵称</label>
                <input id="name" type="text" name="name" placeholder="昵称" value="<?=$profile['name']?>" />
            </div>
            <div class="mt label">
                <label for="avatar">头像</label>
                <input id="avatar" type="text" name="avatar"  placeholder="外链地址" value="<?=$profile['avatar']?>"/>
            </div>
            <div class="mt label">
                <label for="summary">简介</label>
                <textarea id="summary" name="summary" rows="5"><?=$profile['summary']?></textarea>
            </div>
            <button class="mt">保存更改</button>
        </form>
    </div>
</div>