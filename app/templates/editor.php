<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <?php $this->insert('includes/nav')?>
    <div  class="flex-grow mt">
        <form  action="/notes" method="POST">
            <div class="mb">
                <textarea name="summary" rows="1" placeholder="<?=$this->lang('warning_placeholder');?>"></textarea>
            </div>
            <div class="mb">
                <textarea name="content" rows="10" placeholder="<?=$this->lang('content_placeholder');?>"></textarea>
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