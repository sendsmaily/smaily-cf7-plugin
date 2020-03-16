(function( $ ) {
	function getUrlParam(name) {
		var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
			if (results == null) {
				return null;
			}
			else {
				return decodeURI(results[1]) || 0;
			}

	}
	$(document).ready(function () {
		$('#smailyforcf7_validate_credentials').click(function () {
			$.post(smaily_for_cf7.ajax_url,
			{
				// wp ajax action
				action: 'verify_credentials_callback',

				// vars
				subdomain: $('input[name="smailyforcf7[subdomain]"]').val(),
				username: $('input[name="smailyforcf7[username]"]').val(),
				password: $('input[name="smailyforcf7[password]"]').val(),

				// Can't use WPCF7_ContactForm in callback, get form (post) ID with Ajax.
				form_id: getUrlParam('post'),
				// send the nonce along with the request
				nonce: smaily_for_cf7.nonce
			})
			.always(function (result) {
				$('.smailyforcf7-response').hide();
				if (result.code !== 200) {
					$('#smailyforcf7-credentials-error').text(result.message).show();
					return;
				}
				$('#smailyforcf7-credentials-success').text(result.message).show();
				// Autoresponders are default hidden if user hasn't validated credentials.
				$('#smailyforcf7-autoresponders').show();
				// Fill autoresponder <select> with autoresponders if currently empty.
				if ($('#smailyforcf7-autoresponder-select').has('option').length > 0) {
					$.each(result.autoresponders, function(index, autoresponder) {
						var option = new Option(autoresponder.title, autoresponder.id);
						$('#smailyforcf7-autoresponder-select').append($(option));
					});
				}

			})
		});

		$('#smailyforcf7_remove_credentials').click(function () {
			$.post(
				smaily_for_cf7.ajax_url,
				{
					action: 'remove_credentials_callback',
					// Can't use WPCF7_ContactForm in callback, get form (post) ID with Ajax.
					form_id: getUrlParam('post'),
				},
				function (result) {
					if (result.code === 200) {
						$('#smailyforcf7-credentials-success').text(result.message).show();
						// Clear credentials
						$('input[name="smailyforcf7[subdomain]"]').val('');
						$('input[name="smailyforcf7[username]"]').val('');
						$('input[name="smailyforcf7[password]"]').val('');
						// User shouldn't be able to select autoresponder without credentials.
						$('#smailyforcf7-autoresponders').hide();
					} else {
						$('#smailyforcf7-credentials-error').text(result.message).show();
					}
				},
			);
			return false;
		});
	});
})( jQuery );
