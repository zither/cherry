<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <?php $this->insert('includes/nav')?>
    <div  class="flex-grow mt">
        <form  action="/notes" method="POST">
            <div class="mb">
                <textarea name="content" rows="10"></textarea>
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