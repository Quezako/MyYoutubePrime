$(function() {
	var pagerOptions = {
		container: $(".pager"),
		output: '{startRow} - {endRow} / {filteredRows} ({totalRows})',
		fixedHeight: true,
		removeRows: false,
		cssGoto: '.gotoPage'
	};
  
	$('._listSubscriptions table, ._listVideos table').tablesorter({
		theme: 'bootstrap',
		headers: {
			0: { sorter: 'checkbox' },
			3: { sorter: 'select' },
			6: { sorter: 'inputs' }
		},
		widgets: ['zebra', 'filter', 'group', 'columns', 'output', 'stickyHeaders', 'uitheme', 'saveSort'],
		widgetOptions: {
			checkboxVisible: true,
			saveSort: true,
			filter_saveFilters: true
		}
	})
    .tablesorterPager(pagerOptions);
	
	$('._listSubscriptions table').sortable({ items: "tbody tr" });

    $('#btnIgnore, #btnUnignore').click(function() {
		var arrChannel = [];
		var arrVideo = [];
		var arrChecked = [];
		var action = getUrlParameter('action');
		var strCol = (action == '_listVideos') ? '3' : '2';

 		$('.odd, .even').each(function() {
			strHref = $(this).find('td:nth-child(' + strCol + ')').find('a').attr('href');
			strChannel = strHref.split(/[\/ ]+/).pop();
			strChannel = strChannel.split(/[\= ]+/).pop();
			arrChannel.push(strChannel);
		});

		$("tbody input[type='checkbox']").each(function() {
			strChecked = ($(this).is(':checked') == true) ? 1 : 0;
			arrChecked.push(strChecked);
		});

		arrOutput = [
			action,
			this.id,
			arrChecked,
			arrChannel
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
	
    $('#btnSort').click(function() {
		var arrChannel = [];
		var arrChecked = [];
		var action = getUrlParameter('action');

		$('.odd, .even').each(function() {
			strHref = $(this).find('td').find('a').attr('href');
			strChannel = strHref.substring(strHref.lastIndexOf('/'));
			strChannel = strHref.split(/[\/ ]+/).pop();
			arrChannel.push(strChannel);
		});

		$("tbody input[type='checkbox']").each(function() {
			strChecked = ($(this).is(':checked') == true) ? 1 : 0;
			arrChecked.push(strChecked);
		});

		arrOutput = [
			action,
			this.id,
			arrChecked,
			arrChannel
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