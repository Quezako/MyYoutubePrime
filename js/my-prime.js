$(function() {
	var pagerOptions = {
		container: $(".pager"),
		output: "{startRow} - {endRow} / {filteredRows} ({totalRows})",
		fixedHeight: true,
		removeRows: false,
		cssGoto: ".gotoPage",
		
        // css class names of pager arrows
        css: {
			container   : "tablesorter-pager",
			errorRow    : "tablesorter-errorRow", // error information row (don"t include period at beginning)
			disabled    : "disabled"              // class added to arrows @ extremes (i.e. prev/first arrows "disabled" on first page)
        },

        // jQuery selectors
        selectors: {
			container   : ".pager",       // target the pager markup (wrapper)
			first       : ".first",       // go to first page arrow
			prev        : ".prev",        // previous page arrow
			next        : ".next",        // next page arrow
			last        : ".last",        // go to last page arrow
			gotoPage    : ".gotoPage",    // go to page selector - select dropdown that sets the current page
			pageDisplay : ".pagedisplay", // location of where the "output" is displayed
			pageSize    : ".pagesize"     // page size selector - select dropdown that sets the "size" option
        },

        output: "{startRow} - {endRow} / {filteredRows} ({totalRows})",
        updateArrows: true,
        startPage: 0,
        pageReset: 0,
        size: 50,
        countChildRows: false,
        savePages: true,
        storageKey: "tablesorter-pager",
        fixedHeight: false,
        removeRows: false, // removing rows in larger tables speeds up the sort
        ajaxUrl: "my-prime.php?page={page}&size={size}&{filterList:filter}&{sortList:column}",

        // use this option to manipulate and/or add additional parameters to the ajax url
        customAjaxUrl:  function(table, url) {
            // manipulate the url string as you desire
			urlParams = new URLSearchParams(window.location.search);
            url += "&action=" + urlParams.get("action");
            // trigger my custom event
            $(table).trigger("changingUrl", url);
            // send the server the current page
            return url;
        },
        // ajax error callback from $.tablesorter.showError function
        ajaxError: null,
        // modify the $.ajax object to allow complete control over your ajax requests
        ajaxObject: {
          type: "GET", // default setting
          dataType: "json"
        },
        // Set this option to false if your table data is preloaded into the
        // table, but you are still using ajax
        processAjaxOnInit: true,
        // When processAjaxOnInit is set to false, set this option to contain
        ajaxProcessing: function(data) {
			if (data && data.hasOwnProperty("rows")) {
				var indx, r, row, c, d = data.rows,
				// total number of rows (required)
				total = data.total_rows,
				// array of header names (optional)
				headers = data.headers,
				// cross-reference to match JSON key within data (no spaces)
				headerXref = headers.join(",").replace(/\s+/g,"").split(","),
				// all rows: array of arrays; each internal array has the table cell data for that row
				rows = [],
				// len should match pager set size (c.size)
				len = d.length;
				// this will depend on how the json is set up - see City0.json
				// rows
				for ( r=0; r < len; r++ ) {
					row = []; // new row array
					// cells
					for ( c in d[r] ) {
						if (typeof(c) === "string") {
							// match the key with the header to get the proper column index
							indx = $.inArray( c, headerXref );
							// add each table cell data to row array
							if (indx >= 0) {
								row[indx] = d[r][c];
							}
						}
					}
					rows.push(row); // add new row array to rows array
				}
				// in version 2.10, you can optionally return $(rows) a set of table rows within a jQuery object
				return [ total, rows, headers ];
			}
        }
	};
  
	$("table").tablesorter({
		theme: "bootstrap",
		widthFixed: true,
		sortLocaleCompare: true, // needed for accented characters in the data
		sortList: [ [0,1] ],
		headers: {
			0: { sorter: "checkbox" },
			3: { sorter: "select" },
			6: { sorter: "inputs" }
		},
		widgets: ["zebra", "filter", "columns", "stickyHeaders", "uitheme", "saveSort", "pager"],
		widgetOptions: {
			checkboxVisible: true,
			saveSort: true,
			filter_saveFilters: true,
			zebra : ["even", "odd"],
			columns: [ "primary", "secondary", "tertiary" ],
			filter_reset : ".reset",
			filter_cssFilter: [
				"form-control",
				"form-control",
				"form-control", // select needs custom class names :(
				"form-control",
				"form-control",
				"form-control",
				"form-control"
			],
			
			initialRows: {
			  total: 100,
			  filtered: 100
			},
		}
	})
    .tablesorterPager(pagerOptions);
	
	$("._listSubscriptions table, ._listPlaylists table").sortable({ items: "tbody tr" });

    $("#btnIgnore, #btnUnignore, #btnSort, #btnPlaylist, #btnType").click(function() {
		var arrId = [];
		var arrChecked = [];
		var action = getUrlParameter("action");
		var strCol = (action == "_listVideos") ? "3" : "2";
		// $("#status").alert();
		// $("#status").alert('show');
		$("#status").show();
		$("#status").html("<img src='img/download.gif' /> Loading...");

 		$(".odd, .even").each(function() {
			strHref = $(this).find("td:nth-child(" + strCol + ")").find("a").attr("href");
			strId = strHref.split(/[\/ ]+/).pop();
			strId = strId.split(/[\= ]+/).pop();
			arrId.push(strId);
		});

		$("tbody input[type='checkbox']").each(function() {
			strChecked = ($(this).is(":checked") == true) ? 1 : 0;
			arrChecked.push(strChecked);
		});

		arrOutput = [
			action,
			this.id,
			arrChecked,
			arrId,
			$("#selPlaylist option:selected").val(),
			$("#selChannelTypes option:selected").val(),
		];

		$.ajax({
			type: "POST",
			data: {data:arrOutput},
			url: "my-prime.php?action=_ajaxUpdate",
			success: function(data) {
				// console.log(data);
				$("#status").html(data + "<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>");
				// $("#status").hide();
			}
		});
	});
	
    $("#_updateSubscriptions, #_updatePlaylists, #_updateAll, #_updatePlaylistsDetails, #_updateVideos, #_updateVideosDetails").click(function() {
		// $("#status").alert();
		$("#status").show();
		$("#status").html("<img src='img/download.gif' /> Loading...");
		console.log(this.id);
		$.ajax({
			type: "GET",
			url: "my-prime.php?action=" + this.id,
			success: function(data) {
				// console.log(data);
				$("#status").html(data + "<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>");
				// $("#status").hide();
			}
		});
	});
	
	$("#checkAll").click(function(){
		$("tbody tr:visible input:checkbox").not(this).prop("checked", this.checked);
	});

	var getUrlParameter = function getUrlParameter(sParam) {
		var sPageURL = window.location.search.substring(1),
			sURLVariables = sPageURL.split("&"),
			sParameterName,
			i;

		for (i = 0; i < sURLVariables.length; i++) {
			sParameterName = sURLVariables[i].split("=");

			if (sParameterName[0] === sParam) {
				return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
			}
		}
	};
	
	$("#selFilter").change(function() {
		console.log(this.value);
		arrSel = this.value.split("^");
		$("table").trigger("search", [ arrSel[0].split(",") ]);
		$("#selPlaylist").val(arrSel[1]);
	});
	
	$("input[type=radio]").change(function() {
		$("input[type=radio]").each(function(){
			Cookies.set("radio_" + $(this).attr("id"), JSON.stringify({checked: this.checked}), { expires : 777 });
			console.log("1: " + $(this).attr("id") + "" + Cookies.get("radio_" + $(this).attr("id")));
		});
		location.reload(true);
	});

	// Save radio input state to local storage.
	$(function(){
		$("input[type=radio]").each(function(){
			var state = JSON.parse(Cookies.get("radio_" + $(this).attr("id")));
			console.log("2: " + $(this).attr("id") + "" + Cookies.get("radio_" + $(this).attr("id")));
			
			if (state) this.checked = state.checked;
		});
	});
	
	$("#status").on("close.bs.alert", function () {
		$("#status").hide();
		return false;
	});
	
	$("#status").hide();
});