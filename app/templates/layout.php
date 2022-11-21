<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8" content="width=device-width,initial-scale=1,user-scalable=no" name="viewport">
    <title><?=empty($profile) ? 'ActivityPub' : ($profile['name'] ?: $profile['preferred_name'])?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=<?=CHERRY_STYLE_VERSION?>" type="text/css">
    <?php if ($this->getThemeName() !== 'default'):?>
        <link rel="stylesheet" href="/themes/<?=$this->getThemeName()?>.css?v=<?=CHERRY_STYLE_VERSION?>" type="text/css">
    <?php endif;?>
</head>
<body>
<div class="main">
    <?=$this->section('content')?>
    <div class="footer mt-2">
        <p>Powered by <a href="https://github.com/zither/cherry">Cherry</a> and the <a href="https://activitypub.rocks">ActivityPub</a> protocol</p>
    </div>
</div>
<?=$this->section('scripts')?>
</body>
</html>
