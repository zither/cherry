<?php $this->layout('layout') ?>

<div class="flex flex-column">
   <div class="blogs mt-1">
        <?php foreach ($notes as $v): ?>
            <div class="flex-column blog mt-1">
                <div class="flex-row">
                    <div class="flex-column flex-grow flex-shrink box">
                        <div class="flex-grow flex-row">
                            <?php if (!empty($v['avatar'])):?>
                                <div class="flex-auto avatar-min mr"><img src="<?=$v['avatar']?>" referrerpolicy="no-referrer"></div>
                            <?php else:?>
                                <div class="flex-auto avatar-min-none mr"></div>
                            <?php endif;?>
                            <div class="flex-auto bold mr color-black"><?=$v['name'] ?: $v['preferred_name']?></div>
                            <div class="flex-auto flex-shrink ellipse mr"><a class="color-black no-decoration" href="<?=$v['profile_url']?>"><?=$v['account']?></a></div>
                            <div class="color-purple flex-grow ml mr text-right"><?=$v['date']?></div>
                        </div>
                        <div class="flex-grow content mt-1 mb-1"><?=$v['content']?></div>
                        <div class="flex-grow flex-row others">
                            <div class="flex-grow flex-row ml color-purple">
                                <div class="flex-grow">
                                    <a href="<?=$v['url']?>" title="链接">
                                        <div class="inline-block mr">
                                            <i class="gg-link icon"></i>
                                        </div>
                                    </a>
                                </div>
                                <?php if ($v['is_local']):?>
                                    <?php if ($v['replies'] > -1):?>
                                        <div class="flex-grow ">
                                            <a href="/" title="回复">
                                                <div class="inline-block mr">
                                                    <i class="gg-corner-double-up-left"></i>
                                                </div>
                                                <?=$v['replies']?>
                                            </a>
                                        </div>
                                    <?php endif;?>
                                    <?php if ($v['shares'] > -1):?>
                                        <div class="flex-grow">
                                            <a href="/" title="转嘟">
                                                <div class="inline-block mr">
                                                    <i class="gg-path-outline"></i>
                                                </div>
                                                <span><?=$v['shares']?></span>
                                            </a>
                                        </div>
                                    <?php endif;?>
                                    <?php if ($v['likes'] > -1):?>
                                        <div class="flex-grow">
                                            <a href="/" title="喜欢">
                                                <div class="inline-block mr">
                                                    <i class="gg-heart"></i>
                                                </div>
                                                <span><?=$v['likes']?></span>
                                            </a>
                                        </div>
                                    <?php endif;?>
                                <?php endif;?>
                                <?php if ($is_admin && $v['is_local']): ?>
                                    <div class="flex-auto mr">
                                        <a href="/notes/<?=$v['snowflake_id']?>/delete" title="删除">
                                            <div class="inline-block">
                                                <i class="gg-trash"></i>
                                            </div>
                                        </a>
                                    </div>
                                <?php endif;?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach;?>
    </div>
</div>