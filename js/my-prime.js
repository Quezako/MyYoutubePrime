$(function() {
	// var $table = $('table'),
	// define pager options
	var pagerOptions = {
		// target the pager markup - see the HTML block below
		container: $(".pager"),
		// output string - default is '{page}/{totalPages}';
		// possible variables: {size}, {page}, {totalPages}, {filteredPages}, {startRow}, {endRow}, {filteredRows} and {totalRows}
		// also {page:input} & {startRow:input} will add a modifiable input in place of the value
		output: '{startRow} - {endRow} / {filteredRows} ({totalRows})',
		// if true, the table will remain the same height no matter how many records are displayed. The space is made up by an empty
		// table row set to a height to compensate; default is false
		fixedHeight: true,
		// remove rows from the table to speed up the sort of large tables.
		// setting this to false, only hides the non-visible rows; needed if you plan to add/remove rows with the pager enabled.
		removeRows: false,
		// go to page selector - select dropdown that sets the current page
		cssGoto: '.gotoPage'
	};
  
	$("#groups").tablesorter({
		theme : "blue",
		headers: {
			0: { sorter: "checkbox" },
			3: { sorter: "select" },
			6: { sorter: "inputs" }
			// 7: defaults to "shortDate", but set to "weekday-index" ("group-date-weekday") or "time" ("group-date-time")
		},
		widgets: [ "group", "columns", "filter", "zebra" ],
		widgetOptions: {
			group_collapsible : true,  // make the group header clickable and collapse the rows below it.
			group_collapsed   : false, // start with all groups collapsed (if true)
			group_saveGroups  : true,  // remember collapsed groups
			group_saveReset   : '.group_reset', // element to clear saved collapsed groups
			group_count       : " ({num})", // if not false, the "{num}" string is replaced with the number of rows in the group

			// apply the grouping widget only to selected column
			group_forceColumn : [],   // only the first value is used; set as an array for future expansion
			group_enforceSort : true, // only apply group_forceColumn when a sort is applied to the table

			// checkbox parser text used for checked/unchecked values
			group_checkbox    : [ 'checked', 'unchecked' ],

			// change these default date names based on your language preferences (see Globalize section for details)
			group_months      : [ "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec" ],
			group_week        : [ "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday" ],
			group_time        : [ "AM", "PM" ],

			// use 12 vs 24 hour time
			group_time24Hour  : false,
			// group header text added for invalid dates
			group_dateInvalid : 'Invalid Date',

			// this function is used when "group-date" is set to create the date string
			// you can just return date, date.toLocaleString(), date.toLocaleDateString() or d.toLocaleTimeString()
			// reference: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date#Conversion_getter
			group_dateString  : function(date) {
				return date.toLocaleString();
			},

			group_formatter   : function(txt, col, table, c, wo, data) {
				// txt = current text; col = current column
				// table = current table (DOM); c = table.config; wo = table.config.widgetOptions
				// data = group data including both group & row data
				if (col === 7 && txt.indexOf("GMT") > 0) {
					// remove "GMT-0000 (Xxxx Standard Time)" from the end of the full date
					// this code is needed if group_dateString returns date.toString(); (not localeString)
					txt = txt.substring(0, txt.indexOf("GMT"));
				}
				// If there are empty cells, name the group "Empty"
				return txt === "" ? "Empty" : txt;
			},

			group_callback    : function($cell, $rows, column, table) {
				// callback allowing modification of the group header labels
				// $cell = current table cell (containing group header cells ".group-name" & ".group-count"
				// $rows = all of the table rows for the current group; table = current table (DOM)
				// column = current column being sorted/grouped
				if (column === 2) {
					var subtotal = 0;
					$rows.each(function() {
						subtotal += parseFloat( $(this).find("td").eq(column).text() );
					});
					$cell.find(".group-count").append("; subtotal: " + subtotal );
				}
			},
			// event triggered on the table when the grouping widget has finished work
			group_complete    : "groupingComplete"
		}
	})
    // initialize the pager plugin
    // ****************************
    .tablesorterPager(pagerOptions);



	/* DEMO ONLY CODE */
/*
	var $groups = $('#groups'),
		startBlock = 10,
		startVal = 1,
		curGroup = 0,
		numcol = 2,
		letcol = 4,
		datecol = 7,
		dateGroups = [ '', 'year', 'month', 'monthyear', 'day', 'week', 'time', 'hour' ];
	// Numeric column slider
	$( "#slider0" ).slider({
		value: startBlock,
		min: 10,
		max: 50,
		step: 10,
		create: function() {
			$('.numberclass').html(' "group-number-' + startBlock + '"');
			$groups.find('.tablesorter-header-inner:eq(' + numcol + ')').find('span').html(startBlock);
		},
		slide: function( event, ui ) {
			$groups[0].config.$headers.eq(numcol)
				.attr('class', function(i,v) {
					return v.replace(/group-number-\d+/, 'group-number-' + ui.value);
				})
				.trigger('sorton', [ [[numcol,0]] ]);
			$('.numberclass').html(' "group-number-' + ui.value + '"');
			$groups.find('.tablesorter-header-inner:eq(' + numcol + ')').find('span').html(ui.value);
		}
	});
	// animal (letter) column slider
	$( "#slider1" ).slider({
		value: startVal,
		min: 1,
		max: 5,
		step: 1,
		create: function() {
			$('.animalclass').html(' "group-letter-' + startVal + '"');
			$groups.find('.tablesorter-header-inner:eq(' + letcol + ')').find('span').html(startVal === 1 ? 'letter' : startVal + ' letters');
		},
		slide: function( event, ui ) {
			$groups[0].config.$headers.eq(letcol)
				.attr('class', function(i,v) {
					return v.replace(/group-letter-\d+/, 'group-letter-' + ui.value);
				})
				.trigger('sorton', [ [[letcol,0]] ]);
			$('.animalclass').html(' "group-letter-' + ui.value + '"');
			$groups.find('.tablesorter-header-inner:eq(' + letcol + ')').find('span').html(ui.value === 1 ? 'letter' : ui.value + ' letters');
		}
	});
	// date column slider
	$( "#slider2" ).slider({
		value: curGroup,
		min: 0,
		max: dateGroups.length - 1,
		step: 1,
		create: function() {
			$('.dateclass').html(' "group-date' + (curGroup > 0 ? '-' + dateGroups[curGroup] : '') + '"');
			$groups.find('.tablesorter-header-inner:eq(' + datecol + ')').find('span').html(curGroup === 0 ? 'full' : dateGroups[curGroup]);
		},
		slide: function( event, ui ) {
			var $h = $groups[0].config.$headers.eq(datecol)
				.attr('class', function(i,v) {
					return v.replace(/group-date(-\w+)?/, 'group-date' + (ui.value > 0 ? '-' + dateGroups[ui.value] : ''));
				});
			$h.removeClass('sorter-weekday-index sorter-time');
			if ( dateGroups[ui.value] === 'week' ) {
				$h.addClass('sorter-weekday-index');
				$groups.trigger('update', [ [[datecol,0]] ]);
			} else if ( dateGroups[ui.value] === 'time' || dateGroups[ui.value] === 'hour' ) {
				$h.addClass('sorter-time');
				$groups.trigger('update', [ [[datecol,0]] ]);
			} else {
				$groups.trigger('update', [ [[datecol,0]] ]);
			}
			$('.dateclass').html(' "group-date' + (ui.value > 0 ? '-' + dateGroups[ui.value] : '') + '"');
			$groups.find('.tablesorter-header-inner:eq(' + datecol + ')').find('span').html(ui.value === 0 ? 'full' : dateGroups[ui.value]);
		}
	});
*/
});