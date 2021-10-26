<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <div class="blogs mt-1">
        <?php foreach ($notes as $v): ?>
            <div class="flex-column blog <?=$v['object_id'] == $note_id ? '' : 'mt-1'?>">
                <div class="flex-row">
                    <div class="flex-column flex-grow flex-shrink box">
                        <div class="flex-grow flex-row">
                            <?php if (!empty($v['avatar'])):?>
                                <div class="flex-auto avatar-min mr"><img src="<?=$v['avatar']?>" referrerpolicy="no-referrer"></div>
                            <?php else:?>
                                <div class="flex-auto avatar-min-none mr"></div>
                            <?php endif;?>
                            <div class="flex-auto flex-shrink ellipse mr bold color-black">
                                <a class="color-black no-decoration" href="/timeline?pid=<?=$v['profile_id']?>"><?=$v['name'] ?: $v['preferred_name']?></a>
                            </div>
                            <div class="flex-grow flex-shrink ellipse mr"><a class="color-black no-decoration" href="<?=$v['profile_url']?>"><?=$v['account']?></a></div>
                            <div class="color-purple flex-grow ml text-right nowrap"><?=$v['date']?></div>
                        </div>
                        <div class="flex-grow content mt-1">
                            <?php if (!$v['is_sensitive']): ?>
                                <?=$v['content']?>
                            <?php else:?>
                                <span class="object-summary"><?=$v['summary']?></span>
                                <label class="cw-btn" for="show-content-<?=$v['object_id']?>">
                                    <span><?=$this->lang('show_content')?></span>
                                </label>
                                <input type=radio class="show-content" id="show-content-<?=$v['object_id']?>" name="group-<?=$v['object_id']?>">
                                <label class="cw-btn" for="hide-content-<?=$v['object_id']?>">
                                    <span><?=$this->lang('hide_content')?></span>
                                </label>
                                <input type=radio class="hide-content"  id="hide-content-<?=$v['object_id']?>" name="group-<?=$v['object_id']?>">
                                <div class="object-content"><?=$v['content']?></div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($v['attachments'])): ?>
                            <div class="attachment-box flex-grow flex-row mt">
                                <?php foreach ($v['attachments'] as $i => $image): ?>
                                    <div class="flex-grow-full">
                                        <?php if (strpos($image['media_type'], 'image') !== false): ?>
                                            <a href="#release-target" class="JesterBox">
                                                <div id="object-<?=$v['object_id']?>-<?=$i?>">
                                                    <img src="<?=$image['url']?>" alt="<?=$image['name']?>" referrerpolicy="no-referrer"/>
                                                </div>
                                            </a>
                                            <a href="#object-<?=$v['object_id']?>-<?=$i?>">
                                                <img class="attachment" src="<?=$image['url']?>" alt="<?=$image['name']?>"  referrerpolicy="no-referrer"/>
                                            </a>
                                        <?php elseif (strpos($image['media_type'], 'video') !== false):?>
                                            <video style="width:100%" controls>
                                                <source src="<?=$image['url']?>" type="<?=$image['media_type']?>">
                                            </video>
                                        <?php endif;?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif;?>

                        <div class="flex-grow flex-row others mt-1">
                            <div class="flex-grow flex-row ml color-purple">
                                <div class="flex-grow-full">
                                    <a href="<?=$v['url']?>" title="<?=$this->lang('icon_link')?>">
                                        <div class="inline-block">
                                            <i class="gg-link icon"></i>
                                        </div>
                                    </a>
                                </div>
                                <?php if ($is_admin):?>
                                    <div class="flex-grow-full">
                                        <a href="/objects/<?=$v['object_id']?>/reply" title="<?=$this->lang('icon_reply')?>">
                                            <div class="inline-block">
                                                <i class="gg-corner-double-up-left"></i>
                                            </div>
                                            <?php if ($v['is_local']):?>
                                                <span class="ml"><?=$v['replies']?></span>
                                            <?php endif;?>
                                        </a>
                                    </div>
                                <?php else:?>
                                    <div class="flex-grow-full">
                                        <div class="inline-block">
                                            <i class="gg-corner-double-up-left"></i>
                                        </div>
                                        <?php if ($v['is_local']):?>
                                            <span class="ml"><?=$v['replies']?></span>
                                        <?php endif;?>
                                    </div>
                                <?php endif;?>
                                <?php if ($is_admin): ?>
                                    <div class="flex-grow-full">
                                        <?php if (!$v['is_public']):?>
                                            <div class="inline-block color-gray">
                                                <i class="gg-path-outline"></i>
                                            </div>
                                            <?php if ($v['is_local']):?>
                                                <span class="ml"><?=$v['shares']?></span>
                                            <?php endif;?>
                                        <?php else:?>
                                            <form class="interaction" method="POST" action="/objects/<?=$v['object_id']?>/boost">
                                                <button class="inline-block <?=$v['is_boosted'] ? 'color-green' : 'color-purple' ?>" title="<?=$this->lang('icon_announce')?>">
                                                    <i class="gg-path-outline"></i>
                                                </button>
                                                <?php if ($v['is_local']):?>
                                                    <span class="ml"><?=$v['shares']?></span>
                                                <?php endif;?>
                                            </form>
                                        <?php endif;?>
                                    </div>
                                <?php else:?>
                                    <div class="flex-grow-full">
                                        <?php if (!$v['is_public']):?>
                                            <div class="inline-block <?=$v['is_boosted'] ? 'color-green' : 'color-purple' ?>">
                                                <i class="gg-path-outline"></i>
                                            </div>
                                            <?php if ($v['is_local']):?>
                                                <span class="ml"><?=$v['shares']?></span>
                                            <?php endif;?>
                                        <?php else: ?>
                                            <div class="inline-block <?=$v['is_boosted'] ? 'color-green' : 'color-purple' ?>" title="<?=$this->lang('icon_announce')?>">
                                                <i class="gg-path-outline"></i>
                                            </div>
                                            <?php if ($v['is_local']):?>
                                                <span class="ml"><?=$v['shares']?></span>
                                            <?php endif;?>
                                        <?php endif;?>
                                    </div>
                                <?php endif;?>
                                <?php if ($is_admin):?>
                                    <div class="flex-grow-full">
                                        <form class="interaction" method="POST" action="/objects/<?=$v['object_id']?>/like">
                                            <button class="inline-block <?=$v['is_liked'] ? 'color-green' : 'color-purple' ?>" title="<?=$this->lang('icon_like')?>">
                                                <i class="gg-heart"></i>
                                            </button>
                                            <?php if ($v['is_local']):?>
                                                <span class="ml"><?=$v['likes']?></span>
                                            <?php endif;?>
                                        </form>
                                    </div>
                                <?php else:?>
                                    <div class="flex-grow-full">
                                        <div class="inline-block <?=$v['is_liked'] ? 'color-green' : 'color-purple' ?>" title="<?=$this->lang('icon_like')?>">
                                            <i class="gg-heart"></i>
                                        </div>
                                        <?php if ($v['is_local']):?>
                                            <span class="ml"><?=$v['likes']?></span>
                                        <?php endif;?>
                                    </div>
                                <?php endif;?>
                                <div class="flex-auto">
                                    <div class="more">
                                        <i class="gg-more-alt"> </i>
                                    </div>
                                    <?php if ($is_admin): ?>
                                        <div class="dropdown-menu flex-column">
                                            <?php if ($v['is_local']):?>
                                                <div class="item">
                                                    <form action="/notes/<?=$v['snowflake_id']?>/delete" METHOD="POST">
                                                        <input class="btn" type="submit" value="<?=$this->lang('menu_delete_activity')?>" />
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <div class="item">
                                                    <form action="/profiles/<?=$v['profile_id']?>/fetch" METHOD="POST">
                                                        <input class="btn" type="submit" value="<?=$this->lang('menu_update_profile')?>" />
                                                    </form>
                                                </div>
                                            <?php endif;?>
                                        </div>
                                    <?php endif;?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_admin && $note_id == $v['id'] && !empty($interactions)): ?>
                <div class="mt-1 interactions">
                    <?php foreach ($interactions as $interaction): ?>
                        <div class="interaction-like">
                            <a href="<?=$interaction['url']?>">
                                <?php if (!empty($interaction['avatar'])):?>
                                    <div class="avatar-min">
                                        <img
                                                src="<?=$interaction['avatar']?>"
                                                referrerpolicy="no-referrer"
                                                alt=""
                                                title="<?=$interaction['title']?>"
                                        >
                                    </div>
                                <?php else:?>
                                    <div class="avatar-min-none" title="<?=$interaction['title']?>"> </div>
                                <?php endif;?>
                            </a>
                        </div>
                    <?php endforeach;?>
                </div>
            <?php endif;?>
        <?php endforeach;?>
    </div>
</div>