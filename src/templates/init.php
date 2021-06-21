<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <div class="header">
        <div class="title">
            <h1 class="mr">设置信息</h1>
        </div>
    </div>
    <div class="mt-2 container">
        <div>
            <?php foreach ($errors as $error):?>
                <span class="color-red">错误：<?=$error?></span>
            <?php endforeach;?>
            <?php foreach ($messages as $message):?>
                <span class="color-green">消息：<?=$message?></span>
            <?php endforeach;?>
        </div>
        <form class="mt" action="/init" method="POST">
            <div class="label">
                <label for="domain">域名</label>
                <input id="domain" type="text" name="domain" placeholder="www.example.com" />
            </div>
            <div class="mt label">
                <label for="name">用户昵称</label>
                <input id="name" type="text" name="name" placeholder="昵称" />
            </div>
            <div class="mt label">
                <label for="preferred_name">用户名</label>
                <input id="preferred_name" type="text" name="preferred_name"  placeholder="a-z"/>
            </div>
            <div class="mt label">
                <label for="avatar">头像</label>
                <input id="avatar" type="text" name="avatar"  placeholder="外链"/>
            </div>
            <div class="mt label">
                <label for="summary">简介</label>
                <input id="summary" type="text" name="avatar"  placeholder="简介"/>
            </div>
            <div class="mt label">
                <label for="password">登录密码</label>
                <input id="password" type="password" name="password" placeholder="********"/>
            </div>
            <div class="mt label">
                <label for="confirm_password">确认密码</label>
                <input id="confirm_password" type="password" name="confirm_password" placeholder="********" />
            </div>
            <button class="mt">提交</button>
        </form>
    </div>
</div>