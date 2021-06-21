<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8" content="width=device-width,initial-scale=1,user-scalable=no" name="viewport">
    <title><?=empty($profile) ? 'ActivityPub' : ($profile['name'] ?: $profile['preferred_name'])?></title>
    <style>
        :root{
            --sys-sans:-apple-system,BlinkMacSystemFont,"Helvetica Neue";
            --cjk-sans:"PingFang SC","Hiragino Sans GB","Droid Sans Fallback","Microsoft YaHei"
            --ggs:1;
        }
        html,body{
            font:400 16px/1.42 var(--sys-sans),var(--cjk-sans),sans-serif;
            margin: 0;
        }
        :root {box-sizing: border-box;font-weight: normal; color: #293256}
        .main {max-width: 35em; margin: .5em auto; padding: .5em .5em}
        h1 {font-size: 1.2rem; display: inline-block;margin: 0}
        p {margin: 0;line-height: 1.3rem}
        .inline-block {display: inline-block}
        .inline {display: inline}
        .mr {margin-right: .5em}
        .ml {margin-left: .5em}
        .mt {margin-top: .5em}
        .mb {margin-bottom: .5em}
        .mb-1 {margin-bottom: 1em}
        .mt-1 {margin-top: 1em}
        .mt-2 {margin-top: 2em}
        .mt-3 {margin-top: 3em}
        .mt-4 {margin-top: 4em}
        .btn {background: lightgray; color: #333; padding: .1em .5em;text-decoration: none;border-radius: .1em;cursor: pointer}
        .color-green{color: green}
        .color-red{color: red}
        .color-white {color: white}
        .color-gray{color: lightgray}
        .color-mute{color: #f0f0f0}
        .color-black{color: #333}
        .link {color: #293256}
        .color-purple {color: #6b7394}
        .no-decoration {text-decoration: none}
        .bg-color-green {background-color: green}
        .bg-color-purple{background-color: #707aa3}
        .bg-color-gray{background-color: #ededed}
        .flex {display: flex}
        .flex-wrap {flex-wrap: wrap}
        .flex-row {display: flex; flex-direction: row}
        .flex-column {display: flex; flex-direction: column}
        .flex-auto {flex:0 0 auto}
        .flex-grow {flex-grow: 1;}
        .flex-grow-full{flex-grow: 1; flex-basis: 100%;}
        .flex-shrink{flex-shrink: 1; min-width: 0}
        .flex-align-center {align-items: center}
        .avatar {width:2.6em;height: 2.6em; border: 1px solid #555; background-color: lightgray;overflow: hidden;border-radius: .1em}
        .avatar-min {width:1.2em;height: 1.2em; border: 0px solid #555; background-color: lightgray;overflow: hidden;border-radius: .1em}
        .avatar-min-none {width:1.2em;height: 1.2em; overflow: hidden;background-color: lightgray;border-radius: .1em}
        .avatar-min img {width: 1.2em;height: 1.2em;padding: 0;margin: 0;border-radius: .1em}
        .avatar-none {width:2.6em;height: 2.6em;background-color: lightgray;border-radius: .1em;border:1px solid lightgray}
        .avatar img {width: 2.6em;height: 2.6em;padding: 0;margin: 0}
        .attachment {max-width: 5em;}
        .bold {font-weight: bold}
        .tag {background: #ededed; color: #333; padding: .1em .5em;text-decoration: none;word-break: keep-all; border-radius: .1em}
        .tag-min {background: #f0f0f0; color: #333; text-decoration: none;word-break: keep-all}
        .navigator {background: #f5f7ff; padding: .1em .5em;text-decoration: none;word-break: keep-all; border-radius: .1em}
        .float-right {float: right}
        .footer a{color: #6b7394;text-decoration: underline}
        .ellipse {text-overflow: ellipsis; overflow: hidden;white-space: nowrap}
        .nowrap {white-space: nowrap}
        .text-right {text-align: right}
        .text-center{text-align: center}
        textarea {width: 100%;box-sizing: border-box;padding: .5em;border-radius: .3em;border: 1px solid #f0f0f0;font-size: 1rem}
        html {background-color: #dfe2f1}
        .blog {background-color: #f5f7ff;; padding: 1em .5em;border-radius: .2em}
        .blog, .container {background-color: #f5f7ff;; padding: 1em .5em;border-radius: .2em}
        .content {color:#5e6687}
        .content a, .nav a{color: #293256}
        .content p {margin: .6em 0; word-break: break-all;}
        .content br {display: block; margin: .5em 0;content: " "}
        .content img {max-width: 100%;font-weight: normal}
        .content h2 {font-size: 1rem}
        .others a{color: #6b7394; text-decoration: none}
        .blog-header a{
            color: #a9afca;
            text-decoration: none
        }
        .blog-header {
            background-color: #eceffd;
            color: #a9afca;
            padding: .2em .5em;
            border-top-left-radius: .2em;
            border-top-right-radius: .2em;
        }
        .more {padding-top: .5em;width:1.2em;height: .8em;cursor: pointer}
        .scope {padding: .1em}

        .line {
            margin: .5em 0;
            padding: .5em 0;
            border-bottom: 1px solid #dddddd;
        }
        .line:first-child {
            margin-top: 0;
        }
        .line:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .interaction button {border: none; background: none;cursor: pointer;outline: none;padding: 0;margin: 0}
        .interactions {
            display:grid;
            grid-template-columns: repeat(16, 1fr);
            grid-gap: .2em;
            background-color: #f5f7ff;
            padding: 1em .5em;
            border-radius: .2em;
        }
        .interaction-like {
            display: grid;
            /*border: 1px solid black;*/
            justify-content: center;
            align-content: center;
        }
        .label label {
            display: inline-block;
            width: 5rem;
            text-align: left;
        }
        .label label:after {
            content: "：";
        }

        .icon {margin-bottom: .3em}
        .dropdown-menu {
            background: #ededed;
            visibility: hidden;
            opacity: 0;
            min-width: 5rem;
            position: absolute;
            transition: all 0.5s ease;
            margin-top: 1rem;
            left: 0;
            display: none;
            border-radius: .2em;
            border:1px solid #ddd;
        }
        .more:hover + .dropdown-menu,
        .dropdown-menu:hover{
            visibility: visible;
            opacity: 1;
            display: block;
            left: inherit;
            margin-top: -.5em;
            margin-left: -4.4em;
        }
        .dropdown-menu .item {
            text-align: center;
            padding: .2em;
        }
        .dropdown-menu .item:hover{
            background-color: #5e6687;
            cursor: pointer;
        }
        .dropdown-menu .item:hover a {
            color: white;
        }
        .dropdown-menu .btn {
            display: inline;
            width: 100%;
            margin: 0;
            padding: 0;
            font-size: 1em;
            border: none;
            background-color: #ededed;
            color: #6B7393;
        }
        .dropdown-menu .item:hover .btn {
            background-color: #5e6687;
            color: white;
        }

        .gg-link {
            box-sizing: border-box;
            position: relative;
            display: block;
            transform: rotate(-45deg) scale(var(--ggs,1));
            width: 8px;
            height: 2px;
            background: currentColor;
            border-radius: 4px
        }
        .gg-link::after,
        .gg-link::before {
            content: "";
            display: block;
            box-sizing: border-box;
            position: absolute;
            border-radius: 3px;
            width: 8px;
            height: 10px;
            border: 2px solid;
            top: -4px
        }
        .gg-link::before {
            border-right: 0;
            border-top-left-radius: 40px;
            border-bottom-left-radius: 40px;
            left: -6px
        }
        .gg-link::after {
            border-left: 0;
            border-top-right-radius: 40px;
            border-bottom-right-radius: 40px;
            right: -6px
        }

        .gg-bookmark,
        .gg-bookmark::after {
            display: block;
            box-sizing: border-box;
            border-top-right-radius: 3px
        }
        .gg-bookmark {
            border: 2px solid;
            border-bottom: 0;
            border-top-left-radius: 3px;
            overflow: hidden;
            position: relative;
            transform: scale(var(--ggs,1));
            width: 14px;
            height: 16px
        }
        .gg-bookmark::after {
            content: "";
            position: absolute;
            width: 12px;
            height: 12px;
            border-top: 2px solid;
            border-right: 2px solid;
            transform: rotate(-45deg);
            top: 9px;
            left: -1px
        }
        .gg-comment {
            box-sizing: border-box;
            position: relative;
            display: block;
            transform: scale(var(--ggs,1));
            width: 20px;
            height: 16px;
            border: 2px solid;
            border-bottom: 0;
            box-shadow:
                    -6px 8px 0 -6px,
                    6px 8px 0 -6px
        }
        .gg-comment::after,
        .gg-comment::before {
            content: "";
            display: block;
            box-sizing: border-box;
            position: absolute;
            width: 8px
        }
        .gg-comment::before {
            border: 2px solid;
            border-top-color: transparent;
            border-bottom-left-radius: 20px;
            right: 4px;
            bottom: -6px;
            height: 6px
        }
        .gg-comment::after {
            height: 2px;
            background: currentColor;
            box-shadow: 0 4px 0 0;
            left: 4px;
            top: 4px
        }

        .gg-corner-double-up-left {
            box-sizing: border-box;
            position: relative;
            display: block;
            transform: scale(var(--ggs,1));
            width: 10px;
            height: 8px;
            border-top-right-radius: 4px;
            border-top: 2px solid;
            border-right: 2px solid
        }
        .gg-corner-double-up-left::after,
        .gg-corner-double-up-left::before {
            content: "";
            display: block;
            box-sizing: border-box;
            position: absolute;
            width: 6px;
            height: 6px;
            border-bottom: 2px solid;
            top: -4px;
            transform: rotate(45deg)
        }
        .gg-corner-double-up-left::after {
            border-left: 2px solid
        }
        .gg-corner-double-up-left::before {
            border-left: 2px solid;
            left: -4px
        }

        .gg-external {
            box-sizing: border-box;
            position: relative;
            display: block;
            transform: scale(var(--ggs,1));
            width: 12px;
            height: 12px;
            box-shadow:
                    -2px 2px 0 0,
                    -4px -4px 0 -2px,
                    4px 4px 0 -2px;
            margin-left: -2px;
            margin-top: 1px
        }

        .gg-external::after,
        .gg-external::before {
            content: "";
            display: block;
            box-sizing: border-box;
            position: absolute;
            right: -4px
        }

        .gg-external::before {
            background: currentColor;
            transform: rotate(-45deg);
            width: 12px;
            height: 2px;
            top: 1px
        }

        .gg-external::after {
            width: 8px;
            height: 8px;
            border-right: 2px solid;
            border-top: 2px solid;
            top: -4px
        }

        .gg-heart,
        .gg-heart::after {
            border: 2px solid;
            border-top-left-radius: 100px;
            border-top-right-radius: 100px;
            width: 10px;
            height: 8px;
            border-bottom: 0
        }

        .gg-heart {
            box-sizing: border-box;
            position: relative;
            transform:
                    translate(
                            calc(-10px / 2 * var(--ggs,1)),
                            calc(-6px / 2 * var(--ggs,1))
                    )
                    rotate(-45deg)
                    scale(var(--ggs,1));
            display: block
        }

        .gg-heart::after,
        .gg-heart::before {
            content: "";
            display: block;
            box-sizing: border-box;
            position: absolute
        }

        .gg-heart::after {
            right: -9px;
            transform: rotate(90deg);
            top: 5px
        }

        .gg-heart::before {
            width: 11px;
            height: 11px;
            border-left: 2px solid;
            border-bottom: 2px solid;
            left: -2px;
            top: 3px
        }

        .gg-repeat {
            box-sizing: border-box;
            position: relative;
            display: block;
            transform: scale(var(--ggs,1));
            box-shadow:
                    -2px -2px 0 0,
                    2px 2px 0 0;
            width: 14px;
            height: 6px
        }

        .gg-repeat::after,
        .gg-repeat::before {
            content: "";
            display: block;
            box-sizing: border-box;
            position: absolute;
            width: 0;
            height: 0;
            border-top: 3px solid transparent;
            border-bottom: 3px solid transparent
        }

        .gg-repeat::before {
            border-left: 5px solid;
            top: -4px;
            right: 0
        }

        .gg-repeat::after {
            border-right: 5px solid;
            bottom: -4px;
            left: 0
        }

        .gg-arrange-back {
            box-sizing: border-box;
            position: relative;
            transform: scale(var(--ggs,1));
            display: block;
            width: 18px;
            height: 18px
        }

        .gg-arrange-back::after,
        .gg-arrange-back::before {
            content: "";
            display: block;
            box-sizing: border-box;
            position: absolute
        }

        .gg-arrange-back::after {
            width: 10px;
            height: 10px;
            border: 2px solid;
            left: 4px;
            top: 4px
        }

        .gg-arrange-back::before {
            width: 8px;
            height: 8px;
            background: currentColor;
            box-shadow: 10px 10px 0
        }

        .gg-more-alt {
            transform: scale(var(--ggs,1))
        }

        .gg-more-alt,
        .gg-more-alt::after,
        .gg-more-alt::before {
            box-sizing: border-box;
            position: relative;
            display: block;
            width: 4px;
            height: 4px;
            background: currentColor;
            border-radius: 100%
        }

        .gg-more-alt::after,
        .gg-more-alt::before {
            content: "";
            position: absolute;
            top: 0
        }

        .gg-more-alt::after {
            left: -6px
        }

        .gg-more-alt::before {
            right: -6px
        }
        .gg-path-outline {
            display: block;
            position: relative;
            box-sizing: border-box;
            transform: scale(var(--ggs,1));
            width: 14px;
            height: 14px
        }

        .gg-path-outline::after,
        .gg-path-outline::before {
            content: "";
            position: absolute;
            display: block;
            box-sizing: border-box;
            border: 2px solid;
            width: 10px;
            height: 10px
        }

        .gg-path-outline::before {
            bottom: 0;
            right: 0
        }

        .gg-trash {
            box-sizing: border-box;
            position: relative;
            display: block;
            transform: scale(var(--ggs,1));
            width: 10px;
            height: 12px;
            border: 2px solid transparent;
            box-shadow:
                    0 0 0 2px,
                    inset -2px 0 0,
                    inset 2px 0 0;
            border-bottom-left-radius: 1px;
            border-bottom-right-radius: 1px;
            margin-top: 4px
        }

        .gg-trash::after,
        .gg-trash::before {
            content: "";
            display: block;
            box-sizing: border-box;
            position: absolute
        }

        .gg-trash::after {
            background: currentColor;
            border-radius: 3px;
            width: 16px;
            height: 2px;
            top: -4px;
            left: -5px
        }


        .JesterBox div {
            visibility: hidden;
            position: fixed;
            top: 5%;
            right: 5%;
            bottom: 5%;
            left: 5%;
            z-index: 75;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .JesterBox div:before {
            content: '';
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 74;
            background-color: rgba(0, 0, 0, 0);
            transition: all 0.5s ease-out;
        }

        .JesterBox div img {
            position: relative;
            z-index: 77;
            max-width: 100%;
            max-height: 100%;
            margin-left: -9999px;
            opacity: 0;
            transition-property: all, opacity;
            transition-duration: 0.5s, 0.2s;
            transition-timing-function: ease-in-out, ease-out;
        }

        .JesterBox div:target { visibility: visible; }

        .JesterBox div:target:before { background-color: rgba(0, 0, 0, 0.7); }

        .JesterBox div:target img {
            margin-left: 0px;
            opacity: 1;
        }
    </style>
</head>
<body>
<div class="main">
    <?=$this->section('content')?>
    <div class="footer mt-2 color-purple">
        <p>Powered by <a href="https://github.com/zither/cherry">Cherry</a> and the <a href="https://activitypub.rocks">ActivityPub</a> protocol</p>
    </div>
</div>
<?=$this->section('scripts')?>
</body>
</html>
