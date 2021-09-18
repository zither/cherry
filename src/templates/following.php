<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <div class="flex-auto flex flex-row header nav">
        <div class="bold mr">管理</div>
        <div class="mr">
            <a href="/web/following">关注</a>
        </div>
        <div class="mr">
            <a href="/web/followers">关注者</a>
        </div>
        <div>
            <a href="/timeline">返回</a>
        </div>
    </div>

    <div class="mt-1 container">
        <div>
            <?php foreach ($errors as $error):?>
                <span class="color-red">错误：<?=$error?></span>
            <?php endforeach;?>
            <?php foreach ($messages as $message):?>
                <span class="color-green">消息：<?=$message?></span>
            <?php endforeach;?>
        </div>
        <form action="/following" method="POST">
            <label>关注帐号：
                <input type="text" name="account" />
            </label>
            <button>发送请求</button>
        </form>
    </div>

    <div class="mt-1 container">
        <?php foreach ($following as $v): ?>
            <div class="flex-column line mt ml">
                <div class="flex-row">
                    <?php if (!empty($v['avatar'])): ?>
                        <div class="avatar mr">
                            <img src="<?=$v['avatar']?>">
                        </div>
                    <?php else: ?>
                        <div class="avatar-none mr">
                        </div>
                    <?php endif;?>
                    <div class="flex-grow flex-column">
                        <div>
                            <a class="color-black no-decoration" href="/timeline?pid=<?=$v['profile_id']?>"><?=$v['name'] ?: $v['preferred_name']?></a>
                        </div>
                        <div>
                            <a class="color-purple no-decoration" href="<?=$v['url']?>" target="_blank">
                                @<?=$v['account']?>
                            </a>
                        </div>
                    </div>
                    <div class="flex flex-align-center">
                        <form action="/following/<?=$v['id']?>/delete" method="POST">
                            <button>取消</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach;?>
    </div>
</div>