import $ from './jquery-global';
import 'bootstrap4/dist/js/bootstrap.bundle';
import 'jquery.easing';
import DataTable from 'datatables.net-bs4';
import Chart from 'chart.js/auto';
import './sb-admin-2';

window.DataTable = DataTable;
window.Chart = Chart;

// Chart defaults matching the SB Admin 2 demo charts.
Chart.defaults.font.family = 'Nunito, -apple-system, system-ui, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
Chart.defaults.color = '#858796';

const dataTableFrench = {
    processing: 'Traitement en cours...',
    search: 'Rechercher :',
    lengthMenu: 'Afficher _MENU_ éléments',
    info: 'Affichage de _START_ à _END_ sur _TOTAL_ éléments',
    infoEmpty: 'Aucun élément à afficher',
    infoFiltered: '(filtré de _MAX_ éléments au total)',
    loadingRecords: 'Chargement...',
    zeroRecords: 'Aucun résultat trouvé',
    emptyTable: 'Aucune donnée disponible',
    paginate: { first: 'Premier', previous: 'Précédent', next: 'Suivant', last: 'Dernier' },
};

$(function () {
    // Any table marked with .datatable gets search, sort and pagination.
    $('table.datatable').each(function () {
        new DataTable(this, {
            language: dataTableFrench,
            pageLength: 10,
            columnDefs: [{ targets: 'no-sort', orderable: false, searchable: false }],
        });
    });

    // Confirmation modals (delete, refuse, reject...): the button that opens the modal
    // carries the form action in data-action and a label in data-label.
    $(document).on('show.bs.modal', '.modal', function (event) {
        const trigger = $(event.relatedTarget);

        if (trigger.data('action')) {
            $(this).find('form').attr('action', trigger.data('action'));
            $(this).find('.js-delete-label, .js-action-label, .js-reject-label').text(trigger.data('label') || '');
        }
    });
});

// Make the template images available to Vite::asset() in Blade.
import.meta.glob('../../img/admin/**', { eager: true });
