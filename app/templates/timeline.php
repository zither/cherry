<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <?php $this->insert('includes/nav')?>
    <div class="blogs mt-1">
        <?php foreach ($blogs as $v): ?>
            <?php if ($v['show_boosted']):?>
                <div class="flex-grow bg-color-gray mt-1 color-purple blog-header">
                    <a href="<?=$v['activity_profile_url']?>">
                        <?=$this->lang('header_announce', $v['activity_name'] ?: $v['activity_preferred_name'])?>
                    </a>
                </div>
            <?php elseif (!empty($v['parent_profile'])): ?>
                <div class="flex-grow bg-color-gray mt-1 color-purple blog-header">
                    <a href="<?=$v['parent_profile']['raw_object_id']?>">
                        <?=$this->lang('header_reply', $v['parent_profile']['name'] ?: $v['parent_profile']['preferred_name'])?>
                    </a>
                </div>
            <?php else: ?>
                <div class="flex-grow mt-1">
                </div>
            <?php endif;?>

            <div class="flex-column blog">
                <div class="flex-row">
                    <div class="flex-column flex-grow flex-shrink box">
                        <div class="content-header flex-grow flex-row">
                            <?php if (!empty($v['avatar'])):?>
                                <div class="flex-auto avatar-min mr"><img src="<?=$v['avatar']?>" referrerpolicy="no-referrer"></div>
                            <?php else:?>
                                <div class="flex-auto avatar-min-none mr"></div>
                            <?php endif;?>
                            <div class="content-header-name flex-auto flex-shrink ellipse mr bold">
                                <a class="no-decoration" href="/timeline?pid=<?=$v['profile_id']?>">
                                    <?=$v['name'] ?: $v['preferred_name']?>
                                </a>
                            </div>
                            <div class="content-header-account flex-grow flex-shrink ellipse mr"><a class="no-decoration" href="<?=$v['profile_url']?>"><?=$v['account']?></a></div>
                            <div class="content-header-date flex-grow ml text-right nowrap">
                                <?php if (!empty($v['relative_time'])):?>
                                    <?=$v['relative_time']['time'] . $this->lang($v['relative_time']['unit'])?>
                                <?php else :?>
                                    <?=$v['date']?>
                                <?php endif;?>
                            </div>
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
                            <?php if (!empty($v['poll'])): ?>
                                <div class="poll">
                                <?php if ($v['poll']['is_voted'] || $v['poll']['is_closed']):?>
                                    <ul>
                                        <?php foreach ($v['poll']['choices'] as $choice) :?>
                                            <li class="choice">
                                                <?=$choice['percent']?>% <?=$choice['name']?>
                                                <?php if ($choice['selected']):?>
                                                    <span class="icon">✔</span>
                                                <?php endif;?>
                                            </li>
                                            <li class="progress <?=$choice['selected'] ? 'selected' : ''?>" style="width: <?=$choice['percent'] ?: 1?>%"></li>
                                        <?php endforeach;?>
                                    </ul>
                                <?php else:?>
                                    <form method="POST" action="/web/polls/<?=$v['poll']['id']?>/vote">
                                        <?php foreach ($v['poll']['choices'] as $i => $choice) :?>
                                            <div class="choice">
                                                <label>
                                                    <input
                                                            type="<?=$v['poll']['multiple'] ? 'checkbox' : 'radio'?>"
                                                            name="choice<?=$v['poll']['multiple'] ? '[]':''?>"
                                                            value="<?=$choice['name']?>"
                                                    >
                                                    <?=$choice['name']?>
                                                </label>
                                            </div>
                                        <?php endforeach;?>
                                        <button class="mt mb"  <?=$is_admin ? '' : 'disabled'?>>投票</button>
                                    </form>
                                <?php endif; ?>
                                    <span class="details inline-block mb">
                                        <?=$this->lang('poll_voters_count', $v['poll']['voters_count']);?>
                                        ·
                                        <?php if ($v['poll']['is_closed']):?>
                                            <?=$this->lang('poll_closed');?>
                                        <?php else:?>
                                            <?=$this->lang(
                                                'poll_end_time',
                                                $v['poll']['time_left'],
                                                $this->lang(
                                                    $v['poll']['time_left_type'],
                                                    $v['poll']['time_left'] > 1
                                                )
                                            );?>
                                        <?php endif;?>
                                    </span>
                                </div>
                            <?php endif;?>
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
                                    <?php if ($i + 1 < count($v['attachments'])):?>
                                        <div class="flex-gap"></div>
                                    <?php endif;?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif;?>

                        <div class="content-others flex-grow flex-row  mt-1">
                            <div class="flex-grow flex-row ml">
                                <div class="flex-grow-full">
                                    <a href="<?=$v['url']?>" title="<?=$this->lang('icon_link')?>">
                                        <div class="inline-block">
                                            <i class="gg-link icon"></i>
                                        </div>
                                    </a>
                                </div>
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
                                <div class="flex-grow-full">
                                    <?php if (!$v['is_public']):?>
                                        <div class="inline-block">
                                            <i class="gg-path-outline disabled"></i>
                                        </div>
                                        <?php if ($v['is_local']):?>
                                            <span class="ml"><?=$v['shares']?></span>
                                        <?php endif;?>
                                    <?php else:?>
                                        <form class="interaction" method="POST" action="/objects/<?=$v['object_id']?>/boost">
                                            <button class="inline-block <?=$v['is_boosted'] ? 'toggled' : '' ?>" title="<?=$this->lang('icon_announce')?>">
                                                <i class="gg-path-outline"></i>
                                            </button>
                                            <?php if ($v['is_local']):?>
                                                <span class="ml"><?=$v['shares']?></span>
                                            <?php endif;?>
                                        </form>
                                    <?php endif;?>
                                </div>
                                <div class="flex-grow-full">
                                    <form class="interaction" method="POST" action="/objects/<?=$v['object_id']?>/like">
                                        <button class="inline-block <?=$v['is_liked'] ? 'toggled' : '' ?>" title="<?=$this->lang('icon_like')?>">
                                            <i class="gg-heart"></i>
                                        </button>
                                        <?php if ($v['is_local']):?>
                                            <span class="ml"><?=$v['likes']?></span>
                                        <?php endif;?>
                                    </form>
                                </div>
                                <div class="flex-auto">
                                    <div class="more">
                                        <i class="gg-more-alt"> </i>
                                    </div>
                                    <div class="dropdown-menu flex-column">
                                        <div class="item">
                                            <a href="/web/threads/<?=$v['object_id']?>"><?=$this->lang('menu_expand_thread')?></a>
                                        </div>
                                        <div class="item">
                                            <form action="/profiles/<?=$v['profile_id']?>/fetch" METHOD="POST">
                                                <input class="btn" type="submit" value="<?=$this->lang('menu_update_profile')?>" />
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach;?>
    </div>

    <?php if ($prev || $next): ?>
    <div class="flex-row mt-1">
        <div class="navigator  flex-grow-full text-center mr">
            <?php if ($prev):?>
                <a class="color-purple no-decoration" href="/timeline?<?=$prev?>"><?=$this->lang('pagination_prev')?></a>
            <?php else: ?>
                <span class=""><?=$this->lang('pagination_prev')?></span>
            <?php endif;?>
        </div>
        <div class="flex-grow-full text-center navigator ml">
            <?php if ($next):?>
                <a class="color-purple no-decoration" href="/timeline?<?=$next?>"><?=$this->lang('pagination_next')?></a>
            <?php else: ?>
                <span class=""><?=$this->lang('pagination_next')?></span>
            <?php endif;?>
        </div>
    </div>
    <?php endif;?>
</div>