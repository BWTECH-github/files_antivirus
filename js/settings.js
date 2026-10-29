var antivirusSettings = antivirusSettings || {
	statuses : [
		{ value : 0, title : t('files_antivirus', 'Clean')},
		{ value : 1, title : t('files_antivirus', 'Infected')},
		{ value : -1, title : t('files_antivirus', 'Unchecked')}
	],
	types : [
		{ value : 1, title : t('files_antivirus', 'Scanner exit status') },
		{ value : 2, title : t('files_antivirus', 'Scanner output') },
	],
	/**
	 * Die Beschreibungen der mitgelieferten Regeln schreibt RuleMapper::populate()
	 * englisch in die Datenbank. Zur Anzeige werden diese bekannten Texte
	 * übersetzt; eigene Beschreibungen bleiben, wie sie sind.
	 *
	 * @param {string} text Beschreibung aus der Datenbank
	 * @return {string}
	 */
	describe : function(text){
		var bekannt = {
			'Unknown option passed.': t('files_antivirus', 'Unknown option passed.'),
			'Database initialization error.': t('files_antivirus', 'Database initialization error.'),
			'Not supported file type.': t('files_antivirus', 'Not supported file type.'),
			"Can't open directory.": t('files_antivirus', "Can't open directory."),
			"Can't open file. (ofm)": t('files_antivirus', "Can't open file. (ofm)"),
			'Error reading file. (ofm)': t('files_antivirus', 'Error reading file. (ofm)'),
			"Can't stat input file / directory.": t('files_antivirus', "Can't stat input file / directory."),
			"Can't get absolute path name of current working directory.": t('files_antivirus', "Can't get absolute path name of current working directory."),
			'I/O error, please check your file system.': t('files_antivirus', 'I/O error, please check your file system.'),
			"Can't initialize logger.": t('files_antivirus', "Can't initialize logger."),
			"Can't create temporary files/directories (check permissions).": t('files_antivirus', "Can't create temporary files/directories (check permissions)."),
			"Can't write to temporary directory (please specify another one).": t('files_antivirus', "Can't write to temporary directory (please specify another one)."),
			"Can't allocate memory (calloc).": t('files_antivirus', "Can't allocate memory (calloc)."),
			"Can't allocate memory (malloc).": t('files_antivirus', "Can't allocate memory (malloc).")
		};
		return Object.prototype.hasOwnProperty.call(bekannt, text) ? bekannt[text] : text;
	},

	init : function(){
		$.get(OC.generateUrl('apps/files_antivirus/settings/rule/listall'),
			function onSuccess(response){
				if (!response || !response.statuses){
					return;
				}
				for (var i = 0; i < response.statuses.length; i++) {
					antivirusSettings.renderRow(response.statuses[i]);
				}
			}
		);
	},

	renderRow : function(data){
		var row = $('<tr />').data('id', data.id).appendTo($('#antivirus-statuses'));
		$('<td class="icon-checkmark shaded" />')
			.attr('role', 'button')
			.attr('tabindex', '0')
			.attr('aria-label', t('files_antivirus', 'Save rule'))
			.attr('title', t('files_antivirus', 'Save rule'))
			.attr('aria-disabled', 'true')
			.appendTo(row);
		// Auswahlfelder tragen die Spaltenüberschrift als Namen (axe select-name)
		antivirusSettings.renderSelect(
				$('<td class="status-type" />').appendTo(row),
				{options : antivirusSettings.types, current : data.status_type, label : t('files_antivirus', 'Match by')}
		);
		// Textzellen per Tastatur erreichbar (Enter oder F2 öffnet das Feld)
		$('<td class="match editable" tabindex="0" />').appendTo(row).text(
				(data.status_type == 1 ? data.result : data.match)
		);
		// Original merken: unverändert gespeichert bleibt der Datenbankwert, nicht
		// die Übersetzung der Anzeige.
		$('<td class="description editable" tabindex="0" />').appendTo(row)
			.data('original', data.description)
			.text(antivirusSettings.describe(data.description));
		antivirusSettings.renderSelect(
				$('<td class="scan-result" />').appendTo(row),
				{ options : antivirusSettings.statuses, current : data.status, label : t('files_antivirus', 'Mark as') }
		);

		$('<td class="icon-delete" />')
			.attr('role', 'button')
			.attr('tabindex', '0')
			.attr('aria-label', t('files_antivirus', 'Delete rule'))
			.attr('title', t('files_antivirus', 'Delete rule'))
			.appendTo(row);
	},

	/**
	 * Beschreibung zum Speichern: steht noch die übersetzte Anzeige des
	 * Originals in der Zelle, geht das Original zurück an den Server.
	 *
	 * @param {jQuery} cell Zelle .description
	 * @return {string}
	 */
	descriptionOf : function(cell){
		var original = cell.data('original');
		if (typeof original === 'string' && cell.text() === antivirusSettings.describe(original)) {
			return original;
		}
		return cell.text();
	},

	onSave : function(){
		if ($(this).attr('aria-disabled') === 'true') {
			return;
		}
		var node = $(this),
		row = $(node).parent(),
		data = {
			id : row.data('id'),
			statusType : row.find('.status-type select').val(),
			match : row.children('.match').text(),
			description : antivirusSettings.descriptionOf(row.children('.description')),
			status : row.find('.scan-result select').val()
		};

		$.post(OC.generateUrl('apps/files_antivirus/settings/rule/save'), data,
			function onSuccess(response){
				if (response && response.id){
					row.data('id', response.id);
				}
				node.addClass('shaded').attr('aria-disabled', 'true');
			}
		);
	},

	onEdit : function(node){
		if ($(node).find('input').length){
			return;
		}
		var current = $(node).text();
		$(node).text('');
		// Enter übernimmt, Escape verwirft – danach steht der Fokus wieder auf
		// der Zelle. Tab bleibt Tab: Fokus wandert zum nächsten Bedienelement
		// (vorher sprang er zwischen den beiden Textzellen hin und her); landet
		// er dabei auf der anderen Textzelle, öffnet sie gleich ihr Feld.
		var zurueck = false;
		var spalte = $(node).closest('table').find('thead th').eq($(node).index()).text();
		$('<input type="text" />').val(current)
			.attr('aria-label', spalte)
			.on('blur', function(){
				var newValue = $(this).val();
				if (newValue !== current){
					$(node).parents('tr').first().find('td.icon-checkmark').removeClass('shaded').attr('aria-disabled', 'false');
				}
				$(this).remove();
				$(node).text(newValue);
				if (zurueck) {
					$(node).trigger('focus');
				}
			})
			.on('keydown', function(event){
				if (event.keyCode === 9) {
					antivirusSettings.weiterBearbeiten = true;
					setTimeout(function(){ antivirusSettings.weiterBearbeiten = false; }, 0);
					return;
				}
				if (event.keyCode === 27) {
					$(this).val(current);
				}
				if (event.keyCode === 27 || event.keyCode === 13) {
					event.preventDefault();
					event.stopPropagation();
					zurueck = true;
					$(this).trigger('blur');
				}
			})
			.appendTo(node)
			.trigger('focus');
	},

	deleteRow : function(){
		var row = $(this).parent();
		// Fokus nicht mit der Zeile verlieren: nächste Zeile, sonst vorige, sonst „Regel hinzufügen“
		var naechste = row.next('tr').find('td.icon-delete');
		if (!naechste.length) {
			naechste = row.prev('tr').find('td.icon-delete');
		}
		if (!naechste.length) {
			naechste = $('#antivirus-add');
		}
		if (!row.data('id')) {
			// Noch nicht gespeicherte Zeile: nur aus der Tabelle nehmen
			row.remove();
			naechste.trigger('focus');
			return;
		}
		row.hide();
		naechste.trigger('focus');
		$.post(OC.generateUrl('apps/files_antivirus/settings/rule/delete'), {id : row.data('id')},
			function onSuccess(){
				row.remove();
			}
		).fail(function(){
			row.show();
		});
	},

	renderSelect : function(parent, data){
		var select = $('<select />')
				.attr('aria-label', data.label || null)
				.on('change', function(){
					$(this).parents('tr').first().find('td.icon-checkmark').removeClass('shaded').attr('aria-disabled', 'false');
				});
		for (var i=0; i<data.options.length; i++){
			var option = $('<option />');
			option.attr('value', data.options[i].value)
					.text(data.options[i].title)
			;
			if (data.options[i].value == data.current){
				option.attr('selected', '');
			}
			select.append(option);
		}
		parent.append(select);
	}
};


function av_mode_show_options(str){
	if ( str === 'daemon'){
		$('p.av_socket, p.av_path, p.av_mode_icap, p.av_mode_fortinet, p.av_mode_mawgw').hide('slow');
		$('#av_socket, #av_path, #av_request_service, #av_response_header, #av_fortinet_request_service, #av_fortinet_response_header, #av_mawgw_request_service, #av_mawgw_response_header').attr('disabled', true);
		$('#av_host, #av_port').attr('disabled', false);
		$('p.av_host, p.av_port').show('slow');
		return;
	}
	if ( str === 'socket' ) {
		$('#av_socket').attr('disabled', false);
		$('p.av_socket').show('slow');
		$('p.av_path, p.av_host, p.av_port, p.av_mode_icap, p.av_mode_fortinet, p.av_mode_mawgw').hide('slow');
		$('#av_path, #av_host, #av_port, #av_request_service, #av_response_header, #av_fortinet_request_service, #av_fortinet_response_header, #av_mawgw_request_service, #av_mawgw_response_header').attr('disabled', true);
		return;
	}
	if (str === 'executable') {
		$('p.av_socket, p.av_host, p.av_port, p.av_mode_icap, p.av_mode_fortinet, p.av_mode_mawgw').hide('slow');
		$('#av_socket, #av_host, #av_port, #av_request_service, #av_response_header, #av_fortinet_request_service, #av_fortinet_response_header, #av_mawgw_request_service, #av_mawgw_response_header').attr('disabled', true);
		$('#av_path').attr('disabled', false);
		$('p.av_path').show('slow');
		return;
	}
	if (str === 'fortinet') {
		$('p.av_socket, p.av_path, p.av_mode_icap, p.av_mode_mawgw').hide('slow');
		$('#av_socket, #av_path, #av_request_service, #av_response_header, p.av_mode_mawgw, #av_mawgw_request_service, #av_mawgw_response_header').attr('disabled', true);
		$('#av_host, #av_port, #av_fortinet_request_service, #av_fortinet_response_header').attr('disabled', false);
		$('p.av_host, p.av_port, p.av_mode_fortinet').show('slow');
		return;
	}
	if (str === 'mawgw') {
		$('p.av_socket, p.av_path, p.av_mode_icap, p.av_mode_fortinet').hide('slow');
		$('#av_socket, #av_path, #av_request_service, #av_response_header').attr('disabled', true);
		$('#av_host, #av_port, #av_mawgw_request_service, #av_mawgw_response_header').attr('disabled', false);
		$('p.av_host, p.av_port, p.av_mode_mawgw').show('slow');
		return;
	}
	// icap below
	$('p.av_socket, p.av_path, p.av_mode_fortinet, p.av_mode_mawgw').hide('slow');
	$('#av_socket, #av_path, #av_fortinet_request_service, #av_fortinet_response_header, #av_mawgw_request_service, #av_mawgw_response_header').attr('disabled', true);
	$('#av_host, #av_port, #av_request_service, #av_response_header').attr('disabled', false);
	$('p.av_host, p.av_port, p.av_mode_icap').show('slow');
}

$(document).ready(function() {
	$('#av_submit').on('click', function(event){
		var isValid = true;
		$('#av_host, #av_port, #av_stream_max_length, #av_max_file_size').each(
			function(i, e) {
				if (typeof(e.checkValidity) === "function" && !e.checkValidity()){
					isValid = false;
					e.reportValidity();
				}
			}
		);
		event.preventDefault();
		if (isValid) {
			OC.msg.startAction('#antivirus_save_msg', t('files_antivirus', 'Saving...'));
			$.post(
				OC.generateUrl('apps/files_antivirus/settings/save'),
				$('#antivirus').serializeArray(),
				function (data) {
					OC.msg.finishedAction('#antivirus_save_msg', data);
					if (data.connection !== 1) {
						OC.Notification.showTemporary(
							t(
								'files_antivirus',
								'Test scan was unsuccessful. Please recheck antivirus settings.'
							)
						);
					}
				}
			);
		}
	});

	// Die Regeln werden nur beim ersten Aufklappen geladen – vorher hängte
	// jedes erneute Aufklappen alle Regeln ein weiteres Mal an die Tabelle.
	$('#antivirus-advanced').on('click', function () {
		var $spoiler = $('#antivirus-spoiler');
		var offen = !$spoiler.is(':visible');
		$spoiler.toggle(offen);
		$(this).attr('aria-expanded', offen ? 'true' : 'false');
		if (offen && !antivirusSettings.geladen) {
			antivirusSettings.geladen = true;
			antivirusSettings.init();
		}
	});

	// Nach Zurücksetzen/Leeren die Tabelle ganz neu aufbauen (vorher blieben
	// leere Zeilen stehen, weil nur die Zellen entfernt wurden)
	$('#antivirus-reset').on('click', function (){
		$.post(OC.generateUrl('apps/files_antivirus/settings/rule/reset'),
			function onSuccess(){
				$('#antivirus-statuses tbody').empty();
				antivirusSettings.init();
			});
	});

	$('#antivirus-clear').on('click', function (){
		$.post(OC.generateUrl('apps/files_antivirus/settings/rule/clear'),
			function onSuccess(){
				$('#antivirus-statuses tbody').empty();
				antivirusSettings.init();
			});
	});

	$('#antivirus-add').on('click', function (){
		antivirusSettings.renderRow({
			id : '',
			status_type : 1,
			result : '',
			description : '',
			status : 0
		});
		$('#antivirus-statuses tbody tr:last-child td.editable').first().trigger('click');
	});

	$('#antivirus-statuses tbody').on('click', 'td.editable', function(){
		antivirusSettings.onEdit(this);
	});
	$('#antivirus-statuses tbody').on('focus', 'td.editable', function(){
		if (antivirusSettings.weiterBearbeiten) {
			antivirusSettings.weiterBearbeiten = false;
			antivirusSettings.onEdit(this);
		}
	});
	$('#antivirus-statuses tbody').on('keydown', 'td.editable', function(event){
		// Tasten im geöffneten Feld gehören dem Feld
		if (event.target !== this) {
			return;
		}
		if (event.keyCode === 13 || event.keyCode === 113) {
			event.preventDefault();
			antivirusSettings.onEdit(this);
		}
	});
	$('#antivirus-statuses tbody').on('click', 'td.icon-delete', antivirusSettings.deleteRow);
	$('#antivirus-statuses tbody').on('click', 'td.icon-checkmark', antivirusSettings.onSave);
	// Tastaturbedienung fuer die als Buttons fungierenden Icon-Zellen (Enter/Space)
	$('#antivirus-statuses tbody').on('keydown', 'td.icon-checkmark, td.icon-delete', function(event){
		if (event.keyCode === 13 || event.keyCode === 32) {
			event.preventDefault();
			$(this).trigger('click');
		}
	});
	$("#av_mode").change(function () {
		var str = $("#av_mode").val();
		av_mode_show_options(str);
	});
	$("#av_mode").change();
	$("#av_scan_background").change(function () {
		if ($("#av_scan_background").val() === 'true') {
			$('p.infected_action').show('slow');
		} else {
			$('p.infected_action').hide('slow');
		}
	});
	$("#av_scan_background").change();
});
