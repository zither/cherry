<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <div class="flex-auto flex flex-row header nav">
        <div class="bold mr"><?=$this->lang('nav_admin')?></div>
        <div class="mr">
            <a href="/following"><?=$this->lang('nav_following')?></a>
        </div>
        <div class="mr">
            <a href="/followers"><?=$this->lang('nav_followers')?></a>
        </div>
        <div>
            <a href="/timeline"><?=$this->lang('nav_back')?></a>
        </div>
    </div>
    <div class="mt-1 container">
        <?php foreach ($followers as $v): ?>
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
                            <a class="no-decoration" href="/timeline?pid=<?=$v['profile_id']?>"><?=$v['name'] ?: $v['preferred_name']?></a>
                        </div>
                        <div>
                            <a class="no-decoration" href="<?=$v['url']?>" target="_blank">
                                @<?=$v['account']?>
                            </a>
                        </div>
                    </div>
                    <div class="flex flex-align-center">
                        <form action="/followers/<?=$v['id']?>/delete" method="POST">
                            <button><?=$this->lang('undo_button')?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach;?>
    </div>

    <?php if (($prev || $next) && !empty($followers)): ?>
        <div class="flex-row mt-1">
            <div class="navigator  flex-grow-full text-center mr">
                <?php if ($prev):?>
                    <a class="color-purple no-decoration" href="/followers?page=<?=$prev?>"><?=$this->lang('pagination_prev')?></a>
                <?php else: ?>
                    <span class=""><?=$this->lang('pagination_prev')?></span>
                <?php endif;?>
            </div>
            <div class="flex-grow-full text-center navigator ml">
                <?php if ($next):?>
                    <a class="color-purple no-decoration" href="/followers?page=<?=$next?>"><?=$this->lang('pagination_next')?></a>
                <?php else: ?>
                    <span class=""><?=$this->lang('pagination_next')?></span>
                <?php endif;?>
            </div>
        </div>
    <?php endif;?>
</div>
