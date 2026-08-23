<?php

/** @var int $requestId */
/** @var string $trackId */
?>
<section aria-labelledby="rating-title">
    <h1 id="rating-title">Service rating</h1>
    <p>Tracking ID: <strong><?= esc($trackId) ?></strong></p>
    <form method="post" action="<?= site_url('rating') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="request_id" value="<?= $requestId ?>">
        <input type="hidden" name="track_id" value="<?= esc($trackId) ?>">
        <?php for ($question = 1; $question <= 8; $question++): ?>
            <div class="field">
                <label for="rating-<?= $question ?>">Question <?= $question ?></label>
                <input id="rating-<?= $question ?>" name="rating_<?= $question ?>" type="number" min="1" max="5" required>
            </div>
        <?php endfor ?>
        <div class="field">
            <label for="rating-comment">Comment</label>
            <textarea id="rating-comment" name="rating_comment" maxlength="2000"></textarea>
        </div>
        <button type="submit">Submit rating</button>
    </form>
</section>
