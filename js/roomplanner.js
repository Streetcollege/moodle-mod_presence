//
// * Javascript for "rooms" feature extension
// *
// * Developer: 2020 Florian Metzger-Noel (github.com/flocko-motion)
//

require(['core/first', 'jquery'], function (core, $) {
    $(document).ready(function () {

        $('tbody').scroll(function () { // Detect a scroll event on the tbody.
            $('thead').css("left", -$("tbody").scrollLeft()); // Fix the thead relative to the body scrolling.
            $('thead th:nth-child(1)').css("left", $("tbody").scrollLeft()); // Fix the first cell of the header.
            $('tbody td:nth-child(1)').css("left", $("tbody").scrollLeft()); // Fix the first column of tdbody.
        });

        var draggingStatus = null;
        $('#presenceRoomPlannerScrollable tbody').mousedown(function (e) {
            var container = $('#presenceRoomPlannerScrollable tbody');
            draggingStatus = {
                left: container.scrollLeft(),
                top: container.scrollTop(),
                x: e.clientX,
                y: e.clientY,
            };
        });
        $('body').mousemove(function (e) {
            if (draggingStatus) {
                $('#presenceRoomPlannerScrollable tbody')
                    .scrollTop(draggingStatus.top - e.clientY + draggingStatus.y)
                    .scrollLeft(draggingStatus.left - e.clientX + draggingStatus.x);
            }
        }).mouseup(function () {
            draggingStatus = null;
        }).mouseleave(function () {
            draggingStatus = null;
        });

        function resizePresenceRoomPlan() {
            var w = $('#presenceRoomPlannerContainer').width();
            $('#presenceRoomPlannerScrollable thead').width(w + "px");
            $('#presenceRoomPlannerScrollable tbody').width(w + "px");
            $('#presenceRoomPlannerScrollable tbody').height(Math.floor(window.innerHeight / 2) + "px");
        }

        resizePresenceRoomPlan();
        $(window).on('resize', resizePresenceRoomPlan);
    });
});