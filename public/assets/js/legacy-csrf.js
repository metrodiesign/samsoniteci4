(function ($) {
    'use strict';

    if (!$ || typeof $.ajaxPrefilter !== 'function') {
        return;
    }

    function csrfInput() {
        return document.querySelector('input[name="csrf_test_name"]');
    }

    function sameOrigin(url) {
        var target = document.createElement('a');
        target.href = url || window.location.href;

        return target.protocol === window.location.protocol && target.host === window.location.host;
    }

    function containsField(data, name) {
        return data.split('&').some(function (pair) {
            var encodedName = pair.split('=', 1)[0].replace(/\+/g, ' ');
            try {
                return decodeURIComponent(encodedName) === name;
            } catch (error) {
                return false;
            }
        });
    }

    $(function () {
        if (!/^\/(?:(?:Order\/)?ReportTrackingListingTest|(?:Order\/)?reportsummary)(?:\/|$)/.test(window.location.pathname)) {
            return;
        }

        window.setTimeout(function () {
            var lengthSelect = $('#examples_length select');
            if (!lengthSelect.length) {
                return;
            }

            // Repaint CI4's generated native select after CI3's delayed column adjustment.
            var replacement = lengthSelect.get(0).cloneNode(true);
            replacement.value = lengthSelect.val();
            lengthSelect.replaceWith(replacement);
            $(replacement).on('change.DT', function () {
                $('#examples').DataTable().page.len($(this).val()).draw();
            });
        }, 1100);
    });

    jQuery.ajaxPrefilter(function (options) {
        var method = String(options.type || options.method || 'GET').toUpperCase();
        var input = csrfInput();
        if (method !== 'POST' || !input || !input.value || !sameOrigin(options.url)) {
            return;
        }

        if (window.FormData && options.data instanceof window.FormData) {
            if (!options.data.has(input.name)) {
                options.data.append(input.name, input.value);
            }
            return;
        }

        if (typeof options.data === 'string') {
            if (!containsField(options.data, input.name)) {
                var separator = options.data === '' ? '' : '&';
                options.data += separator + encodeURIComponent(input.name) + '=' + encodeURIComponent(input.value);
            }
            return;
        }

        if (options.data && typeof options.data === 'object') {
            if (!Object.prototype.hasOwnProperty.call(options.data, input.name)) {
                options.data[input.name] = input.value;
            }
            return;
        }

        options.data = encodeURIComponent(input.name) + '=' + encodeURIComponent(input.value);
    });

    $(document).ajaxError(function (_event, xhr) {
        var responseJSON = xhr.responseJSON;
        if (xhr.status === 409 && responseJSON && responseJSON.error === 'master_referenced') {
            window.alert('branch deletion failed');
        }
    });

    $(document).ajaxComplete(function (_event, xhr) {
        var token = xhr.getResponseHeader('X-CSRF-TOKEN');
        if (!token) {
            return;
        }
        document.querySelectorAll('input[name="csrf_test_name"]').forEach(function (input) {
            input.value = token;
        });
    });
})(window.jQuery);
