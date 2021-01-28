$(function() {
	var pagerOptions = {
		container: $(".pager"),
		output: '{startRow} - {endRow} / {filteredRows} ({totalRows})',
		fixedHeight: true,
		removeRows: false,
		cssGoto: '.gotoPage'
	};
  
	$('._listSubscriptions table, ._listPlaylists table, ._listVideos table').tablesorter({
		theme: 'bootstrap',
		headers: {
			0: { sorter: 'checkbox' },
			3: { sorter: 'select' },
			6: { sorter: 'inputs' }
		},
		widgets: ['zebra', 'filter', 'columns', 'stickyHeaders', 'uitheme', 'saveSort'],
		widgetOptions: {
			checkboxVisible: true,
			saveSort: true,
			filter_saveFilters: true
		}
	})
    .tablesorterPager(pagerOptions);
	
	$('._listSubscriptions table, ._listPlaylists table').sortable({ items: "tbody tr" });

    $('#btnIgnore, #btnUnignore, #btnSort, #btnPlaylist').click(function() {
		var arrId = [];
		var arrChecked = [];
		var action = getUrlParameter('action');
		var strCol = (action == '_listVideos') ? '3' : '2';

 		$('.odd, .even').each(function() {
			strHref = $(this).find('td:nth-child(' + strCol + ')').find('a').attr('href');
			strId = strHref.split(/[\/ ]+/).pop();
			strId = strId.split(/[\= ]+/).pop();
			arrId.push(strId);
		});

		$("tbody input[type='checkbox']").each(function() {
			strChecked = ($(this).is(':checked') == true) ? 1 : 0;
			arrChecked.push(strChecked);
		});

		arrOutput = [
			action,
			this.id,
			arrChecked,
			arrId,
			$('#selPlaylist option:selected').val(),
		];

		$.ajax({
			type: "POST",
			data: {data:arrOutput},
			url: "my-prime.php?action=_ajaxUpdate",
			success: function(data) {
				console.log(data);
				$('#status').html(data);
			}
		});
	});
	
	$('#checkAll').click(function(){
		$('tbody tr:visible input:checkbox').not(this).prop('checked', this.checked);
	});

	var getUrlParameter = function getUrlParameter(sParam) {
		var sPageURL = window.location.search.substring(1),
			sURLVariables = sPageURL.split('&'),
			sParameterName,
			i;

		for (i = 0; i < sURLVariables.length; i++) {
			sParameterName = sURLVariables[i].split('=');

			if (sParameterName[0] === sParam) {
				return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
			}
		}
	};
});