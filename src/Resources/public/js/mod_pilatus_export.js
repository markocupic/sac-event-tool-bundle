/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */
(function ($) {
    $(document).ready(function () {

        /**
         * Catch datarecord from Event-API
         */
        $('button[data-targetmodal]').click(function (e) {
            e.stopPropagation();
            var modal = $('#' + $(e.target).data('targetmodal'));

            var eventId = $(e.target).data('eventid');

            $(modal).on('show.bs.modal', function (e) {
                var form = $(modal).find('form');
                form.find('[name="id"').val(eventId);
                var formData = $(form).serialize();

                // Clear modal
                $(modal).find('[data-event="title"]').text('');
                $(modal).find('textarea[data-event]').val('');
                // Fetch data
                $.ajax({
                    url: 'eventApi/getEventById',
                    type: 'POST',
                    data: formData
                }).done(function (response) {
                    if (response['status'] === 'success') {
                        var aEvent = response['arrEventData'];
                        // Show event title in the modal header
                        $(modal).find('[data-event="title"]').text(aEvent['title']);

                        // Fill textareas with values
                        for (fieldname in aEvent) {
                            if ($(modal).find('textarea[data-event="' + fieldname + '"')) {
                                $(modal).find('textarea[data-event="' + fieldname + '"').first().val(aEvent[fieldname]);
                            }
                        }
                    }
                });
            });
            // Open modal
            $(modal).modal('show');

            // !important: unbind event from modal
            $(modal).off('show.bs.modal');
            $(modal).unbind('show.bs.modal');
        });


        /**
         * Save datarecord and close modal
         */
        $("button.send-form").click(function () {
            if (confirm('Willst du deine Änderungen am Event wirklich speichern?')) {
                var modal = $(this).closest('.modal')
                var form = $(this).closest('form');
                var form_data = $(form).serialize();
                var request_method = $(form).attr("method");
                var url = $(form).attr("action");
                $.ajax({
                    url: url,
                    type: request_method,
                    data: form_data
                }).done(function (response) {
                    if (response['status'] === 'success') {
                        var scrollTop = $(document).scrollTop();
                        sessionStorage.setItem('scroll-top', scrollTop);
                        // Reload page
                        var formReload = $('input[name="FORM_SUBMIT"][value="form-pilatus-export"]').closest('form');
                        $(formReload).find('.submit').trigger('click');
                        $(modal).modal('hide');
                    } else {
                        console.log(response.message);
                    }
                });
            }
        });


        /**
         * Toggle the "enable frontend-edit button"
         */
        if (sessionStorage.getItem('enable-frontend-edit') == 'true') {
            $('tr.row-fe-edit').removeClass('d-none');
            $('.enable-fe-editing').html($('.enable-fe-editing').data('label-disable'));
        } else {
            $('tr.row-fe-edit').addClass('d-none');
            $('.enable-fe-editing').html($('.enable-fe-editing').data('label-enable'));
        }

        /**
         * Show confirmation when enabling frontend-edit
         */
        $('.enable-fe-editing').click(function () {
            $('tr.row-fe-edit').toggleClass('d-none');
            if ($('tr.row-fe-edit').hasClass('d-none')) {
                alert('Frontend-Editing wurde deaktiviert.');
                sessionStorage.setItem('enable-frontend-edit', 'false');
                $(this).html($(this).data('label-enable'));
            }
            else {
                if(confirm('Soll "Frontend-Editing" aktiviert werden? Die Events können danach ausgewählt und bearbeitet werden. !Achtung gemachte Änderungen können nicht rückgängig gemacht werden.')) {
                    sessionStorage.setItem('enable-frontend-edit', 'true');
                    $(this).html($(this).data('label-disable'));
                }
            }
        });

        /**
         * Hide or show recurring events, when clicking the toggle-recurring-events button
         */
        $('.toggle-recurring-events').click(function () {
            if ($('body').hasClass('hide-recurring-events')) {
                $('body').removeClass('hide-recurring-events');
                $('*[data-recurringevent="true"]').show();
                $(this).html($(this).data('label-hide'));

            } else {
                $('body').addClass('hide-recurring-events');
                $('*[data-recurringevent="true"]').hide();
                $(this).html($(this).data('label-show'));
            }
        });

        /**
         * Scroll to last position
         */
        if (sessionStorage.getItem('scroll-top') > 0) {
            $(document).scrollTop(sessionStorage.getItem('scroll-top'));
            sessionStorage.setItem('scroll-top', 0);
        }


    });
})(jQuery);
