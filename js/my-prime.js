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
  
	// $('#groups').tablesorter({
	$('.group1 table').tablesorter({
		// theme : 'blue',
		theme: 'bootstrap',
		headers: {
			0: { sorter: 'checkbox' },
			3: { sorter: 'select' },
			6: { sorter: 'inputs' }
			// 7: defaults to 'shortDate', but set to 'weekday-index' ('group-date-weekday') or 'time' ('group-date-time')
		},
		// widgets: ['zebra', 'filter', 'group', 'columns', 'output'],
		// widgets: ['zebra', 'stickyHeaders', 'uitheme'],
		widgets: ['zebra', 'filter', 'group', 'columns', 'output', 'stickyHeaders', 'uitheme'],
		widgetOptions: {
			group_collapsible : true,  // make the group header clickable and collapse the rows below it.
			group_collapsed   : false, // start with all groups collapsed (if true)
			group_saveGroups  : true,  // remember collapsed groups
			group_saveReset   : '.group_reset', // element to clear saved collapsed groups
			group_count       : ' ({num})', // if not false, the '{num}' string is replaced with the number of rows in the group

			// apply the grouping widget only to selected column
			group_forceColumn : [],   // only the first value is used; set as an array for future expansion
			group_enforceSort : true, // only apply group_forceColumn when a sort is applied to the table

			// checkbox parser text used for checked/unchecked values
			group_checkbox    : [ 'checked', 'unchecked' ],

			// change these default date names based on your language preferences (see Globalize section for details)
			group_months      : [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ],
			group_week        : [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ],
			group_time        : [ 'AM', 'PM' ],

			// use 12 vs 24 hour time
			group_time24Hour  : false,
			// group header text added for invalid dates
			group_dateInvalid : 'Invalid Date',

			// this function is used when 'group-date' is set to create the date string
			// you can just return date, date.toLocaleString(), date.toLocaleDateString() or d.toLocaleTimeString()
			// reference: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date#Conversion_getter
			group_dateString  : function(date) {
				return date.toLocaleString();
			},

			group_formatter   : function(txt, col, table, c, wo, data) {
				// txt = current text; col = current column
				// table = current table (DOM); c = table.config; wo = table.config.widgetOptions
				// data = group data including both group & row data
				if (col === 7 && txt.indexOf('GMT') > 0) {
					// remove 'GMT-0000 (Xxxx Standard Time)' from the end of the full date
					// this code is needed if group_dateString returns date.toString(); (not localeString)
					txt = txt.substring(0, txt.indexOf('GMT'));
				}
				// If there are empty cells, name the group 'Empty'
				return txt === '' ? 'Empty' : txt;
			},

			group_callback    : function($cell, $rows, column, table) {
				// callback allowing modification of the group header labels
				// $cell = current table cell (containing group header cells '.group-name' & '.group-count'
				// $rows = all of the table rows for the current group; table = current table (DOM)
				// column = current column being sorted/grouped
				if (column === 2) {
					var subtotal = 0;
					$rows.each(function() {
						subtotal += parseFloat( $(this).find('td').eq(column).text() );
					});
					$cell.find('.group-count').append('; subtotal: ' + subtotal );
				}
			},
			// event triggered on the table when the grouping widget has finished work
			group_complete    : 'groupingComplete',
			checkboxVisible: true
			
			/** OUTPUT **/
			/*
			output_separator     : ',',         // ',' 'json', 'array' or separator (e.g. ';')
			output_ignoreColumns : [0],         // columns to ignore [0, 1,... ] (zero-based index)
			output_hiddenColumns : false,       // include hidden columns in the output
			output_includeFooter : true,        // include footer rows in the output
			output_includeHeader : true,        // include header rows in the output
			output_headerRows    : false,       // output all header rows (if multiple rows)
			output_dataAttrib    : 'data-name', // data-attribute containing alternate cell text
			output_delivery      : 'p',         // (p)opup, (d)ownload
			output_saveRows      : 'f',         // (a)ll, (v)isible, (f)iltered, jQuery filter selector (string only) or filter function
			output_duplicateSpans: true,        // duplicate output data in tbody colspan/rowspan
			output_replaceQuote  : '\u201c;',   // change quote to left double quote
			output_includeHTML   : true,        // output includes all cell HTML (except the header cells)
			output_trimSpaces    : false,       // remove extra white-space characters from beginning & end
			output_wrapQuotes    : false,       // wrap every cell output in quotes
			output_popupStyle    : 'width=580,height=310',
			output_saveFileName  : 'mytable.csv',
			// callback executed after the content of the table has been processed
			output_formatContent : function(config, widgetOptions, data) {
				// data.isHeader (boolean) = true if processing a header cell
				// data.$cell = jQuery object of the cell currently being processed
				// data.content = processed cell content (spaces trimmed, quotes added/replaced, etc)
				// data.columnIndex = column in which the cell is contained
				// data.parsed = cell content parsed by the associated column parser
				return data.content;
			},
			// callback executed when processing completes
			output_callback      : function(config, data, url) {
				// return false to stop delivery & do something else with the data
				// return true OR modified data (v2.25.1) to continue download/output
				return true;
			},
			// callbackJSON used when outputting JSON & any header cells has a colspan - unique names required
			output_callbackJSON  : function($cell, txt, cellIndex) {
				return txt + '(' + cellIndex + ')';
			},
			// the need to modify this for Excel no longer exists
			output_encoding      : 'data:application/octet-stream;charset=utf8,',
			// override internal save file code and use an external plugin such as
			// https://github.com/eligrey/FileSaver.js
			output_savePlugin    : null 
			*/
		}
	})
    // initialize the pager plugin
    // ****************************
    .tablesorterPager(pagerOptions);

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
			url: "my-prime.php?action=_updateChannels",
			success: function(data) {
				console.log(data);
				$('#status').html(data);
			}
		});
	});
	
	$("#checkAll").click(function(){
		$('tbody tr:visible input:checkbox').not(this).prop('checked', this.checked);
	});


	/** OUTPUT **/
	/*
	var demos = ['.group1'];
	
    $.each(demos, function(groupIndex) {
        var $this = $(demos[groupIndex]);

        $this.find('.dropdown-toggle').click(function(e) {
            // this is needed because clicking inside the dropdown will close
            // the menu with only bootstrap controlling it.
            $this.find('.dropdown-menu').toggle();
            return false;
        });
        // make separator & replace quotes buttons update the value
        $this.find('.output-separator').click(function() {
            $this.find('.output-separator').removeClass('active');
            var txt = $(this).addClass('active').html();
            $this.find('.output-separator-input').val( txt );
            $this.find('.output-filename').val(function(i, v) {
                // change filename extension based on separator
                var filetype = (txt === 'json' || txt === 'array') ? 'js' :
                    txt === ',' ? 'csv' : 'txt';
                return v.replace(/\.\w+$/, '.' + filetype);
            });
            return false;
        });
        $this.find('.output-quotes').click(function() {
            $this.find('.output-quotes').removeClass('active');
            $this.find('.output-replacequotes').val( $(this).addClass('active').text() );
            return false;
        });
        // header/footer toggle buttons
        $this.find('.output-header, .output-footer').click(function() {
            $(this).toggleClass('active');
        });
        // clicking the download button; all you really need is to
        // trigger an 'output' event on the table
        $this.find('.download').click(function() {
            var typ,
                $table = $this.find('table'),
                wo = $table[0].config.widgetOptions,
                val = $this.find('.output-filter-all :checked').attr('class');
            wo.output_saveRows = val === 'output-filter' ? 'f' :
                val === 'output-visible' ? 'v' :
                // checked class name, see table.config.checkboxClass
                val === 'output-selected' ? '.checked' :
                val === 'output-sel-vis' ? '.checked:visible' :
                'a';
            val = $this.find('.output-download-popup :checked').attr('class');
            wo.output_delivery        	= val === 'output-download' ? 'd' : 'p';
            wo.output_separator        	= $this.find('.output-separator-input').val();
            wo.output_replaceQuote 		= $this.find('.output-replacequotes').val();
            wo.output_trimSpaces     	= $this.find('.output-trim').is(':checked');
            wo.output_includeHTML    	= $this.find('.output-html').is(':checked');
            wo.output_wrapQuotes     	= $this.find('.output-wrap').is(':checked');
            wo.output_saveFileName 		= $this.find('.output-filename').val();

            // first example buttons, second has radio buttons
            if (groupIndex === 0) {
                wo.output_includeHeader = $this.find('button.output-header').is('.active');
            } else {
                wo.output_includeHeader = !$this.find('.output-no-header').is(':checked');
                wo.output_headerRows = $this.find('.output-headers').is(':checked');
            }
            // footer not included in second example
            wo.output_includeFooter = $this.find('.output-footer').is('.active');

            $table.trigger('outputTable');
            return false;
        });

        // add tooltip
        // $this.find('.dropdown-menu [title]').tipsy({ gravity: 's' });

    });
	*/
});