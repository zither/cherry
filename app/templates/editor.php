<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <?php $this->insert('includes/nav')?>

    <?php if (!empty($note)): ?>
    <div class="flex-column blog mt">
        <div class="flex-row">
            <div class="flex-column flex-grow flex-shrink box">
                <div class="content-header flex-grow flex-row">
                    <?php if (!empty($note['avatar'])):?>
                        <div class="flex-auto avatar-min mr"><img src="<?=$note['avatar']?>" referrerpolicy="no-referrer"></div>
                    <?php else:?>
                        <div class="flex-auto avatar-min-none mr"></div>
                    <?php endif;?>
                    <div class="content-header-name flex-auto flex-shrink ellipse mr bold">
                        <?php if ($is_admin):?>
                            <a class="no-decoration" href="/timeline?pid=<?=$note['profile_id']?>">
                                <?=$note['name'] ?: $note['preferred_name']?>
                            </a>
                        <?php else: ?>
                            <?=$note['name'] ?: $note['preferred_name']?>
                        <?php endif;?>
                    </div>
                    <div class="content-header-account flex-grow flex-shrink ellipse mr"><a class="no-decoration" href="<?=$note['profile_url']?>"><?=$note['account']?></a></div>
                    <div class="content-header-date flex-grow ml text-right nowrap"><?=$note['date']?></div>
                </div>
                <div class="flex-grow content mt-1">
                    <?php if (!$note['is_sensitive']): ?>
                        <?=$note['content']?>
                    <?php else:?>
                        <span class="object-summary"><?=$note['summary']?></span>
                        <label class="cw-btn" for="show-content-<?=$note['object_id']?>">
                            <span><?=$this->lang('show_content')?></span>
                        </label>
                        <input type=radio class="show-content" id="show-content-<?=$note['object_id']?>" name="group-<?=$note['object_id']?>">
                        <label class="cw-btn" for="hide-content-<?=$note['object_id']?>">
                            <span><?=$this->lang('hide_content')?></span>
                        </label>
                        <input type=radio class="hide-content"  id="hide-content-<?=$note['object_id']?>" name="group-<?=$note['object_id']?>">
                        <div class="object-content"><?=$note['content']?></div>
                    <?php endif; ?>
                    <?php if (!empty($note['poll'])): ?>
                        <div class="poll">
                            <?php if ($note['poll']['is_voted'] || $note['poll']['is_closed']):?>
                                <ul>
                                    <?php foreach ($note['poll']['choices'] as $choice) :?>
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
                                <form method="POST" action="/web/polls/<?=$note['poll']['id']?>/vote">
                                    <?php foreach ($note['poll']['choices'] as $i => $choice) :?>
                                        <div class="choice">
                                            <label>
                                                <input
                                                        type="<?=$note['poll']['multiple'] ? 'checkbox' : 'radio'?>"
                                                        name="choice<?=$note['poll']['multiple'] ? '[]':''?>"
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
                                <?=$this->lang('poll_voters_count', $note['poll']['voters_count']);?>
                                ·
                                <?php if ($note['poll']['is_closed']):?>
                                    <?=$this->lang('poll_closed');?>
                                <?php else:?>
                                    <?=$this->lang(
                                        'poll_end_time',
                                        $note['poll']['time_left'],
                                        $this->lang(
                                            $note['poll']['time_left_type'],
                                            $note['poll']['time_left'] > 1
                                        )
                                    );?>
                                <?php endif;?>
                            </span>
                        </div>
                    <?php endif;?>
                </div>

                <?php if (!empty($note['attachments'])): ?>
                    <div class="attachment-box flex-grow flex-row mt">
                        <?php foreach ($note['attachments'] as $i => $image): ?>
                            <div class="flex-grow-full">
                                <?php if (strpos($image['media_type'], 'image') !== false): ?>
                                    <a href="#release-target" class="JesterBox">
                                        <div id="object-<?=$note['object_id']?>-<?=$i?>">
                                            <img src="<?=$image['url']?>" alt="<?=$image['name']?>" referrerpolicy="no-referrer"/>
                                        </div>
                                    </a>
                                    <a href="#object-<?=$note['object_id']?>-<?=$i?>">
                                        <img class="attachment" src="<?=$image['url']?>" alt="<?=$image['name']?>"  referrerpolicy="no-referrer"/>
                                    </a>
                                <?php elseif (strpos($image['media_type'], 'video') !== false):?>
                                    <video style="width:100%" controls>
                                        <source src="<?=$image['url']?>" type="<?=$image['media_type']?>">
                                    </video>
                                <?php endif;?>
                            </div>
                            <?php if ($i + 1 < count($note['attachments'])):?>
                                <div class="flex-gap"></div>
                            <?php endif;?>
                        <?php endforeach; ?>
                    </div>
                <?php endif;?>

                <div class="content-others flex-grow flex-row mt-1">
                    <div class="flex-grow flex-row ml color-purple">
                        <div class="flex-grow-full">
                            <a href="<?=$note['url']?>" title="<?=$this->lang('icon_link')?>">
                                <div class="inline-block">
                                    <i class="gg-link icon"></i>
                                </div>
                            </a>
                        </div>
                        <?php if ($is_admin):?>
                            <div class="flex-grow-full">
                                <a href="/objects/<?=$note['object_id']?>/editor" title="<?=$this->lang('icon_reply')?>">
                                    <div class="inline-block">
                                        <i class="gg-corner-double-up-left"></i>
                                    </div>
                                    <?php if ($note['is_local']):?>
                                        <span class="ml"><?=$note['replies']?></span>
                                    <?php endif;?>
                                </a>
                            </div>
                        <?php else:?>
                            <div class="flex-grow-full">
                                <div class="inline-block">
                                    <i class="gg-corner-double-up-left"></i>
                                </div>
                                <?php if ($note['is_local']):?>
                                    <span class="ml"><?=$note['replies']?></span>
                                <?php endif;?>
                            </div>
                        <?php endif;?>
                        <?php if ($is_admin): ?>
                            <div class="flex-grow-full">
                                <?php if (!$note['is_public']):?>
                                    <div class="inline-block disabled">
                                        <i class="gg-path-outline"></i>
                                    </div>
                                    <?php if ($note['is_local']):?>
                                        <span class="ml"><?=$note['shares']?></span>
                                    <?php endif;?>
                                <?php else:?>
                                    <form class="interaction" method="POST" action="/objects/<?=$note['object_id']?>/boost">
                                        <button class="inline-block <?=$note['is_boosted'] ? 'toggled' : '' ?>" title="<?=$this->lang('icon_announce')?>">
                                            <i class="gg-path-outline"></i>
                                        </button>
                                        <?php if ($note['is_local']):?>
                                            <span class="ml"><?=$note['shares']?></span>
                                        <?php endif;?>
                                    </form>
                                <?php endif;?>
                            </div>
                        <?php else:?>
                            <div class="flex-grow-full">
                                <?php if (!$note['is_public']):?>
                                    <div class="inline-block disabled">
                                        <i class="gg-path-outline"></i>
                                    </div>
                                    <?php if ($note['is_local']):?>
                                        <span class="ml"><?=$note['shares']?></span>
                                    <?php endif;?>
                                <?php else: ?>
                                    <div class="inline-block <?=$note['is_boosted'] ? 'toggled' : '' ?>" title="<?=$this->lang('icon_announce')?>">
                                        <i class="gg-path-outline"></i>
                                    </div>
                                    <?php if ($note['is_local']):?>
                                        <span class="ml"><?=$note['shares']?></span>
                                    <?php endif;?>
                                <?php endif;?>
                            </div>
                        <?php endif;?>
                        <?php if ($is_admin):?>
                            <div class="flex-grow-full">
                                <form class="interaction" method="POST" action="/objects/<?=$note['object_id']?>/like">
                                    <button class="inline-block <?=$note['is_liked'] ? 'toggled' : '' ?>" title="<?=$this->lang('icon_like')?>">
                                        <i class="gg-heart"></i>
                                    </button>
                                    <?php if ($note['is_local']):?>
                                        <span class="ml"><?=$note['likes']?></span>
                                    <?php endif;?>
                                </form>
                            </div>
                        <?php else:?>
                            <div class="flex-grow-full">
                                <div class="inline-block <?=$note['is_liked'] ? 'toggled' : '' ?>" title="<?=$this->lang('icon_like')?>">
                                    <i class="gg-heart"></i>
                                </div>
                                <?php if ($note['is_local']):?>
                                    <span class="ml"><?=$note['likes']?></span>
                                <?php endif;?>
                            </div>
                        <?php endif;?>
                        <div class="flex-auto">
                            <div class="more">
                                <i class="gg-more-alt"> </i>
                            </div>
                            <?php if ($is_admin): ?>
                                <div class="dropdown-menu flex-column">
                                    <?php if ($note['is_local']):?>
                                        <div class="item">
                                            <form action="/notes/<?=$note['snowflake_id']?>/delete" METHOD="POST">
                                                <input class="btn" type="submit" value="<?=$this->lang('menu_delete_activity')?>" />
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="item">
                                            <form action="/profiles/<?=$note['profile_id']?>/fetch" METHOD="POST">
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
    <?php endif;?>

    <div  class="flex-grow mt">
        <form  action="/editor" method="POST">
            <input name="in_reply_to" type="hidden" value="<?=$note['id'] ?? ''?>"/>
            <div class="mb">
                <textarea name="summary" rows="1" placeholder="<?=$this->lang('warning_placeholder');?>"></textarea>
            </div>
            <div class="mb">
                <textarea name="content" rows="10" placeholder="<?=$this->lang('content_placeholder');?>"><?=$at ?? ''?></textarea>
            </div>
            <div class="flex-row">
                <div class="flex-grow-full">
                    <select name="scope" class="scope">
                        <option value="1"><?=$this->lang('editor_scope_1')?></option>
                        <option value="2"><?=$this->lang('editor_scope_2')?></option>
                        <option value="3"><?=$this->lang('editor_scope_3')?></option>
                        <option value="4"><?=$this->lang('editor_scope_4')?></option>
                    </select>
                </div>
                <div class="flex-grow-full text-right">
                    <button><?=$this->lang('editor_btn')?></button>
                </div>
        </form>
    </div>
</div>