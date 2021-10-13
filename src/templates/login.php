<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <div class="header">
        <div class="title">
            <h1 class="mr">登录</h1>
            <span class="mr">@<?=$profile['account']?></span>
        </div>
    </div>
    <div class="mt-2 container">
        <div>
            <?php foreach ($errors as $error):?>
                <span class="color-red mb">错误：<?=$error?></span>
            <?php endforeach;?>
        </div>
        <form action="/login" method="POST">
            <label>请输入密码：
            <input type="password" name="password" />
            </label>
            <button>登录</button>
        </form>
    </div>
</div>