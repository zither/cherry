<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <?php $this->insert('includes/nav')?>
    <div  class="flex-grow mt container">
        <?php foreach ($notifications as $v):?>
        <div class="flex-grow flex-row mt">
            <?php if (!empty($v['avatar'])): ?>
                <div class="avatar mr"><img src="<?=$v['avatar']?>" alt="<?=$v['name'] ?: $v['preferred_name']?>"/></div>
            <?php else:?>
                <div class="avatar-none mr" title="<?=$v['name'] ?: $v['preferred_name']?>"></div>
            <?php endif;?>
            <div class="flex-grow">
                <?php if (strtolower($v['type']) === 'follow'): ?>
                    <?php if ($v['status'] === 0): ?>
                        <div><a class="color-black" href="<?=$v['url']?>"><?=$v['name'] ?: $v['preferred_name']?></a> requests to follow you</div>
                        <div>
                            <a class="btn mr" href="/follow-requests/<?=$v['id']?>/answer?action=accept">允许</a>
                            <a class="btn mr" href="/follow-requests/<?=$v['id']?>/answer?action=reject">拒绝</a>
                            <a class="btn mr" href="/follow-requests/<?=$v['id']?>/answer?action=ignore">忽略</a>
                        </div>
                    <?php else: ?>
                        <div><span><?=$v['name']?></span> followed you</div>
                        <div><a class="color-black" href="<?=$v['url']?>"><?=$v['url']?></a></div>
                    <?php endif;?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach;?>
    </div>
</div>