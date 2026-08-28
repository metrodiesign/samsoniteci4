<?php
/**
 * @var string $submissionId
 * @var string $targetId
 */
?>
<form id="upload" method="post" action="/order/do_upload_multi/<?= esc($submissionId, 'attr') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div id="drop" style="display: -webkit-inline-box; text-align: left; width: 100%;">
        <a><i class="fa fa-camera"></i> ADD IMAGE</a>
        <input type="file" name="upl" accept="image/*">
    </div>
    <ul></ul>
</form>
<script>
var xtimesite = <?= json_encode($submissionId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
(function($) {
    var target = document.getElementById(<?= json_encode($targetId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    var files = [];
    var operationKey = '__ci4OrderUploadOperation';

    function syncFiles() {
        var queue = new DataTransfer();
        files.forEach(function(item) { queue.items.add(item.file); });
        target.files = queue.files;
    }

    function removeQueueItem(preview) {
        var item = preview.data('orderQueueItem');
        var removed = false;
        for (var index = files.length - 1; index >= 0; index--) {
            if (files[index].group === item) {
                files.splice(index, 1);
                removed = true;
            }
        }
        if (removed) syncFiles();
    }

    function bindQueueRemoval(preview) {
        if (!preview.length || preview.data('orderQueueRemovalBound')) return;
        preview.data('orderQueueRemovalBound', true);
        preview.find('span').click(function() { removeQueueItem(preview); });
    }

    function bindContext(state) {
        var preview = state.context.filter('li');
        if (!preview.length) preview = state.context;
        if (state.item) preview.data('orderQueueItem', state.item);
        if (state.terminal !== 'pending') {
            preview.removeClass('working');
            if (state.terminal !== 'success') preview.addClass('error');
        }
    }

    function settle(state, terminal) {
        if (!state || state.terminal !== 'pending') return false;
        state.terminal = terminal;
        bindContext(state);
        return true;
    }

    function observeOperation(data) {
        var initialContext = data.context;
        var context = $();
        var state = { files: data.files.slice(), context: context, item: null, terminal: 'pending' };
        data[operationKey] = state;
        Object.defineProperty(data, 'context', {
            configurable: true,
            enumerable: true,
            get: function() { return context; },
            set: function(nextContext) {
                var next = $(nextContext);
                var progress = next.filter('input').first();
                var preview = next.filter('li').first();
                if (progress.length && preview.length) progress.prependTo(preview);
                context.length = 0;
                Array.prototype.push.apply(context, next.get());
                bindQueueRemoval(preview);
                bindContext(state);
            }
        });
        if (initialContext) data.context = initialContext;
    }

    function updateCsrf(result) {
        if (result && result.csrf_token && result.csrf_hash) {
            $('input[name="' + result.csrf_token + '"]').val(result.csrf_hash);
        }
    }

    $('#upload')
        .on('fileuploadadd', function(event, data) {
            observeOperation(data);
        })
        .on('fileuploaddone', function(event, data) {
            updateCsrf(data.result);
            var state = data[operationKey];
            if (!state || state.terminal !== 'pending') return;
            if (!data.result || data.result.status !== 'success') {
                settle(state, 'rejected');
                return;
            }
            if (files.length + state.files.length > 5) {
                settle(state, 'rejected');
                return;
            }
            state.item = data.orderQueueItem = {};
            state.files.forEach(function(file) {
                files.push({ file: file, group: state.item });
            });
            syncFiles();
            settle(state, 'success');
        })
        .on('fileuploadfail', function(event, data) {
            updateCsrf(data.jqXHR && data.jqXHR.responseJSON);
            var state = data[operationKey];
            if (!settle(state, 'failed') && state && state.terminal === 'success') {
                data.context = $();
            }
        })
        .on('click', '#upload li span', function() {
            removeQueueItem($(this).closest('li'));
        });
})(jQuery);
</script>
