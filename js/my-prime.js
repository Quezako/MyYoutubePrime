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
			/*
			group_collapsible : true,  // make the group header clickable and collapse the rows below it.
			group_collapsed   : false, // start with all groups collapsed (if true)
			group_saveGroups  : true,  // remember collapsed groups
			group_saveReset   : '.group_reset', // element to clear saved collapsed groups
			group_count       : ' ({num})',
			group_forceColumn : [],   // only the first value is used; set as an array for future expansion
			group_enforceSort : true, // only apply group_forceColumn when a sort is applied to the table
			group_checkbox    : [ 'checked', 'unchecked' ],
			group_months      : [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ],
			group_week        : [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ],
			group_time        : [ 'AM', 'PM' ],
			group_time24Hour  : false,
			group_dateInvalid : 'Invalid Date',
			group_dateString  : function(date) {
				return date.toLocaleString();
			},
			group_formatter   : function(txt, col, table, c, wo, data) {
				if (col === 7 && txt.indexOf('GMT') > 0) {
					txt = txt.substring(0, txt.indexOf('GMT'));
				}
				
				return txt === '' ? 'Empty' : txt;
			},
			group_callback    : function($cell, $rows, column, table) {
				if (column === 2) {
					var subtotal = 0;
					$rows.each(function() {
						subtotal += parseFloat( $(this).find('td').eq(column).text() );
					});
					$cell.find('.group-count').append('; subtotal: ' + subtotal );
				}
			},
			group_complete: 'groupingComplete',
			*/
		}
	})
    .tablesorterPager(pagerOptions);
	
	
	$('._listSubscriptions table').sortable({ items: "tbody tr" });

    $('#btnIgnore, #btnUnignore').click(function() {
		var arrChannel = [];
		var arrChecked = [];

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
			this.id,
			arrChannel,
			arrChecked
		];

		$.ajax({
			type: "POST",
			data: {data:arrOutput},
			url: "my-prime.php?action=_ajaxUpdateChannels",
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
			this.id,
			arrChannel,
			arrChecked
		];

		$.ajax({
			type: "POST",
			data: {data:arrOutput},
			url: "my-prime.php?action=_ajaxUpdateChannels",
			success: function(data) {
				console.log(data);
				$('#status').html(data);
			}
		});
	});
});