
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
        slide_fun: function () {
            var el = document.querySelector('#carousel-example');
            if (!el) return;
            // Use Bootstrap 5 native API when available; otherwise skip to avoid errors
            if (window.bootstrap && typeof window.bootstrap.Carousel === 'function') {
                try {
                    new bootstrap.Carousel(el, { interval: 3000 });
                } catch (e) { /* ignore */ }
            }
            // No jQuery fallback to avoid $(...).carousel errors under mixed Bootstrap versions
        },
        dataTable_fun: function () {
            // Guard for DataTables availability
            if ($ && $.fn && typeof $.fn.dataTable === 'function') {
                var options = {
                    // Use legacy oLanguage keys for DataTables 1.10.0-dev in this project
                    oLanguage: {
                        sSearch: 'ค้นหา:',
                        sLengthMenu: 'แสดง _MENU_ รายการต่อหน้า',
                        sInfo: 'แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ',
                        sInfoEmpty: 'ไม่มีข้อมูลที่จะแสดง',
                        sInfoFiltered: '(กรองจากทั้งหมด _MAX_ รายการ)',
                        sZeroRecords: 'ไม่พบข้อมูลที่ตรงกัน',
                        oPaginate: { sPrevious: 'ก่อนหน้า', sNext: 'ถัดไป' }
                    },
                    columnDefs: [
                        { orderable: false, targets: [0, -1] }
                    ],
                    // Cleaner layout: search + length on top, info + pagination at bottom
                    dom: "<'row align-items-center mb-2'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6 text-end'l>>t<'row align-items-center mt-2'<'col-sm-12 col-md-6'i><'col-sm-12 col-md-6 text-end'p>>"
                };
                if ($.fn.DataTable.isDataTable('#dataTables-example')) {
                    // Already initialized, skip reinit to avoid warning
                    return;
                }
                var table = $('#dataTables-example').dataTable(options);
                // Polish controls with Bootstrap styling
                var wrapper = $('#dataTables-example').closest('.dataTables_wrapper');
                wrapper.find('.dataTables_filter input')
                    .addClass('form-control form-control-sm')
                    .attr('placeholder', 'พิมพ์คำค้น...');
                wrapper.find('.dataTables_length select').addClass('form-select form-select-sm');
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


