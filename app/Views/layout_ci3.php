<?php

use App\Presentation\LegacyViewRenderer;

/**
 * CI4 integration shell for the pinned CI3 administrative header and footer.
 * Dynamic values are prepared by AdminLayoutPresenter and escaped by the legacy adapter.
 *
 * @var string $pageTitle
 * @var string $content
 * @var string $name
 * @var string $role_text
 * @var string $last_login
 * @var bool $contentOwnsWrapper
 * @var string $subtitle
 * @var string $actions
 */
$renderer = new LegacyViewRenderer();
echo $renderer->render('includes/header', [
    'pageTitle' => esc($pageTitle),
    'name' => esc($name),
    'role_text' => esc($role_text),
    'last_login' => esc($last_login),
    'response' => '[]',
]);

if ($contentOwnsWrapper):
    echo $content;
else:
?>
<div class="content-wrapper">
    <section class="content-header">
        <h1><?= esc($pageTitle) ?><?php if ($subtitle !== ''): ?> <small><?= $subtitle ?></small><?php endif ?></h1>
        <?php if ($actions !== ''): ?><div class="page-actions"><?= $actions ?></div><?php endif ?>
    </section>
    <section class="content">
        <?= $content ?>
    </section>
</div>
<?php
endif;
echo $renderer->render('includes/footer');
