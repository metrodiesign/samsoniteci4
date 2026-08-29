<script src="<?= base_url('assets/bootstrap/js/bootstrap.min.js') ?>" type="text/javascript"></script>
<script src="<?= base_url('assets/dist/js/app.min.js') ?>" type="text/javascript"></script>
<script src="<?= base_url('assets/js/jquery.validate.js') ?>" type="text/javascript"></script>
<script src="<?= base_url('assets/js/validation.js') ?>" type="text/javascript"></script>
<script src="<?= base_url('assets/js/browse/jquery.knob.js') ?>"></script>
<script src="<?= base_url('assets/js/browse/jquery.ui.widget.js') ?>"></script>
<script src="<?= base_url('assets/js/browse/jquery.iframe-transport.js') ?>"></script>
<script src="<?= base_url('assets/js/browse/jquery.fileupload.js') ?>"></script>
<script src="<?= base_url('assets/js/browse/script.js') ?>"></script>
<script type="text/javascript">
    var windowURL = window.location.href;
    pageURL = windowURL.substring(0, windowURL.lastIndexOf('/'));
    var x = $('a[href="' + pageURL + '"]');
    x.addClass('active');
    x.parent().addClass('active');
    var y = $('a[href="' + windowURL + '"]');
    y.addClass('active');
    y.parent().addClass('active');
</script>
