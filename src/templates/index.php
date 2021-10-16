<?php $this->layout('layout', ['profile' => $profile ?? []]) ?>

<div class="flex flex-column">
    <div class="header">
        <div class="title">
            <h1 class="mr"><?=$profile['name'] ?: $profile['preferred_name']?></h1>
            <span class="mr">@<?=$profile['account']?></span>
            <?php if ($is_admin): ?>
            <a class="link" href="/timeline">
                管理
                <?php if ($notifications):?>
                    (<?=$notifications?>)
                <?php endif;?>
            </a>
            <?php endif;?>
        </div>
        <div class="description mt-1 mb-1">
            <?=$profile['summary']?>
        </div>
        <div class="counts">
            <a href="/" class="btn mr bg-color-purple color-white"><span><?=$counts['objects']?></span> 条嘟文</a>
            <a href="/" class="btn mr bg-color-purple color-white"><span><?=$counts['following']?></span> 个关注</a>
            <a href="/" class="btn mr bg-color-purple color-white"><span><?=$counts['followers']?></span> 个关注者</a>
        </div>
    </div>

    <div class="blogs mt-1">
        <?php foreach ($blogs as $v): ?>
            <?php if ($v['show_boosted']):?>
                <div class="flex-grow bg-color-gray mt-1 color-purple blog-header">
                    Boosted by <a href="<?=$v['activity_profile_url']?>"><?=$v['activity_name'] ?: $v['activity_preferred_name']?></a>
                </div>
            <?php elseif (!empty($v['parent_profile'])): ?>
                <div class="flex-grow bg-color-gray mt-1 color-purple blog-header">
                    In reply to  <a href="<?=$v['parent_profile']['raw_object_id']?>"><?=$v['parent_profile']['name'] ?: $v['parent_profile']['preferred_name']?></a>
                </div>
            <?php else: ?>
                <div class="flex-grow mt-1">
                </div>
            <?php endif;?>

            <div class="flex-column blog">
                <div class="flex-row">
                    <div class="flex-column flex-grow flex-shrink box">
                        <div class="flex-grow flex-row">
                            <?php if (!empty($v['avatar'])):?>
                                <div class="flex-auto avatar-min mr"><img src="<?=$v['avatar']?>" referrerpolicy="no-referrer"></div>
                            <?php else:?>
                                <div class="flex-auto avatar-min-none mr"></div>
                            <?php endif;?>
                            <div class="flex-auto flex-shrink ellipse mr bold color-black"><?=$v['name'] ?: $v['preferred_name']?></div>
                            <div class="flex-grow flex-shrink ellipse mr"><a class="color-black no-decoration" href="<?=$v['profile_url']?>"><?=$v['account']?></a></div>
                            <div class="color-purple flex-grow ml text-right nowrap"><?=$v['date']?></div>
                        </div>
                        <div class="flex-grow content mt-1">
                            <?php if (!$v['is_sensitive']): ?>
                                <?=$v['content']?>
                            <?php else:?>
                                <span id="summary"><?=$v['summary']?></span>
                                <label for="show-content">
                                    <span>显示内容</span>
                                </label>
                                <input type=radio id="show-content" name="group">
                                <label for="hide-content">
                                    <span>隐藏内容</span>
                                </label>
                                <input type=radio id="hide-content" name="group">
                                <div id="content"><?=$v['content']?></div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($v['attachments'])): ?>
                            <div class="flex-grow flex-row mt">
                                <?php foreach ($v['attachments'] as $i => $image): ?>
                                    <?php if (strpos($image['media_type'], 'image') !== false): ?>
                                        <a href="#release-target" class="JesterBox">
                                            <div id="object-<?=$v['object_id']?>-<?=$i?>">
                                                <img src="<?=$image['url']?>" alt="<?=$image['name']?>" referrerpolicy="no-referrer"/>
                                            </div>
                                        </a>
                                        <a href="#object-<?=$v['object_id']?>-<?=$i?>">
                                            <img class="attachment" src="<?=$image['url']?>" alt="<?=$image['name']?>" referrerpolicy="no-referrer"/>
                                        </a>
                                    <?php elseif (strpos($image['media_type'], 'video') !== false):?>
                                        <video style="width:100%" controls>
                                            <source src="<?=$image['url']?>" type="<?=$image['media_type']?>">
                                        </video>
                                    <?php endif;?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif;?>

                        <div class="flex-grow flex-row others mt-1">
                            <div class="flex-grow flex-row ml color-purple">
                                <div class="flex-grow-full">
                                    <a href="<?=$v['url']?>" title="链接">
                                        <div class="inline-block">
                                            <i class="gg-link icon"></i>
                                        </div>
                                    </a>
                                </div>
                                <?php if ($is_admin):?>
                                    <div class="flex-grow-full">
                                        <a href="/objects/<?=$v['object_id']?>/reply" title="回复">
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
                                                <button class="inline-block <?=$v['is_boosted'] ? 'color-green' : 'color-purple' ?>" title="转嘟">
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
                                            <div class="inline-block <?=$v['is_boosted'] ? 'color-green' : 'color-purple' ?>" title="转嘟">
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
                                            <button class="inline-block <?=$v['is_liked'] ? 'color-green' : 'color-purple' ?>" title="喜欢">
                                                <i class="gg-heart"></i>
                                            </button>
                                            <?php if ($v['is_local']):?>
                                                <span class="ml"><?=$v['likes']?></span>
                                            <?php endif;?>
                                        </form>
                                    </div>
                                <?php else:?>
                                    <div class="flex-grow-full">
                                        <div class="inline-block <?=$v['is_liked'] ? 'color-green' : 'color-purple' ?>" title="喜欢">
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
                                                    <input class="btn" type="submit" value="删除嘟文" />
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="item">
                                                <form action="/profiles/<?=$v['profile_id']?>/fetch" METHOD="POST">
                                                    <input class="btn" type="submit" value="更新资料" />
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
        <?php endforeach;?>
    </div>
    <?php if ($is_admin && ($prev || $next)): ?>
    <div class="flex-row mt-1">
        <div class="flex-grow-full text-center navigator mr">
            <?php if ($prev):?>
                <a class="color-purple no-decoration" href="/?index=<?=$prev?>">上一页</a>
            <?php else: ?>
                <span class="color-gray"> 上一页</span>
            <?php endif;?>
        </div>
        <div class="flex-grow-full text-center navigator ml">
            <?php if ($next):?>
                <a class="color-purple no-decoration" href="/?index=<?=$next?>">下一页</a>
            <?php else: ?>
                <span class="color-gray"> 下一页</span>
            <?php endif;?>
        </div>
    </div>
    <?php endif;?>
</div>
