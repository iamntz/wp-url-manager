jQuery(document).ready(function ($) {
    let wrapper = $('.ntz-link-manager-add');
    let searchBox = $('input', wrapper);
    let submit = $('button', wrapper);
    let deletingInProgress = false;

    if (searchBox.length === 0) {
        return;
    }

    searchBox.autocomplete({
        source: function (request, response) {
            $.getJSON(ajaxurl, {
                ntz_nonce: ntz_url_manager.nonce,
                action: 'ntz_url_manager_search',
                query: encodeURIComponent(searchBox.val())
            }, response);
        },
        select: function (e, ui) {
            if (!$(e.srcElement).hasClass('js-ntz-delete-link')) {
                copyIdToClipboard(ui.item.id, e.ctrlKey);
            }
            return false;
        },
        minLength: 3
    }).autocomplete("instance")._renderItem = function (ul, item) {
        var markup = [];
        markup.push("<span>" + item.url + "</span>");
        markup.push("<br><small>" + (item.title || item.url) + "</small>");

        markup.push('<span class="js-ntz-delete-link ntz-delete-link dashicons dashicons-no-alt" data-id="' + item.id + '" data-title="Remove the link"></span>');
        return $("<li class='ntz-link-search-results'>")
            .append("<div data-id='" + item.id + "'>" + markup.join('') + "</div>")
            .appendTo(ul);
    };


    searchBox.on('keydown', function (e) {
        if (e.keyCode === 13 && !$(searchBox.autocomplete('widget')).is(':visible')) {
            submit.trigger('click');
            return false;
        }
    });

    function copyToClipboard(value) {
        var oText = false;
        var bResult = false;
        $('.ntz-copied-to-clipboard').remove();
        try {
            oText = document.createElement("textarea");
            $(oText).addClass('clipboardCopier').val(value).insertAfter('body').focus();
            oText.select();
            document.execCommand("Copy");
            bResult = true;

            $('<div class="ntz-copied-to-clipboard"/>').html('Copied to clipboard:<br>' + value).appendTo('body');

            window.setTimeout(function () {
                $('.ntz-copied-to-clipboard').fadeOut();
            }, 1000);

        } catch (e) {
            console.log(e);
        }

        $(oText).remove();
        return bResult;
    }

    function copyIdToClipboard(id, isCtrl) {
        var prepend = '';
        if (isCtrl) {
            prepend = ntz_url_manager.redirect_to;
        }
        copyToClipboard(prepend + id);
    }

    submit.on('click', function () {
        var submittingClass = 'is-submitting loading-content';
        if (searchBox.val() === '' || submit.hasClass(submittingClass)) {
            return false;
        }
        submit.addClass(submittingClass);
        submit.append('<span class="spinner" />');
        $.post(ajaxurl, {
            ntz_nonce: ntz_url_manager.nonce,
            action: 'ntz_url_manager_add',
            url: encodeURIComponent(searchBox.val())
        }, function (response) {
            if (!response.length) {
                return;
            }
            searchBox.val('');
            submit.removeClass(submittingClass);
            submit.find('.spinner').remove();

            $(response).addClass('added').prependTo('.ntz-link-manager-list');
            window.setTimeout(function () {
                $('.ntz-link-manager-list li').removeClass('added');
            }, 1000);
        });
        return false;
    });


    var list = $('.ntz-link-manager-list');
    list.on('click', '.copy-id', function (e) {
        copyIdToClipboard($(e.currentTarget).attr('data-id'), e.ctrlKey);
    });

    $(document).on('click', '.js-ntz-delete-link', function (e) {
        e.stopPropagation();
        e.preventDefault();
        deletingInProgress = true;
        if (window.confirm('Really, really delete?')) {
            var li = $(e.currentTarget).closest('li').addClass('to-be-deleted');
            $.post(ajaxurl, {
                ntz_nonce: ntz_url_manager.nonce,
                action: 'ntz_url_manager_delete',
                id: $(e.currentTarget).attr('data-id')
            }, function () {
                li.slideUp();
                deletingInProgress = false;
            });
        }
        return false;
    });
});