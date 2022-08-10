<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <?php $this->insert('includes/nav')?>
    <div  class="flex-grow mt container">
        <?php foreach ($notifications as $v):?>
            <div class="line flex-grow flex-row mt">
                <?php if (!empty($v['avatar'])): ?>
                    <div class="avatar mr">
                        <a class="avatar-none"  href="<?=$v['url']?>" title="<?=$v['name'] ?: $v['preferred_name']?>">
                            <img src="<?=$v['avatar']?>" alt="<?=$v['name'] ?: $v['preferred_name']?>"/>
                        </a>
                    </div>
                <?php else:?>
                    <a class="avatar-none mr"  href="<?=$v['url']?>" title="<?=$v['name'] ?: $v['preferred_name']?>"><span></span></a>
                <?php endif;?>
                <div class="flex-grow">
                    <?php if (strtolower($v['type']) === 'follow'): ?>
                        <?php if ($v['status'] === 0): ?>
                            <div><?=$this->lang('follow_request', $v['name'] ?: $v['preferred_name'])?></div>
                            <div>
                                <a class="btn mr" href="/follow-requests/<?=$v['id']?>/answer?action=accept"><?=$this->lang('action_accept')?></a>
                                <a class="btn mr" href="/follow-requests/<?=$v['id']?>/answer?action=reject"><?=$this->lang('action_reject')?></a>
                                <a class="btn mr" href="/follow-requests/<?=$v['id']?>/answer?action=ignore"><?=$this->lang('action_ignore')?></a>
                            </div>
                        <?php else: ?>
                            <div><?=$this->lang('followed_message', $v['name'])?></div>
                            <div><a class="link" href="<?=$v['url']?>"><?=$v['url']?></a></div>
                        <?php endif;?>
                    <?php elseif (strtolower($v['type']) === 'unfollow'): ?>
                        <div><?=$this->lang('unfollowed_message', $v['name'])?></div>
                        <div><a class="link" href="<?=$v['url']?>"><?=$v['url']?></a></div>
                    <?php elseif (strtolower($v['type']) === 'mention'): ?>
                        <div><?=$this->lang('mentioned_message', $v['name'])?></div>
                        <div><a class="link" href="/web/threads/<?=$v['object_id']?>"><?=$v['raw']['object']['url'] ?? ($v['raw']['object']['id'] ?? '')?></a></div>
                    <?php elseif (strtolower($v['type']) === 'like'): ?>
                        <div><?=$this->lang('liked_message', $v['name'])?></div>
                        <div><a class="link" href="/web/threads/<?=$v['object_id']?>"><?=$v['raw_object_id'] ?? ''?></a></div>
                    <?php elseif (strtolower($v['type']) === 'reply'): ?>
                        <div><?=$this->lang('replied_message', $v['name'])?></div>
                        <div><a class="link" href="/web/threads/<?=$v['object_id']?>"><?=$v['raw']['object']['url'] ?? ($v['raw']['object']['id'] ?? '')?></a></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach;?>
    </div>
</div>
