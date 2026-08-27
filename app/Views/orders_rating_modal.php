<?php

/**
 * Rating modal — included once per listing เมื่อ profile['row_action'] === 'rate' (status 5)
 * เปิดจากปุ่ม `.rate-open` ต่อแถว ยิง POST /rating ด้วย contract CI4 (request_id, track_id,
 * rating_1..rating_8, rating_comment) ผ่าน fetch แล้วแสดงผลตาม status code
 */

$questions = [
    ['th' => 'ความพึงพอใจในการให้บริการของเจ้าหน้าที่ ณ จุดรับซ่อม', 'en' => 'How would you rate the helpfulness of our drop point service?'],
    ['th' => 'ความพึงพอใจในการให้บริการของศูนย์บริการ', 'en' => 'How would you rate our service center?'],
    ['th' => 'ความพึงพอใจในคุณภาพงานซ่อม', 'en' => 'Did our repair quality meet your expectation?'],
    ['th' => 'ระยะเวลาที่ใช้ในการซ่อม', 'en' => 'Did our repair lead time meet your expectation?'],
    ['th' => 'ระยะเวลาซ่อม', 'en' => 'Repair lead time'],
    ['th' => 'ค่าบริการซ่อม', 'en' => 'Cost of the repair service'],
    ['th' => 'คุณภาพงานซ่อม', 'en' => 'Quality of repair service'],
    ['th' => 'ความพึงพอใจในการบริการ', 'en' => 'Quality of customer service'],
];
?>
<dialog id="rating-modal" aria-labelledby="rating-modal-title">
    <div>
        <button type="button" class="rate-lang" data-lang="th">ไทย</button>
        <button type="button" class="rate-lang" data-lang="en">English</button>
    </div>
    <?php // Rendered as a paragraph, not a heading: this modal is a CI4 addition and CI3's
          // queue-5 page has no heading of its own here. The dialog keeps its accessible name
          // through aria-labelledby, which works on any element. ?>
    <p class="rating-modal-title" id="rating-modal-title">
        <span class="lang-th">กรุณาประเมินความพึงพอใจในบริการของเรา</span>
        <span class="lang-en">Please rate your satisfaction with our service</span>
    </p>
    <form id="rating-form">
        <?= csrf_field() ?>
        <input type="hidden" name="request_id" value="">
        <input type="hidden" name="track_id" value="">
        <?php foreach ($questions as $index => $question): ?>
            <?php $number = $index + 1; ?>
            <div class="rate-q">
                <div class="lang-th"><?= $number ?>. <?= esc($question['th']) ?></div>
                <div class="lang-en"><?= $number ?>. <?= esc($question['en']) ?></div>
                <div class="rate-stars">
                    <?php for ($value = 1; $value <= 5; $value++): ?>
                        <span class="star" data-value="<?= $value ?>">&#9733;</span>
                    <?php endfor ?>
                    <input type="hidden" name="rating_<?= $number ?>" value="0">
                </div>
            </div>
        <?php endforeach ?>
        <div class="rate-q">
            <label for="rating-modal-comment">
                <span class="lang-th">ข้อเสนอแนะเพิ่มเติม</span>
                <span class="lang-en">Any other feedback?</span>
            </label>
            <textarea id="rating-modal-comment" name="rating_comment" maxlength="2000"></textarea>
        </div>
        <p id="rating-result" role="status" aria-live="polite"></p>
        <button type="button" id="rating-submit">
            <span class="lang-th">ส่งคะแนน</span>
            <span class="lang-en">Submit</span>
        </button>
        <button type="button" id="rating-close">
            <span class="lang-th">ปิด</span>
            <span class="lang-en">Close</span>
        </button>
    </form>
</dialog>
<script>
    (function () {
        var dialog = document.getElementById('rating-modal');
        if (dialog === null || typeof dialog.showModal !== 'function') {
            return;
        }
        var form = document.getElementById('rating-form');
        var result = document.getElementById('rating-result');
        var groups = form.querySelectorAll('.rate-stars');

        function paint(group, value) {
            group.querySelectorAll('.star').forEach(function (star) {
                star.classList.toggle('on', Number(star.dataset.value) <= value);
            });
            group.querySelector('input[type="hidden"]').value = value;
        }

        groups.forEach(function (group) {
            group.querySelectorAll('.star').forEach(function (star) {
                star.addEventListener('click', function () {
                    paint(group, Number(star.dataset.value));
                });
            });
        });

        document.querySelectorAll('.rate-open').forEach(function (button) {
            button.addEventListener('click', function () {
                form.elements.request_id.value = button.dataset.requestId;
                form.elements.track_id.value = button.dataset.trackId;
                dialog.showModal();
            });
        });

        dialog.querySelectorAll('.rate-lang').forEach(function (button) {
            button.addEventListener('click', function () {
                dialog.classList.toggle('show-en', button.dataset.lang === 'en');
            });
        });

        document.getElementById('rating-close').addEventListener('click', function () {
            dialog.close();
        });

        dialog.addEventListener('close', function () {
            form.reset();
            groups.forEach(function (group) { paint(group, 0); });
            result.textContent = '';
        });

        document.getElementById('rating-submit').addEventListener('click', function () {
            result.textContent = '';
            fetch('/rating', { method: 'POST', body: new FormData(form) })
                .then(function (response) {
                    if (response.status === 201) {
                        result.textContent = 'บันทึกคะแนนเรียบร้อย';
                    } else if (response.status === 409) {
                        result.textContent = 'รายการนี้ถูกประเมินแล้ว';
                    } else {
                        result.textContent = 'บันทึกไม่สำเร็จ';
                    }
                })
                .catch(function () {
                    result.textContent = 'บันทึกไม่สำเร็จ';
                });
        });
    })();
</script>
