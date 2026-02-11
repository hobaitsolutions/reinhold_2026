// jQuery(document).ready(function () {
//     if (jQuery("#orderhistory").length) {
//         var table = jQuery('#orderhistory').DataTable(
//             {
//                 responsive: false,
//                 autoWidth: true,
//                 buttons: [
//                     {
//                         extend: 'copy',
//                     },
//                     {
//                         extend: 'excel',
//                         title: null
//                     },
//                     {
//                         extend: 'csv',
//                     },
//                     {
//                         text: 'JSON',
//                         action: function (e, dt, button, config) {
//                             var data = dt.buttons.exportData();
//
//                             $.fn.dataTable.fileSave(
//                                 new Blob([JSON.stringify(data)]),
//                                 'Export.json'
//                             );
//                         }
//                     },
//                     {
//                         extend: 'pdfHtml5',
//                         exportOptions: {
//                             columns: ':visible'
//                         },
//                         orientation: 'landscape'
//                     },
//                     {
//                         extend: 'print',
//                         exportOptions: {
//                             columns: ':visible'
//                         },
//                     },
//                 ],
//                 dom: 'lfrtipB',
//                 language: {
//                     "url": "/datatables_german.json"
//                 },
//                 pageLength: 25,
//                 order: [[1, "desc"]],
//                 // initComplete: function (settings, json) {
//                 //     jQuery('.table-loading').remove();
//                 //     jQuery('.table-wrap').show();
//                 // }
//             }
//         );
//         table.column(7).visible(false)
//         table.column(9).visible(false)
//         table.column(10).visible(false)
//         table.column(11).visible(false)
//         table.column(12).visible(false)
//     }
//
//
//     jQuery('.chose-columns input').on('click touch', function (e) {
//         var column = table.column(jQuery(this).val());
//         column.visible(!column.visible());
//     });
//
//     if (jQuery("#quickordertable").length) {
//         var quickorder = jQuery('#quickordertable').DataTable(
//             {
//                 responsive: true,
//                 autoWidth: true,
//                 //dom: 'lfrtipB',
//                 dom: 'pftripl',
//                 language: {
//                     "url": "/datatables_german.json"
//                 },
//                 search: {
//                     regex: true
//                 },
//                 // buttons: [],
//                 //  'columns': [
//                 //     {data: 'image'}, /* index - 0 */
//                 //     {data: 'productnumber'}, /* index - 1 */
//                 //     {data: 'text'}, /* index - 2 */
//                 //     {data: 'price'}, /* index - 3 */
//                 //     {data: 'buy'} /* index - 4 */
//                 // ],
//                 // 'columnDefs': [{
//                 //     'targets': [0, 4], /* column index */
//                 //     'orderable': false, /* true or false */
//                 // }],
//                 pageLength: 25,
//                 order: [[1, "asc"]],
//                 // initComplete: function (settings, json) {
//                 //     jQuery('.table-loading').remove();
//                 //     jQuery('#quickordertable').show();
//                 // }
//             }
//         );
//         // quickorder.buttons.disable();
//         // quickorder.responsive.recalc();
//     }
//
//     jQuery('.filterAdress').on('change', function () {
//         quickorder.column(1).search(jQuery(this).val(), true, false).draw();
//     });
//
//     $('.lightbox').topbox();
//
// });
