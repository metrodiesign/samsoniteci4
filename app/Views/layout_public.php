<?php

use App\Presentation\LegacyViewRenderer;

/**
 * CI4 integration shell for the pinned CI3 public header and footer.
 * Page controllers must supply content rendered by the matching dedicated CI3 view.
 *
 * @var string $content
 * @var string|null $language
 */
$language = ($language ?? 'en') === 'th' ? 'th' : 'en';
$renderer = new LegacyViewRenderer();

echo $renderer->render($language === 'th' ? 'web/header_th' : 'web/header');
echo $content;
echo $renderer->render('web/footer');
