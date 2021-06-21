<?php $this->layout('layout') ?>

<div class="flex flex-column">
    <div class="flex-auto flex flex-row header nav">
        <div class="bold mr">管理</div>
        <div class="mr">
            <a href="/timeline">公共时间轴</a>
        </div>
        <div class="mr">
            <a href="/notifications">通知</a>
        </div>
        <div class="mr">
            <a href="/editor">嘟嘟</a>
        </div>
        <div class="mr">
            <a href="/web/following">关系</a>
        </div>
        <div>
            <a href="/">返回</a>
        </div>
    </div>
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