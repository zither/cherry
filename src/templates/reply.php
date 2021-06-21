<?php $this->layout('layout') ?>

<div class="flex flex-column">

    <div class="mt-1">
        <div class="flex-column blog mt-1">
            <div class="flex-row">
                <div class="flex-column flex-grow flex-shrink box">
                    <div class="flex-grow flex-row">
                        <div class="flex-auto avatar-min mr"><img src="<?=$note['avatar']?>"></div>
                        <div class="flex-auto bold mr color-black"><?=$note['name'] ?: $note['preferred_name']?></div>
                        <div class="flex-auto flex-shrink ellipse mr"><a class="color-black no-decoration" href="<?=$note['profile_url']?>"><?=$note['account']?></a></div>
                        <div class="color-purple flex-grow ml mr text-right"><?=$note['date']?></div>
                    </div>
                    <div class="flex-grow content mt-1 mb-1"><?=$note['content']?></div>
                    <div class="flex-grow flex-row others">
                        <div class="flex-grow flex-row ml color-purple">
                            <div class="flex-grow flex-row ml color-purple">
                                <div class="flex-grow-full">
                                    <a href="<?=$note['url']?>" title="链接">
                                        <div class="inline-block">
                                            <i class="gg-link icon"></i>
                                        </div>
                                    </a>
                                </div>
                                <?php if ($note['replies'] > 0):?>
                                    <div class="flex-grow-full color-gray">
                                        <div class="inline-block" title="回复">
                                            <i class="gg-corner-double-up-left"></i>
                                        </div>
                                        <?php if ($note['is_local']):?>
                                            <span class="ml"><?=$note['replies']?></span>
                                        <?php endif;?>
                                    </div>
                                <?php endif;?>
                                <?php if ($note['shares'] > 0):?>
                                    <div class="flex-grow-full">
                                        <div class="inline-block color-purple">
                                            <i class="gg-path-outline"></i>
                                        </div>
                                        <?php if ($note['is_local']):?>
                                            <span class="ml"><?=$note['shares']?></span>
                                        <?php endif;?>
                                    </div>
                                <?php endif;?>
                                <?php if ($note['likes'] > 0):?>
                                    <div class="flex-grow-full">
                                        <form class="interaction" method="POST" action="/liked/<?=$note['id']?>">
                                            <button class="inline-block <?=$note['is_liked'] ? 'color-green' : 'color-purple' ?>" title="喜欢">
                                                <i class="gg-heart"></i>
                                            </button>
                                            <?php if ($note['is_local']):?>
                                                <span class="ml"><?=$note['likes']?></span>
                                            <?php endif;?>
                                        </form>
                                    </div>
                                <?php endif;?>
                                <div class="flex-auto">
                                    <?php if ($note['show_boosted']):?>
                                        <div class="avatar-min" title="Boosted by <?=$note['activity_name']?>">
                                            <?php if ($note['activity_avatar']): ?>
                                                <a href="<?=$note['activity_profile_url']?>"><img src="<?=$note['activity_avatar']?>" alt="<?=$note['activity_name']?>"></a>
                                            <?php else:?>
                                                <a href="<?=$v['activity_profile_url']?>"><?=$v['activity_name']?></a>
                                            <?php endif;?>
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar-min-none">
                                        </div>
                                    <?php endif;?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt">
            <form action="/create" method="POST">
                <input name="in_reply_to" type="hidden" value="<?=$note['id']?>"/>
                <div class="mb">
                    <textarea class="color-purple" name="content" rows="10"><?=$at?></textarea>
                </div>
                <div class="flex-row">
                    <div class="flex-grow-full">
                        <select name="scope" class="scope">
                            <option value="1">公开</option>
                            <option value="2">不公开</option>
                            <option value="3">仅关注者</option>
                            <option value="4">私信</option>
                        </select>
                    </div>
                    <div class="flex-grow-full text-right">
                        <button>嘟嘟</button>
                    </div>
            </form>
        </div>
    </div>
</div>