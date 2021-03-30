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

    $('#btnIgnore, #btnUnignore, #btnSort, #btnPlaylist, #btnType').click(function() {
		var arrId = [];
		var arrChecked = [];
		var action = getUrlParameter('action');
		var strCol = (action == '_listVideos') ? '3' : '2';
		$('#status').html('loading...');

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
			$('#selChannelTypes option:selected').val(),
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
	
	$('#selFilter').change(function() {
		console.log(this.value);
		strFilter = 'PL52YqI0PbEYU1_3bne33bXQYAviFN8AD4';
		
		if (this.value > 15) {
			strFilter = 'PLPjkJ-eKlc2yp5mv6wuVo_UfhT2ZsZk5m';
		}
		
		switch(this.value) {
			case "3": // Bob
				strFilter = 'PL52YqI0PbEYXMGfA71veOZCM_4NAWVopj';
				break;
			case "4": // Zera
				strFilter = 'PL52YqI0PbEYX18AmDYyv1lx28lIzwYgTb';
				break;
			case "8": // Podcast: health|philo|documentary
				strFilter = 'PL52YqI0PbEYUxtItqu6WAIqj1C7hX4LUL';
				break;
			case "9": // Asia
				strFilter = 'PL52YqI0PbEYXzJuYKJFyCCu1lREuoMhnO';
				break;
			case "15": // Long
				strFilter = 'PL52YqI0PbEYXZv2APe7B3wPkgdVXi1yK7';
				break;
		}
		
		var objType = {
			'1': ['', '', '', '<16', '<89', '', '!friends'], //Prime
			'2': ['', '', '', '<5', '<89', '', ''], //Prime Short
			'3': ['', 'bob lennon', '', '', '', '', ''], //Bob
			'4': ['', 'zerator', '', '', '', '', ''], //Zera
			'5': ['', '', '', '', '', '', 'gamers|humor'], //gamers|humor
			'6': ['', '', '', '', '', '', 'education|technologies'], //education|technologies
			'7': ['', '', '', '', '', '', 'gaming|zap|fun'], //gaming|zap|fun
			'8': ['', '', '', '>40', '', '', 'health|philo|documentary'], //Podcast: health|philo|documentary
			'9': ['', '', '', '', '', '', 'asia|travel|art'], //Asia|travel|art
			'10': ['', '', '', '', '', '', 'fiction'], //fiction
			'11': ['', '', '', '', '', '', 'TV / Radio|streaming'], //TV / Radio|streaming
			'12': ['', '', '', '', '', '', 'friends'], //friends
			'13': ['', '', '', '<2', '', '', ''], //Very Short
			'14': ['', '', '', '>15 and <39', '>2 and <89', '', ''], //Medium
			'15': ['', '', '', '>40', '', '', ''], //Long
			'16': ['', '', '', '>2 and <15', '', '>2014', 'techno|trance|house|electronic'], //MUSIC Electro
			'17': ['', '', '', '>2 and <15', '', '>2014', 'dance'], //MUSIC Dance
			'18': ['', '', '', '>2 and <15', '', '>2014', 'rock|pop'], //MUSIC Rock / Pop
			'19': ['', '', '', '>2 and <15', '', '>2014', 'france'], //MUSIC France
			'20': ['', '', '', '>2 and <15', '', '>2014', '!(techno|trance|house|electronic|dance|rock|pop|france)'], //MUSIC Other
			'21': ['', '', 'live', '', '', ''], //MUSIC live
			'22': ['', '', '', '<3 or >14', '', ''], //MUSIC bad length
			'99': [], //Clear
		};
		arrFilter = (objType[this.value] || objType['1']);
		
		$('#tblVideos').trigger('search', [ arrFilter ]);
		$('#selPlaylist').val(strFilter);
	});

});