//
// * Javascript for "rooms" feature extension
// *
// * Developer: 2020 Florian Metzger-Noel (github.com/flocko-motion)
//

require(['core/first', 'jquery'], function(core, $) {
    $(document).ready(function() {

        $('input[type=radio][name=multiple]').change(function() {
            if (this.value == 1) {
                $('[data-presence-sessions-multiple]').show();
            } else {
                $('[data-presence-sessions-multiple]').hide();
            }
        });

        $('#presence_delete_sessions_dialog').show();

    });
});