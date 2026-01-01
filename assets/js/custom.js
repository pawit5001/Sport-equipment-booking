
/*=============================================================
    Authour URI: www.binarytheme.com
    License: Commons Attribution 3.0

    http://creativecommons.org/licenses/by/3.0/

    100% Free To use For Personal And Commercial Use.
    IN EXCHANGE JUST GIVE US CREDITS AND TELL YOUR FRIENDS ABOUT US
   
    ========================================================  */

(function ($) {
    "use strict";
    var mainApp = {
        dataTable_fun: function () {
            // Only initialize DataTables if element exists and library is loaded
            if ($('#dataTables-example').length > 0 && typeof $.fn.dataTable !== 'undefined') {
                $('#dataTables-example').dataTable({
                    "drawCallback": function() {
                        // Prevent DataTables from interfering with dropdowns
                        $('.dropdown-toggle').off('click.bs.dropdown');
                    }
                });
            }
        },
       
        custom_fun:function()
        {
            /*====================================
             WRITE YOUR   SCRIPTS  BELOW
            ======================================*/




        },

    }
   
   
    $(document).ready(function () {
        mainApp.dataTable_fun();
        mainApp.custom_fun();
    });
}(jQuery));


