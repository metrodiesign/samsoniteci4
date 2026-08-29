<?php

use App\Presentation\LegacyViewRenderer;

/** @var string $pageTitle @var string $content @var string $name @var string $role_text @var string $last_login @var bool $contentOwnsWrapper @var string $subtitle @var string $actions */
$renderer = new LegacyViewRenderer();
echo $renderer->render('includes/header_order', [
    'pageTitle' => esc($pageTitle),
    'name' => esc($name),
    'role_text' => esc($role_text),
    'last_login' => esc($last_login),
    'response' => '[]',
]);

echo $content;
echo $renderer->render('includes/footer_order');
